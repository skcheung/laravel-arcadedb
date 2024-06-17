<?php

namespace SKCheung\ArcadeDB\Eloquent;

use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectId;
use SKCheung\ArcadeDB\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use SKCheung\ArcadeDB\Query\Builder as QueryBuilder;

abstract class Model extends BaseModel
{
    protected $primaryKey = '@rid';

    protected $keyType = 'string';

    protected $label = null;

    public $incrementing = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    public function getAttribute($key)
    {
        if ($key === 'id') {
            $key = '@rid';
        }

        return parent::getAttribute($key);
    }

    public function setLabel($label): void
    {
        $this->label = $label;
    }

    public function newEloquentBuilder($query): Builder
    {
        return new EloquentBuilder($query);
    }

    protected function newBaseQueryBuilder(): QueryBuilder
    {
        $connection = $this->getConnection();
        $grammar = $connection->getQueryGrammar();

        return new QueryBuilder($connection, $grammar);
    }

    public function getQualifiedKeyName(): string
    {
        return $this->getKeyName();
    }

    public function addTimestamps(): void
    {
        $this->updateTimestamps();
    }

    public function getDirty(): array
    {
        $dirty = parent::getDirty();

        // We need to remove the primary key from the dirty attributes since primary keys
        // never change and when updating it shouldn't be part of the attribtues.
        if (isset($dirty[$this->primaryKey])) unset($dirty[$this->primaryKey]);

        return $dirty;
    }

}
