<?php

namespace SKCheung\ArcadeDB\Eloquent;

use Illuminate\Contracts\Support\Arrayable;
use SKCheung\ArcadeDB\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
    public function find($id, $columns = ['*'])
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return $this->findMany($id, $columns);
        }

        return $this->whereKey($id)->first($columns);
    }
}
