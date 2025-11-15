<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Toporia\Framework\Container\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Search\Contracts\SearchClientInterface;
use Toporia\Framework\Search\Contracts\SearchIndexerInterface;
use Toporia\Framework\Search\Elasticsearch\ElasticsearchClient;
use Toporia\Framework\Search\Elasticsearch\ElasticsearchIndexer;
use Toporia\Framework\Search\SearchManager;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        /** @var \Elastic\Elasticsearch\Client */
        $container->singleton(Client::class, function () {
            $config = config('search.connections.elasticsearch', []);

            /** @var \Elastic\Elasticsearch\ClientBuilder $builder */
            $builder = ClientBuilder::create()
                ->setHosts($config['hosts'] ?? ['http://localhost:9200'])
                ->setRetries($config['retries'] ?? 2)
                ->setSSLVerification($config['ssl_verification'] ?? true);

            // Set HTTP client options (timeout, connect_timeout, etc.)
            $requestTimeout = $config['request_timeout'] ?? 2.0;
            $builder->setHttpClientOptions([
                'timeout' => $requestTimeout,
                'connect_timeout' => $requestTimeout,
            ]);

            if (!empty($config['username']) && !empty($config['password'])) {
                $builder->setBasicAuthentication($config['username'], $config['password']);
            }

            if (!empty($config['api_key'])) {
                $builder->setApiKey($config['api_key']);
            }

            return $builder->build();
        });

        $container->singleton(SearchClientInterface::class, function ($c) {
            $bulkConfig = config('search.bulk', []);
            return new ElasticsearchClient(
                $c->get(Client::class),
                (int) ($bulkConfig['batch_size'] ?? 500),
                (int) ($bulkConfig['flush_interval_ms'] ?? 1000)
            );
        });

        $container->singleton(SearchIndexerInterface::class, function ($c) {
            return new ElasticsearchIndexer($c->get(SearchClientInterface::class));
        });

        $container->singleton(SearchManager::class, function ($c) {
            return new SearchManager(
                $c->get(SearchClientInterface::class),
                $c->get(SearchIndexerInterface::class),
                config('search.indices', [])
            );
        });

        $container->bind('search', fn($c) => $c->get(SearchManager::class));
    }

    public function boot(ContainerInterface $container): void
    {
        // Warm up default indices if configured
        $manager = $container->get(SearchManager::class);
        $manager->ensureIndices();
    }
}
