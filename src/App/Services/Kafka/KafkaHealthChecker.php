<?php

declare(strict_types=1);

namespace App\Services\Kafka;

use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\Support\Accessors\Log;

/**
 * Kafka Health Checker
 *
 * Checks Kafka connection, API version, and broker health.
 * Single Responsibility: Only handles health checking.
 *
 * @package App\Services\Kafka
 */
final class KafkaHealthChecker
{
    public function __construct(
        private readonly RealtimeManager $realtimeManager
    ) {}

    /**
     * Check if Kafka broker is accessible.
     *
     * @return bool True if accessible
     */
    public function checkConnection(): bool
    {
        try {
            $broker = $this->realtimeManager->broker('kafka');
            if (!$broker) {
                return false;
            }

            // Check connection by attempting to list topics
            $container = config('kafka.kafka_container', 'project_topo_kafka');
            $bootstrapServer = config('kafka.bootstrap_server', 'localhost:29092');

            $cmd = sprintf(
                'docker exec %s /usr/bin/kafka-broker-api-versions --bootstrap-server %s >/dev/null 2>&1',
                escapeshellarg($container),
                escapeshellarg($bootstrapServer)
            );

            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            return $exitCode === 0;
        } catch (\Throwable $e) {
            Log::error("Kafka connection check failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check Kafka API version compatibility.
     *
     * @return bool True if API version is compatible
     */
    public function checkApiVersion(): bool
    {
        try {
            $container = config('kafka.kafka_container', 'project_topo_kafka');
            $bootstrapServer = config('kafka.bootstrap_server', 'localhost:29092');

            $cmd = sprintf(
                'docker exec %s /usr/bin/kafka-broker-api-versions --bootstrap-server %s 2>&1',
                escapeshellarg($container),
                escapeshellarg($bootstrapServer)
            );

            $output = shell_exec($cmd);

            // Check if we got valid API version response
            return str_contains($output ?? '', 'Produce')
                && str_contains($output ?? '', 'Fetch')
                && !str_contains($output ?? '', 'Error')
                && !str_contains($output ?? '', 'could not be established');
        } catch (\Throwable $e) {
            Log::error("Kafka API version check failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check security protocol configuration.
     *
     * @return bool True if security protocol is correctly configured
     */
    public function checkSecurityProtocol(): bool
    {
        $brokerConfig = config('realtime.brokers.kafka', []);
        $consumerConfig = $brokerConfig['consumer_config'] ?? [];
        $producerConfig = $brokerConfig['producer_config'] ?? [];

        $consumerProtocol = $consumerConfig['security.protocol'] ?? null;
        $producerProtocol = $producerConfig['security.protocol'] ?? null;

        // Both should be set and match (usually 'plaintext' for development)
        if ($consumerProtocol && $producerProtocol) {
            return $consumerProtocol === $producerProtocol && $consumerProtocol === 'plaintext';
        }

        return false;
    }

    /**
     * Perform comprehensive health check.
     *
     * @return array<string, bool> Health check results
     */
    public function performHealthCheck(): array
    {
        return [
            'connection' => $this->checkConnection(),
            'api_version' => $this->checkApiVersion(),
            'security_protocol' => $this->checkSecurityProtocol(),
        ];
    }
}
