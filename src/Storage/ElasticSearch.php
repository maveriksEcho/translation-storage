<?php

namespace Translate\StorageManager\Storage;

use Elasticsearch\Client;
use Translate\StorageManager\Contracts\BulkActions;
use Translate\StorageManager\Contracts\TranslationStorage;

class ElasticSearch implements TranslationStorage, BulkActions
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param Client $client
     * @param array $options
     */
    public function __construct(Client $client, array $options = [])
    {
        $this->client = $client;
        $this->processOptions($options);

        if (!$this->client->indices()->exists(['index' => $this->options['indexName']])) {
            $this->client->indices()->create([
                'index' => $this->options['indexName'],
                'body' => [
                    'mappings' => [
                        'properties' => [
                            'key' => ['type' => 'keyword'],
                            'value' => ['type' => 'text', 'index_options' => 'freqs'],
                            'lang' => ['type' => 'keyword'],
                            'group' => ['type' => 'keyword']
                        ]
                    ]
                ],
            ]);
        }
    }

    /**
     * @param array $options
     */
    protected function processOptions(array $options): void
    {
        $options['indexName'] = $options['indexName'] ?? 'translation';
        $this->options = $options;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, string $value, string $lang, string $group = null): bool
    {
        $this->client->deleteByQuery([
            'index' => $this->options['indexName'],
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => [
                            'key' => $key,
                            'lang' => $lang,
                            'group' => $group
                        ]
                    ]
                ]
            ]
        ]);
        $resp = $this->client->index([
            'index' => $this->options['indexName'],
            'body' => [
                'key' => $key,
                'value' => $value,
                'lang' => $lang,
                'group' => $group
            ]
        ]);

        return isset($resp['result']) && $resp['result'] === 'created';
    }

    /**
     * @inheritDoc
     */
    public function find(string $key, string $lang): ?string
    {
        $resp = $this->client->search(['index' => $this->options['indexName'], 'body' => [
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => ['lang' => $lang]],
                        ['term' => ['key' => $key]]
                    ],
                ],

            ]
        ]]);

        return $resp['hits']['hits'][0]['_source']['value'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $resp = $this->client->deleteByQuery(['index' => $this->options['indexName'], 'body' => [
            'query' => [
                'term' => ['key' => $key]
            ]
        ]]);

        return empty($resp['failures']);
    }

    /**
     * @inheritDoc
     */
    public function findByGroup(string $group, string $lang): array
    {
        $resp = $this->client->search(['index' => $this->options['indexName'], 'body' => [
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => ['lang' => $lang]],
                        ['term' => ['group' => $group]]
                    ],
                ],

            ]
        ]]);

        return $this->parseResults($resp['hits']['hits']);
    }

    /**
     * @param array $hits
     * @return array
     */
    protected function parseResults(array $hits): array
    {
        return array_map(static function (array $hit) {
            return $hit['_source'];
        }, $hits);
    }

    /**
     * @inheritDoc
     */
    public function deleteGroup(string $group): bool
    {
        $resp = $this->client->deleteByQuery(['index' => $this->options['indexName'], 'body' => [
            'query' => [
                'term' => ['group' => $group]
            ]
        ]]);

        return empty($resp['failures']);
    }

    /**
     * @inheritDoc
     */
    public function bulkSet(array $data): bool
    {
        $this->client->deleteByQuery(['index' => $this->options['indexName'], 'body' => [
            'query' => [
                'match_all' => ['boost' => 1.0]
            ]
        ]]);
        $body = [];
        foreach ($data as $item) {
            $body[] = ['index' => ['_index' => $this->options['indexName']]];
            $body[] = $item;
        }
        $resp = $this->client->bulk(['body' => $body]);

        return $resp['errors'] === false;
    }
}
