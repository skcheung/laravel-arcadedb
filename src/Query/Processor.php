<?php

namespace SKCheung\ArcadeDB\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Illuminate\Support\Arr;

class Processor extends BaseProcessor
{
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {

        $connection = $query->getConnection();
        $connection->insert($sql, $values);

        $item = Arr::first($connection->getLastResults());

        return $item ? $item['@rid'] : null;
    }
}
