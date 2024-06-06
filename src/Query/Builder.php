<?php

namespace SKCheung\ArcadeDB\Query;

use SKCheung\ArcadeDB\Connection;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Arr;
class Builder extends BaseBuilder
{
    public $operators = [
        '=',
        '==',
        '<=>',
        '<',
        '>',
        '<=',
        '>=',
        '!=',
        '<>',
        'LIKE',
        'ILIKE',
        'BETWEEN',
        'IS',
        'IS NOT',
        'INSTANCEOF',
        'IN',
        'CONTAINS',
        'CONTAINSALL',
        'CONTAINSANY',
        'CONTAINSKEY',
        'CONTAINSVALUE',
        'CONTAINSTEXT',
        'MATCHES',
    ];
}
