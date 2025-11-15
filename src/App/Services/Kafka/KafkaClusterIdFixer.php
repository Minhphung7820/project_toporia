<?php

declare(strict_types=1);

namespace App\Services\Kafka;

use Toporia\Framework\Support\Accessors\Log;

/**
 * Kafka Cluster ID Fixer
 *
 * Detects and fixes cluster ID mismatch issues.
 * Single Responsibility: Only handles cluster ID mismatch.
 *
 * @package App\Services\Kafka
 */
final class KafkaClusterIdFixer
{
    private const MISMATCH_MARKER = '/var/lib/kafka/data/.cluster_id_mismatch_detected';

    /**
     * Check if cluster ID mismatch needs to be fixed.
     *
     * @return bool True if fix is needed
     */
    public function needsFix(): bool
    {
        try {
            $container = config('kafka.kafka_container', 'project_topo_kafka');

            // Check Kafka logs for cluster ID mismatch error
            $cmd = sprintf(
                'docker logs %s 2>&1 | grep -q "InconsistentClusterIdException"',
                escapeshellarg($container)
            );

            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            if ($exitCode === 0) {
                Log::warning('Cluster ID mismatch detected in Kafka logs');
                return true;
            }

            // Check for marker file (if entrypoint script created it)
            $markerCmd = sprintf(
                'docker exec %s test -f %s 2>/dev/null',
                escapeshellarg($container),
                escapeshellarg(self::MISMATCH_MARKER)
            );

            exec($markerCmd, $output, $exitCode);
            if ($exitCode === 0) {
                Log::warning('Cluster ID mismatch marker file found');
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::error("Failed to check cluster ID mismatch: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Fix cluster ID mismatch by resetting Kafka data.
     *
     * @return bool True if fix was successful
     */
    public function fix(): bool
    {
        try {
            Log::info('Starting cluster ID mismatch fix...');

            $container = config('docker.kafka_container', 'project_topo_kafka');

            // Stop Kafka container
            $stopCmd = sprintf('docker compose stop kafka 2>&1');
            exec($stopCmd, $stopOutput, $stopExitCode);

            if ($stopExitCode !== 0) {
                Log::error("Failed to stop Kafka container: " . implode("\n", $stopOutput));
                return false;
            }

            // Remove Kafka container
            $rmCmd = sprintf('docker compose rm -f kafka 2>&1');
            exec($rmCmd, $rmOutput, $rmExitCode);

            // Remove Kafka data volume
            $volumeCmd = sprintf('docker volume rm toporia_kafka_data 2>&1');
            exec($volumeCmd, $volumeOutput, $volumeExitCode);

            // Ignore volume removal errors (might not exist)
            Log::info('Kafka data volume removed');

            // Start Kafka container (will create new volume with matching cluster ID)
            $startCmd = sprintf('docker compose up -d kafka 2>&1');
            exec($startCmd, $startOutput, $startExitCode);

            if ($startExitCode !== 0) {
                Log::error("Failed to start Kafka container: " . implode("\n", $startOutput));
                return false;
            }

            // Wait for Kafka to be ready
            $this->waitForKafkaReady();

            Log::info('Cluster ID mismatch fixed successfully');
            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to fix cluster ID mismatch: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Wait for Kafka to be ready after restart.
     *
     * @param int $maxWait Maximum wait time in seconds
     * @return bool True if Kafka is ready
     */
    private function waitForKafkaReady(int $maxWait = 60): bool
    {
        $container = config('kafka.kafka_container', 'project_topo_kafka');
        $bootstrapServer = config('kafka.bootstrap_server', 'localhost:29092');

        for ($i = 0; $i < $maxWait; $i++) {
            $cmd = sprintf(
                'docker exec %s /usr/bin/kafka-broker-api-versions --bootstrap-server %s >/dev/null 2>&1',
                escapeshellarg($container),
                escapeshellarg($bootstrapServer)
            );

            $exitCode = 0;
            exec($cmd, $output, $exitCode);

            if ($exitCode === 0) {
                Log::info("Kafka is ready after {$i} seconds");
                return true;
            }

            sleep(1);
        }

        Log::error("Kafka did not become ready within {$maxWait} seconds");
        return false;
    }
}
