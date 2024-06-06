<?php

namespace SKCheung\ArcadeDB;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Connection extends BaseConnection
{
    protected GuzzleClient $connection;

    public function __construct(array $config)
    {
        $this->config = $config;

        $options = Arr::get($config, 'options', []);

        $this->connection = $this->createConnection($config, $options);

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();
    }

    public function getDriverName(): string
    {
        return 'arcadedb';
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $response = $this->connection->request('POST', 'command/' . $this->config['database'], [
                'json' => [
                    'language' => 'sql',
                    'command' => $this->bindQueryParams($query, $bindings),
                ]
            ]);

            return $this->decode($response);
        });
    }

    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $response = $this->connection->request('POST', 'command/' . $this->config['database'], [
                'json' => [
                    'language' => 'sql',
                    'command' => $this->bindQueryParams($query, $bindings),
                ]
            ]);

            $this->recordsHaveBeenModified();

            return $this->decode($response);
        });
    }

    public function ping(): bool
    {
        $response = $this->connection->request('GET', 'ready');

        return $response->getStatusCode() === 204;
    }

    protected function getDefaultPostProcessor(): Query\Processor
    {
        return new Query\Processor;
    }

    protected function getDefaultQueryGrammar(): Query\Grammar
    {
        return new Query\Grammar;
    }

    protected function createConnection(array $config, array $options)
    {
        $baseUri = (!parse_url($config['host'], PHP_URL_HOST)) ? $config['host'] . ':' . $config['port'] : $config['host'];

        $clientConfig = [
            'base_uri' => $baseUri . '/api/v1/',
        ];

        if ($config['username'] && $config['password']) {
            $credentials = base64_encode($config['username'] . ':' . $config['password']);
            $clientConfig['headers']['Authorization'] = 'Basic ' . $credentials;
        }

        $clientConfig['headers']['Content-Type'] = 'application/json';

        return new GuzzleClient($clientConfig);
    }

    protected function decode($response)
    {
        if (is_array($response)) {
            return $response;
        }

        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded['result'];
    }

    protected function bindQueryParams($query, $bindings)
    {
        collect($this->prepareBindings($bindings))->each(function ($value) use (&$query) {
            $value = is_string($value) ? "'$value'" : $value;
            $query = Str::of($query)->replace('?', $value)->value();
        });

        return $query;
    }
}
