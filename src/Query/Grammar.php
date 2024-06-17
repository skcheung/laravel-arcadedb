<?php

namespace SKCheung\ArcadeDB\Query;

use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Grammar extends BaseGrammar
{
    protected Builder $query;

    public function parameter($value)
    {
        // Validate whether the requested field is the
        // node id, in that case id(n) doesn't work as
        // a placeholder so we transform it to the id replacement instead.

        // When coming from a WHERE statement we'll have to pluck out the column
        // from the collected attributes.

        if (is_bool($value)) {
            // For the boolean column this need to be un-quote unless orientdb will reject
            return $value ? 'true' : 'false';
        }

        if (is_array($value) && isset($value['binding'])) {
            $value = $value['binding'];
        } elseif (is_array($value) && isset($value['column'])) {
            $value = $value['column'];
        } elseif ($this->isExpression($value)) {
            $value = $this->getValue($value);
        }

        $property = $this->getIdReplacement($value);

        if (str_contains($property, '.')) {
            $property = explode('.', $property)[1];
        }

        if (is_string($property) && is_array(json_decode($property, true)) && (json_last_error() == JSON_ERROR_NONE)) {// Don't wrap json data
            return $property;
        }

        $property = str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"], ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $property);

        if (preg_match('~#(-)?[0-9]+:[0-9]+~', $property)) {//is (graph) id, don't wrap it or error would be thrown
            return $property;
        }

        return "'" . $property . "'";
    }

    public function prepareLabels(array $labels)
    {
        // get the labels prepared and back to a string imploded by : they go.
        return implode('', Arr::map($labels, [$this, 'wrapLabel']));
    }

    /**
     * Make sure the label is wrapped with backticks
     *
     * @param string $label
     * @return string
     */
    public function wrapLabel(string $label): string
    {
        // every label must begin with a ':' so we need to check
        // and reformat if need be.
        return trim(':`' . preg_replace('/^:/', '', $label) . '`');
    }

    /**
     * Prepare a relationship label.
     *
     * @param string $relation
     * @param string $related
     * @return string
     */
    public function prepareRelation(string $relation, string $related): string
    {
        return "rel_" . mb_strtolower($relation) . '_' . $related . ":{$relation}";
    }

    /**
     * Turn labels like this ':User:Admin'
     * into this 'user_admin'
     *
     * @param string $labels
     * @return string
     */
    public function normalizeLabels(string $labels): string
    {
        return Str::of(preg_replace('/^:/', '', $labels))->replace(':', '_')->lower()->value();
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     */
    public function wrap($value, bool $prefixAlias = false): string
    {
        // We will only wrap the value unless it has parentheses
        // in it which is the case where we're matching a node by id, or an *
        // and last whether this is a pre-formatted key.
        if (preg_match('/[(|)]/', $value) || $value == '*' || strpos($value, '.') !== false) return $value;

        // In the case where the developer specifies the properties and not returning
        // everything, we need to check whether the primaryKey is meant to be returned
        // since Orientdb's way of evaluating returned properties for the Node id is
        // different: id(n) instead of n.id

//        if ($value == 'id')
//        {
//            return 'id(' . $this->query->modelAsNode() . ')';
//        }

        //return $this->query->modelAsNode() . '.' . $value;
        return $value;
    }

    /**
     * Turn an array of values into a comma separated string of values
     * that are escaped and ready to be passed as values in a query
     *
     * @param array $values
     * @return  string
     */
    public function valufy($values)
    {
        // we'll only deal with arrays so let's turn it into one if it isn't
        if (!is_array($values)) $values = [$values];

        // escape and wrap them with a quote.
        $values = array_map(function ($value) {
            // We need to keep the data type of values
            // except when they're strings, we need to
            // escape wrap them.
            if (is_string($value)) {
                $value = "'" . addslashes($value) . "'";
            }
            // In order to support boolean value types and not have PHP convert them to their
            // corresponding string values, we'll have to handle boolean values and add their literal string representation.
            elseif (is_bool($value)) {
                $value = ($value) ? 'true' : 'false';
            }

            return $value;

        }, $values);

        // stringify them.
        return implode(', ', $values);
    }

    /**
     * Get a model's name as a Node placeholder
     *
     * i.e. in "MATCH (user:`User`)"... "user" is what this method returns
     *
     * @param null $labels The labels we're choosing from
     * @param null $relation
     * @return string
     */
    public function modelAsNode($labels = null, $relation = null): string
    {
        if (is_null($labels)) {
            return 'n';
        }
        if (is_array($labels)) {
            $labels = reset($labels);
        }

        // When this is a related node we'll just prepend it with 'with_' that way we avoid
        // clashing node models in the cases like using recursive model relations.
        //
        if (!is_null($relation)) {
            $labels = 'with_' . $relation . '_' . $labels;
        }

        return mb_strtolower($labels);
    }

    /**
     * Set the query builder for this grammar instance.
     *
     * @param $query
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }

    public function getIdReplacement($column): array|string|null
    {
        // If we have id(n) we're removing () and keeping idn
        $column = preg_replace('/[(|)]/', '', $column);

        // When it's a form of node.attribute we'll just remove the '.' so that
        // we get a consistent form of binding key/value pairs.
        if (strpos($column, '.')) {
            return str_replace('.', '', $column);
        }

        return $column;
    }

    protected function prepareEntities(array $entities): string
    {
        return implode(', ', array_map([$this, 'prepareEntity'], $entities));
    }

    /**
     * Prepare an entity's values to be used in a query, performs sanitization and reformatting.
     *
     * @param array $entity
     * @param bool $identifier
     * @return string
     */
    protected function prepareEntity(array $entity, bool $identifier = false): string
    {
        $label = (is_array($entity['label'])) ? $this->prepareLabels($entity['label']) : $entity['label'];

        if ($identifier) $label = $this->modelAsNode($entity['label']) . $label;

        $bindings = $entity['bindings'];

        $properties = [];
        foreach ($bindings as $key => $value) {
            // From the Orientdb docs:
            //  "NULL is not a valid property value. NULLs can instead be modeled by the absence of a key."
            // So we'll just ignore null keys if they occur.
            if (is_null($value)) continue;

            $key          = $this->propertize($key);
            $value        = $this->valufy($value);
            $properties[] = "$key: $value";
        }

        return "($label { " . implode(', ', $properties) . '})';
    }

    /**
     * Turn a string into a valid property for a query.
     *
     * @param string $property
     * @return string
     */
    public function propertize($property): string
    {
        // Sanitize the string from all characters except alpha numeric.
        return preg_replace('[^A-Za-z0-9]', '', $property);
    }

    public function wrapTable($table)
    {
        return $this->getValue($table);
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }
}
