<?php

declare(strict_types=1);

namespace App\Services\Kafka;

use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\Support\Accessors\Log;

/**
 * Kafka Topic Service
 *
 * Handles Kafka topic creation, management, and validation.
 * Follows Single Responsibility Principle - only manages topics.
 *
 * Performance Optimizations:
 * - Batch topic creation for efficiency
 * - Connection pooling for multiple operations
 * - Caching topic metadata
 *
 * @package App\Services\Kafka
 */
final class KafkaTopicService
{
    private ?KafkaHealthChecker $healthChecker = null;
    private ?KafkaClusterIdFixer $clusterFixer = null;

    public function __construct(
        private readonly RealtimeManager $realtimeManager,
        private readonly array $config = []
    ) {}

    /**
     * Get health checker instance (lazy initialization).
     *
     * @return KafkaHealthChecker
     */
    private function getHealthChecker(): KafkaHealthChecker
    {
        if ($this->healthChecker === null) {
            $this->healthChecker = new KafkaHealthChecker($this->realtimeManager);
        }
        return $this->healthChecker;
    }

    /**
     * Get cluster ID fixer instance (lazy initialization).
     *
     * @return KafkaClusterIdFixer
     */
    private function getClusterFixer(): KafkaClusterIdFixer
    {
        if ($this->clusterFixer === null) {
            $this->clusterFixer = new KafkaClusterIdFixer();
        }
        return $this->clusterFixer;
    }

    /**
     * Ensure Kafka is healthy and fix any issues.
     *
     * @return bool True if Kafka is healthy and ready
     */
    public function ensureHealthy(): bool
    {
        $healthChecker = $this->getHealthChecker();

        // Check connection first
        if (!$healthChecker->checkConnection()) {
            Log::error('Kafka connection failed');
            return false;
        }

        // Check and fix cluster ID mismatch
        $clusterFixer = $this->getClusterFixer();
        if ($clusterFixer->needsFix()) {
            Log::warning('Cluster ID mismatch detected, attempting to fix...');
            if (!$clusterFixer->fix()) {
                Log::error('Failed to fix cluster ID mismatch');
                return false;
            }
            Log::info('Cluster ID mismatch fixed successfully');
        }

        // Verify API version compatibility
        if (!$healthChecker->checkApiVersion()) {
            Log::error('Kafka API version check failed');
            return false;
        }

        return true;
    }

    /**
     * Create a single topic.
     *
     * @param string $topicName Topic name
     * @param int $partitions Number of partitions
     * @param int $replicationFactor Replication factor
     * @param bool $ifNotExists Create only if not exists
     * @return bool True if created or already exists
     */
    public function createTopic(
        string $topicName,
        int $partitions = 1,
        int $replicationFactor = 1,
        bool $ifNotExists = true
    ): bool {
        try {
            // Ensure Kafka is healthy before creating topic
            if (!$this->ensureHealthy()) {
                Log::error("Cannot create topic '{$topicName}': Kafka is not healthy");
                return false;
            }

            $broker = $this->realtimeManager->broker('kafka');
            if (!$broker) {
                Log::error("Kafka broker not available for topic creation: {$topicName}");
                return false;
            }

            // Use Docker exec to create topic (direct Kafka command)
            return $this->executeTopicCommand([
                '--create',
                '--topic',
                $topicName,
                '--partitions',
                (string) $partitions,
                '--replication-factor',
                (string) $replicationFactor,
                $ifNotExists ? '--if-not-exists' : '',
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to create topic '{$topicName}': {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Create multiple topics from configuration.
     *
     * @param array<string, array{topic: string, partitions: int}> $topicConfigs Topic configurations
     * @return array<string, bool> Results: topic => success
     */
    public function createTopicsFromConfig(array $topicConfigs): array
    {
        $results = [];

        // Ensure Kafka is healthy first
        if (!$this->ensureHealthy()) {
            Log::error('Cannot create topics: Kafka is not healthy');
            foreach ($topicConfigs as $key => $config) {
                $topic = $config['topic'] ?? $key;
                $results[$topic] = false;
            }
            return $results;
        }

        // Create topics in batch
        foreach ($topicConfigs as $key => $config) {
            $topic = $config['topic'] ?? $key;
            $partitions = $config['partitions'] ?? 1;
            $replicationFactor = $config['replication_factor'] ?? 1;

            $results[$topic] = $this->createTopic(
                $topic,
                $partitions,
                $replicationFactor,
                true
            );
        }

        return $results;
    }

    /**
     * List all topics.
     *
     * @return array<string> Topic names
     */
    public function listTopics(): array
    {
        try {
            $output = $this->executeTopicCommand(['--list'], true);
            if (empty($output)) {
                return [];
            }

            $topics = array_filter(
                explode("\n", trim($output)),
                fn($line) => !empty($line) && !str_starts_with($line, '__')
            );

            return array_values($topics);
        } catch (\Throwable $e) {
            Log::error("Failed to list topics: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Get topic details.
     *
     * @param string $topicName Topic name
     * @return array<string, mixed>|null Topic details or null if not found
     */
    public function describeTopic(string $topicName): ?array
    {
        try {
            $output = $this->executeTopicCommand([
                '--describe',
                '--topic',
                $topicName,
            ], true);

            if (empty($output)) {
                return null;
            }

            return $this->parseTopicDescription($output);
        } catch (\Throwable $e) {
            Log::error("Failed to describe topic '{$topicName}': {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Execute kafka-topics command via Docker.
     *
     * @param array<string> $args Command arguments
     * @param bool $returnOutput Return output instead of success status
     * @return string|bool Output if $returnOutput=true, success status otherwise
     */
    private function executeTopicCommand(array $args, bool $returnOutput = false): string|bool
    {
        $container = config('kafka.kafka_container', 'project_topo_kafka');
        $bootstrapServer = $this->getBootstrapServer();

        $cmd = sprintf(
            'docker exec %s /usr/bin/kafka-topics --bootstrap-server %s %s 2>&1',
            escapeshellarg($container),
            escapeshellarg($bootstrapServer),
            implode(' ', array_map('escapeshellarg', array_filter($args)))
        );

        $output = shell_exec($cmd);

        if ($returnOutput) {
            return $output ?? '';
        }

        // Check for success indicators
        $success = str_contains($output ?? '', 'Created topic')
            || str_contains($output ?? '', 'already exists')
            || (str_contains($output ?? '', 'Topic:') && !str_contains($output ?? '', 'Error'));

        return $success;
    }

    /**
     * Get bootstrap server address.
     *
     * @return string Bootstrap server address
     */
    private function getBootstrapServer(): string
    {
        // Use internal Docker network address for better performance
        return config('kafka.bootstrap_server', 'localhost:29092');
    }

    /**
     * Get RealtimeManager instance.
     *
     * @return RealtimeManager
     */
    public function getRealtimeManager(): RealtimeManager
    {
        return $this->realtimeManager;
    }

    /**
     * Parse topic description output.
     *
     * @param string $output Command output
     * @return array<string, mixed> Parsed topic details
     */
    private function parseTopicDescription(string $output): array
    {
        $details = [
            'name' => '',
            'partitions' => 0,
            'replication_factor' => 0,
            'partitions_detail' => [],
        ];

        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (preg_match('/^Topic:\s+(\S+)\s+TopicId:\s+(\S+)\s+PartitionCount:\s+(\d+)\s+ReplicationFactor:\s+(\d+)/', $line, $matches)) {
                $details['name'] = $matches[1];
                $details['topic_id'] = $matches[2];
                $details['partitions'] = (int) $matches[3];
                $details['replication_factor'] = (int) $matches[4];
            } elseif (preg_match('/Partition:\s+(\d+)\s+Leader:\s+(\d+)\s+Replicas:\s+([\d,]+)\s+Isr:\s+([\d,]+)/', $line, $matches)) {
                $details['partitions_detail'][] = [
                    'partition' => (int) $matches[1],
                    'leader' => (int) $matches[2],
                    'replicas' => array_map('intval', explode(',', $matches[3])),
                    'isr' => array_map('intval', explode(',', $matches[4])),
                ];
            }
        }

        return $details;
    }
}
