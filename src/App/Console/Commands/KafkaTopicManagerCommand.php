<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Kafka\KafkaTopicService;
use Toporia\Framework\Console\Command;
use Toporia\Framework\Realtime\RealtimeManager;

/**
 * Kafka Topic Manager Command
 *
 * Manages Kafka topics: create, list, describe, and fix issues.
 * Orchestrates topic service operations.
 *
 * Performance:
 * - Batch topic creation
 * - Health checks before operations
 * - Auto-fix cluster ID mismatch
 *
 * Architecture:
 * - Command: Orchestration only
 * - Service: Business logic (KafkaTopicService)
 * - Health Checker: Connection verification
 * - Cluster Fixer: Auto-recovery
 *
 * SOLID Principles:
 * - Single Responsibility: Command orchestrates, Service handles logic
 * - Open/Closed: Extensible via services
 * - Dependency Inversion: Depends on abstractions
 *
 * Usage:
 *   php console kafka:topics:create
 *   php console kafka:topics:create --topic=orders.events --partitions=10
 *   php console kafka:topics:create --from-config
 *   php console kafka:topics:list
 *   php console kafka:topics:describe --topic=orders.events
 *   php console kafka:topics:health-check
 *   php console kafka:topics:fix-cluster-id
 *
 * @package App\Console\Commands
 */
final class KafkaTopicManagerCommand extends Command
{
    protected string $signature = 'kafka:topics {action : Action to perform (create|list|describe|health-check|fix-cluster-id)} {--topic= : Topic name (for create/describe)} {--partitions= : Number of partitions (for create)} {--replication-factor=1 : Replication factor (for create)} {--from-config : Create topics from config/kafka.php} {--all : Create all topics from configuration}';

    protected string $description = 'Manage Kafka topics: create, list, describe, health check, and fix issues';

    private ?KafkaTopicService $topicService = null;

    public function __construct(
        private readonly RealtimeManager $realtimeManager
    ) {
        // Command base class doesn't have constructor
    }

    /**
     * Get topic service instance (lazy initialization).
     *
     * @return KafkaTopicService
     */
    private function getTopicService(): KafkaTopicService
    {
        if ($this->topicService === null) {
            $this->topicService = new KafkaTopicService($this->realtimeManager);
        }
        return $this->topicService;
    }

    /**
     * Execute the command.
     *
     * @return int Exit code
     */
    public function handle(): int
    {
        // Get action from positional argument (index 0)
        $action = $this->argument(0);

        if (empty($action)) {
            $this->error('❌ Action is required');
            $this->newLine();
            $this->line('Available actions: create, list, describe, health-check, fix-cluster-id');
            $this->newLine();
            $this->line('Usage: php console kafka:topics {action} [options]');
            $this->newLine();
            $this->line('Examples:');
            $this->line('  php console kafka:topics create --topic=orders.events --partitions=10');
            $this->line('  php console kafka:topics list');
            $this->line('  php console kafka:topics health-check');
            return 1;
        }

        return match ($action) {
            'create' => $this->handleCreate(),
            'list' => $this->handleList(),
            'describe' => $this->handleDescribe(),
            'health-check' => $this->handleHealthCheck(),
            'fix-cluster-id' => $this->handleFixClusterId(),
            default => $this->handleInvalidAction($action),
        };
    }

    /**
     * Handle topic creation.
     *
     * @return int Exit code
     */
    private function handleCreate(): int
    {
        $this->info('=== Kafka Topic Creation ===');
        $this->newLine();

        $topicService = $this->getTopicService();

        // Check health first
        if (!$topicService->ensureHealthy()) {
            $this->error('❌ Kafka is not healthy. Please check connection and fix issues first.');
            $this->line('   Run: php console kafka:topics:health-check');
            $this->line('   Run: php console kafka:topics:fix-cluster-id');
            return 1;
        }

        $this->info('✅ Kafka is healthy');
        $this->newLine();

        // Create from config
        if ($this->option('from-config') || $this->option('all')) {
            return $this->createTopicsFromConfig();
        }

        // Create single topic
        $topic = $this->option('topic');
        if (empty($topic)) {
            $this->error('❌ Topic name is required. Use --topic=name');
            $this->line('   Or use --from-config to create all topics from config');
            return 1;
        }

        $partitions = (int) $this->option('partitions', 1);
        $replicationFactor = (int) $this->option('replication-factor', 1);

        $this->line("Creating topic: <info>{$topic}</info>");
        $this->line("  Partitions: <info>{$partitions}</info>");
        $this->line("  Replication Factor: <info>{$replicationFactor}</info>");
        $this->newLine();

        if ($topicService->createTopic($topic, $partitions, $replicationFactor)) {
            $this->info("✅ Topic '{$topic}' created successfully");
            return 0;
        } else {
            $this->error("❌ Failed to create topic '{$topic}'");
            return 1;
        }
    }

    /**
     * Create topics from configuration.
     *
     * @return int Exit code
     */
    private function createTopicsFromConfig(): int
    {
        $this->info('Creating topics from configuration...');
        $this->newLine();

        $topicMapping = config('kafka.topic_mapping', []);

        if (empty($topicMapping)) {
            $this->warn('No topic mapping found in config/kafka.php');
            return 1;
        }

        // Extract topic configs
        $topicConfigs = [];
        foreach ($topicMapping as $pattern => $config) {
            if (isset($config['topic']) && isset($config['partitions'])) {
                $topicConfigs[$pattern] = [
                    'topic' => $config['topic'],
                    'partitions' => $config['partitions'],
                    'replication_factor' => 1, // Default for single broker
                ];
            }
        }

        // Add default topic
        $defaultTopic = config('kafka.default_topic', 'realtime');
        $defaultPartitions = (int) config('kafka.default_partitions', 10);

        $topicConfigs['default'] = [
            'topic' => $defaultTopic,
            'partitions' => $defaultPartitions,
            'replication_factor' => 1,
        ];

        $topicService = $this->getTopicService();

        $this->line('Topics to create:');
        foreach ($topicConfigs as $key => $config) {
            $this->line("  - <info>{$config['topic']}</info> ({$config['partitions']} partitions)");
        }
        $this->newLine();

        $results = $topicService->createTopicsFromConfig($topicConfigs);

        $success = 0;
        $failed = 0;

        $this->info('Results:');
        foreach ($results as $topic => $successful) {
            if ($successful) {
                $this->line("  ✅ {$topic}");
                $success++;
            } else {
                $this->line("  ❌ {$topic}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Summary: {$success} created, {$failed} failed");

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Handle topic listing.
     *
     * @return int Exit code
     */
    private function handleList(): int
    {
        $this->info('=== Kafka Topics ===');
        $this->newLine();

        $topics = $this->getTopicService()->listTopics();

        if (empty($topics)) {
            $this->warn('No topics found');
            return 0;
        }

        foreach ($topics as $topic) {
            $this->line("  - <info>{$topic}</info>");
        }

        $this->newLine();
        $this->info("Total: " . count($topics) . " topic(s)");

        return 0;
    }

    /**
     * Handle topic description.
     *
     * @return int Exit code
     */
    private function handleDescribe(): int
    {
        $topic = $this->option('topic');
        if (empty($topic)) {
            $this->error('❌ Topic name is required. Use --topic=name');
            return 1;
        }

        $this->info("=== Topic: {$topic} ===");
        $this->newLine();

        $details = $this->getTopicService()->describeTopic($topic);

        if ($details === null) {
            $this->error("❌ Topic '{$topic}' not found");
            return 1;
        }

        $this->line("Topic Name: <info>{$details['name']}</info>");
        if (isset($details['topic_id'])) {
            $this->line("Topic ID: <info>{$details['topic_id']}</info>");
        }
        $this->line("Partitions: <info>{$details['partitions']}</info>");
        $this->line("Replication Factor: <info>{$details['replication_factor']}</info>");

        if (!empty($details['partitions_detail'])) {
            $this->newLine();
            $this->line("Partition Details:");
            foreach ($details['partitions_detail'] as $partition) {
                $replicas = implode(',', $partition['replicas']);
                $isr = implode(',', $partition['isr']);
                $this->line("  Partition {$partition['partition']}: Leader={$partition['leader']}, Replicas=[{$replicas}], ISR=[{$isr}]");
            }
        }

        return 0;
    }

    /**
     * Handle health check.
     *
     * @return int Exit code
     */
    private function handleHealthCheck(): int
    {
        $this->info('=== Kafka Health Check ===');
        $this->newLine();

        $healthChecker = new \App\Services\Kafka\KafkaHealthChecker($this->realtimeManager);

        $results = $healthChecker->performHealthCheck();

        $allHealthy = true;
        foreach ($results as $check => $status) {
            $icon = $status ? '✅' : '❌';
            $label = ucwords(str_replace('_', ' ', $check));
            $this->line("{$icon} {$label}: " . ($status ? 'OK' : 'FAILED'));
            if (!$status) {
                $allHealthy = false;
            }
        }

        $this->newLine();
        if ($allHealthy) {
            $this->info('✅ Kafka is healthy and ready');
            return 0;
        } else {
            $this->error('❌ Kafka health check failed');
            $this->line('   Run: php console kafka:topics:fix-cluster-id');
            return 1;
        }
    }

    /**
     * Handle cluster ID fix.
     *
     * @return int Exit code
     */
    private function handleFixClusterId(): int
    {
        $this->info('=== Fixing Cluster ID Mismatch ===');
        $this->newLine();

        $fixer = new \App\Services\Kafka\KafkaClusterIdFixer();

        if (!$fixer->needsFix()) {
            $this->info('✅ No cluster ID mismatch detected');
            return 0;
        }

        $this->warn('⚠️  Cluster ID mismatch detected');
        $this->line('This will reset Kafka data (topics and messages will be lost)');

        if (!$this->confirm('Continue?', false)) {
            $this->info('Cancelled');
            return 0;
        }

        $this->newLine();
        $this->line('Fixing cluster ID mismatch...');

        if ($fixer->fix()) {
            $this->newLine();
            $this->info('✅ Cluster ID mismatch fixed successfully');
            $this->line('You may need to recreate topics: php console kafka:topics:create --from-config');
            return 0;
        } else {
            $this->newLine();
            $this->error('❌ Failed to fix cluster ID mismatch');
            return 1;
        }
    }

    /**
     * Handle invalid action.
     *
     * @param string $action Invalid action
     * @return int Exit code
     */
    private function handleInvalidAction(string $action): int
    {
        $this->error("❌ Invalid action: '{$action}'");
        $this->line('Available actions: create, list, describe, health-check, fix-cluster-id');
        return 1;
    }
}
