<?php

namespace SKCheung\ArcadeDB\Enums;

enum QueryLanguage: string
{
    case SQL = 'sql';

    case CYPHER = 'cypher';

    case GREMLIN = 'gremlin';

    case MONGO = 'mongo';

    case GRAPHQL = 'graphql';
}
