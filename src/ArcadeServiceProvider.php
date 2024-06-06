<?php

namespace SKCheung\ArcadeDB;
use Illuminate\Support\ServiceProvider;

class ArcadeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->resolving('db', function ($db) {
            $db->extend('arcadedb', function ($config, $name) {
                $config['name'] = $name;

                return new Connection($config);
            });
        });
    }
}