<?php

namespace SKCheung\ArcadeDB;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Connection as BaseConnection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SKCheung\ArcadeDB\Exceptions\InvalidTransactionException;
use SKCheung\ArcadeDB\Enums\QueryLanguage;

class Connection extends BaseConnection
{
    protected GuzzleClient $connection;

    protected QueryLanguage $language = QueryLanguage::SQL;

    protected array $lastResults;

    protected string $transactionId;

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

    public function getLastResults(): array
    {
        return $this->lastResults;
    }

    public function getQueryLanguage(): string
    {
        return $this->language->value;
    }

    public function setQueryLanguage(QueryLanguage $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $response = $this->connection->request('POST', 'command/' . $this->config['database'], [
                'json' => [
                    'language' => $this->getQueryLanguage(),
                    'command' => $this->bindQueryParams($query, $bindings),
                ]
            ]);

            return $this->decode($response);
        });
    }

    public function affectingStatement($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            $response = $this->connection->request('POST', 'command/' . $this->config['database'], [
                'json' => [
                    'language' => 'sql',
                    'command' => $this->bindQueryParams($query, $bindings),
                ]
            ]);

            $count = Arr::first($this->decode($response))['count'];

            $this->recordsHaveBeenModified($count > 0);

            return $count;
        });
    }

    public function insert($query, $bindings = [], $useWritePdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useWritePdo) {
            if ($this->pretending()) {
                return [];
            }

            $response = $this->connection->request('POST', 'command/' . $this->config['database'], [
                'json' => [
                    'language' => $this->getQueryLanguage(),
                    'command' => $this->bindQueryParams($query, $bindings),
                ]
            ]);

            $this->recordsHaveBeenModified();

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
                    'language' => $this->getQueryLanguage(),
                    'command' => $this->bindQueryParams($query, $bindings),
                ]
            ]);

            $this->recordsHaveBeenModified();

            $this->lastResults = $this->decode($response);

            return $response->getStatusCode() === 200;
        });
    }

    public function ping(): bool
    {
        $response = $this->connection->request('GET', 'ready');

        return $response->getStatusCode() === 204;
    }

    public function serverInfo(): array
    {
        $response = $this->connection->request('GET', 'server');

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getServerVersion(): string
    {
        return Arr::get($this->serverInfo(), 'version', '');
    }

    public function getServerName(): string
    {
        return Arr::get($this->serverInfo(), 'serverName', '');
    }

    public function collection($collection)
    {
        $query = new Query\Builder($this, $this->getQueryGrammar(), $this->getPostProcessor());

        return $query->from($collection);
    }

    public function beginTransaction()
    {
        foreach ($this->beforeStartingTransaction as $callback) {
            $callback($this);
        }


    }

    protected function getDefaultPostProcessor(): Query\Processor
    {
        return new Query\Processor;
    }

    protected function getDefaultQueryGrammar(): Query\Grammar
    {
        return new Query\Grammar;
    }

    protected function createConnection(array $config, array $options): GuzzleClient
    {
        $baseUri = (!parse_url($config['host'], PHP_URL_HOST)) ? $config['host'] . ':' . $config['port'] : $config['host'];

        $clientConfig = [
            'base_uri' => $baseUri . '/api/v1/',
        ];

        if ($config['username'] && $config['password']) {
            $credentials = base64_encode($config['username'] . ':' . $config['password']);
            $clientConfig['headers']['Authorization'] = 'Basic ' . $credentials;
        }

        if ($this->transactionId) {
            $clientConfig['headers']['arcadedb-session-id'] = $this->transactionId;
        }

        $clientConfig['headers']['Content-Type'] = 'application/json';

        return new GuzzleClient($clientConfig);
    }

    public function decode($response)
    {
        if (is_array($response)) {
            return $response;
        }

        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded['result'];
    }

    protected function bindQueryParams($query, $bindings): string
    {
        collect($this->prepareBindings($bindings))->each(function ($value) use (&$query) {
            $value = is_string($value) ? "'$value'" : $value;
            $query = Str::of($query)->replace('?', $value)->value();
        });

        return $query;
    }

    protected function createTransaction()
    {
        $response = $this->connection->request('POST', 'begin/'.$this->config['database']);

        if ($response->hasHeader('arcadedb-session-id')) {
            $this->transactionId = Arr::first($response->getHeader('arcadedb-session-id'));
        }
    }

    public function commit()
    {
        $this->fireConnectionEvent('committing');

        $response = $this->connection->request('POST', 'commit/'.$this->config['database']);

        if ($response->getStatusCode() === 500) {
            throw new InvalidTransactionException();
        }

        $this->fireConnectionEvent('committed');
    }
}
