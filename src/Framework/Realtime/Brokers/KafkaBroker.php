<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Brokers;

use Toporia\Framework\Realtime\Contracts\{BrokerInterface, MessageInterface};
use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\Realtime\Message;
use Toporia\Framework\Realtime\Brokers\Kafka\TopicStrategy\TopicStrategyInterface;
use Toporia\Framework\Realtime\Brokers\Kafka\TopicStrategy\TopicStrategyFactory;

/**
 * Kafka Broker
 *
 * Apache Kafka broker for high-throughput, persistent realtime communication.
 * Enables horizontal scaling with message replay and history support.
 *
 * Performance:
 * - Latency: ~5ms (network dependent)
 * - Throughput: 1M+ messages/sec
 * - Persistence: Durable messages with configurable retention
 * - Scalability: Partition-based horizontal scaling
 * - Replay: Message history and replay support
 *
 * Use Cases:
 * - High-throughput realtime systems
 * - Multi-server deployments with message persistence
 * - Event sourcing and audit trails
 * - Microservices architecture
 * - Message replay and recovery
 *
 * Architecture:
 * - Server A publishes to Kafka topic
 * - Kafka distributes to all consumer groups
 * - Each server consumes from its consumer group
 * - Messages are persisted for replay
 *
 * Dependencies:
 * - Requires Kafka PHP client library (e.g., nmred/kafka-php or enqueue/rdkafka)
 * - Kafka server must be running and accessible
 *
 * Configuration:
 * ```php
 * 'kafka' => [
 *     'driver' => 'kafka',
 *     'brokers' => ['localhost:9092'],
 *     'topic_prefix' => 'realtime',
 *     'consumer_group' => 'realtime-servers',
 *     'producer_config' => [...],
 *     'consumer_config' => [...],
 * ]
 * ```
 *
 * SOLID Principles:
 * - Single Responsibility: Only manages Kafka broker communication
 * - Open/Closed: Extensible via custom Kafka client injection
 * - Liskov Substitution: Implements BrokerInterface
 * - Interface Segregation: Minimal, focused interface
 * - Dependency Inversion: Can work with any Kafka client library
 *
 * @package Toporia\Framework\Realtime\Brokers
 */
final class KafkaBroker implements BrokerInterface
{
    /**
     * @var object|null Kafka producer instance (client library dependent)
     */
    private ?object $producer = null;

    /**
     * @var object|null Kafka consumer instance (client library dependent)
     */
    private ?object $consumer = null;

    /**
     * @var array<string, callable> Channel subscriptions
     */
    private array $subscriptions = [];

    /**
     * @var bool Connection status
     */
    private bool $connected = false;

    /**
     * @var array Kafka broker configuration
     */
    private array $config;

    /**
     * @var string Topic prefix for all channels (legacy, kept for backward compatibility)
     */
    private string $topicPrefix;

    /**
     * @var string Consumer group ID
     */
    private string $consumerGroup;

    /**
     * @var array<string> Kafka broker addresses
     */
    private array $brokers;

    /**
     * @var TopicStrategyInterface Topic strategy for mapping channels to topics
     */
    private TopicStrategyInterface $topicStrategy;

    /**
     * @var bool Whether to use manual commit (more reliable)
     */
    private bool $manualCommit;

    /**
     * @var array<string, int> Channel → partition mapping cache
     */
    private array $partitionCache = [];

    /**
     * @var string Preferred Kafka client (auto|rdkafka|php)
     */
    private string $clientPreference = 'auto';

    /**
     * @var bool Whether consumer loop is running
     */
    private bool $consuming = false;

    /**
     * @var int|null Consumer process ID (for graceful shutdown)
     */
    private ?int $consumerPid = null;

    /**
     * @var array<string, \RdKafka\ProducerTopic> Cached topic instances (rdkafka only)
     * Performance: O(1) topic lookup instead of creating new topic each time
     */
    private array $topicCache = [];

    /**
     * @var array<array> Message buffer for batching (rdkafka only)
     * Accumulates messages before sending to reduce network round-trips
     */
    private array $messageBuffer = [];

    /**
     * @var int Maximum buffer size before flush
     */
    private int $bufferSize = 100;

    /**
     * @var int Last flush timestamp (for periodic flush)
     */
    private int $lastFlushTime = 0;

    /**
     * @var int Flush interval in milliseconds
     */
    private int $flushInterval = 100; // 100ms

    /**
     * @param array $config Kafka configuration
     * @param RealtimeManager|null $manager Realtime manager instance
     */
    public function __construct(
        array $config = [],
        private readonly ?RealtimeManager $manager = null
    ) {
        $this->config = $config;

        // Ensure brokers is always an array
        $brokers = $config['brokers'] ?? ['localhost:9092'];
        $this->brokers = is_array($brokers) ? $brokers : [$brokers];

        // Validate brokers not empty
        if (empty($this->brokers) || empty($this->brokers[0])) {
            throw new \InvalidArgumentException('Kafka brokers configuration is required. Set KAFKA_BROKERS environment variable or configure in config/realtime.php');
        }

        $this->topicPrefix = $config['topic_prefix'] ?? 'realtime';
        $this->consumerGroup = $config['consumer_group'] ?? 'realtime-servers';
        $this->manualCommit = (bool) ($config['manual_commit'] ?? false);
        $this->clientPreference = strtolower((string) ($config['client'] ?? 'auto'));

        // Initialize topic strategy
        $this->topicStrategy = TopicStrategyFactory::create($config);

        // Initialize Kafka client
        $this->initialize();
    }

    /**
     * Initialize Kafka producer and consumer.
     *
     * Supports multiple Kafka client libraries:
     * - nmred/kafka-php (pure PHP)
     * - enqueue/rdkafka (librdkafka C extension)
     *
     * @return void
     * @throws \RuntimeException If Kafka client library not found
     */
    private function initialize(): void
    {
        $client = $this->selectClient();

        if ($client === 'rdkafka') {
            $this->initializeRdKafka();
        } elseif ($client === 'php') {
            $this->initializeKafkaPhp();
        } else {
            throw new \RuntimeException('No supported Kafka client available.');
        }

        $this->connected = true;
    }

    /**
     * Determine which Kafka client to use (rdkafka or nmred/php).
     *
     * @return string Client name ('rdkafka' or 'php')
     * @throws \RuntimeException If no Kafka client is available
     */
    private function selectClient(): string
    {
        $rdkafkaAvailable = extension_loaded('rdkafka') && class_exists(\RdKafka\Producer::class);
        $phpClientAvailable = class_exists(\Kafka\Producer::class);

        // Runtime check: Ensure at least one Kafka client is available
        if (!$rdkafkaAvailable && !$phpClientAvailable) {
            throw new \RuntimeException(
                "No Kafka client library found. Please install one of:\n" .
                "  Option 1 (Recommended): Install rdkafka extension + enqueue/rdkafka\n" .
                "    - sudo apt-get install librdkafka-dev\n" .
                "    - sudo pecl install rdkafka\n" .
                "    - composer require enqueue/rdkafka\n" .
                "  Option 2: nmred/kafka-php is already in composer.json\n" .
                "    - composer install (should already be installed)\n" .
                "  See EXTENSION_SETUP.md for detailed instructions."
            );
        }

        $client = match ($this->clientPreference) {
            'rdkafka' => $rdkafkaAvailable ? 'rdkafka' : ($phpClientAvailable ? 'php' : ''),
            'php', 'kafka-php', 'nmred' => $phpClientAvailable ? 'php' : ($rdkafkaAvailable ? 'rdkafka' : ''),
            default => $rdkafkaAvailable ? 'rdkafka' : ($phpClientAvailable ? 'php' : ''),
        };

        // Log which client is being used
        if ($client === 'rdkafka') {
            error_log('[Kafka] Using rdkafka extension (high performance)');
        } elseif ($client === 'php') {
            error_log('[Kafka] Using nmred/kafka-php (pure PHP)');
        }

        return $client;
    }

    /**
     * Initialize using enqueue/rdkafka (librdkafka).
     *
     * @return void
     */
    private function initializeRdKafka(): void
    {
        $producerConfig = new \RdKafka\Conf();
        $consumerConfig = new \RdKafka\Conf();

        // Apply custom producer config
        $producerConfigArray = $this->sanitizeKafkaConfig($this->config['producer_config'] ?? []);
        foreach ($producerConfigArray as $key => $value) {
            $producerConfig->set($key, (string) $value);
        }

        // Apply custom consumer config
        $consumerConfigArray = $this->sanitizeKafkaConfig($this->config['consumer_config'] ?? []);
        foreach ($consumerConfigArray as $key => $value) {
            $consumerConfig->set($key, (string) $value);
        }

        // Set consumer group
        $consumerConfig->set('group.id', $this->consumerGroup);

        // Manual commit for better reliability
        $consumerConfig->set('enable.auto.commit', $this->manualCommit ? 'false' : 'true');
        $consumerConfig->set('auto.offset.reset', 'earliest');

        // Build broker list once (validated in constructor)
        $brokerList = implode(',', $this->brokers);

        // Create producer
        $producer = new \RdKafka\Producer($producerConfig);
        $producer->addBrokers($brokerList);
        $this->producer = $producer;

        // Ensure consumer config knows how to reach the cluster
        $consumerConfig->set('bootstrap.servers', $brokerList);
        $consumerConfig->set('metadata.broker.list', $brokerList);

        // Create consumer (will be initialized when subscribing)
        $this->consumer = new \RdKafka\KafkaConsumer($consumerConfig);
    }

    /**
     * Initialize using nmred/kafka-php.
     *
     * @return void
     */
    private function initializeKafkaPhp(): void
    {
        if (!class_exists(\Kafka\ProducerConfig::class)) {
            throw new \RuntimeException('nmred/kafka-php library not found');
        }

        // Validate brokers format
        $brokerList = implode(',', array_filter($this->brokers, fn($b) => !empty($b)));

        if (empty($brokerList)) {
            throw new \InvalidArgumentException(
                'Kafka brokers list is empty. ' .
                    'Configure KAFKA_BROKERS environment variable (e.g., KAFKA_BROKERS=localhost:9092) ' .
                    'or set in config/realtime.php'
            );
        }

        // Producer config (singleton - clear first to avoid stale config)
        /** @var \Kafka\ProducerConfig $producerConfig */
        $producerConfig = \Kafka\ProducerConfig::getInstance();
        $producerConfig->clear(); // Clear any existing config

        // Set metadata broker list first (required)
        try {
            $producerConfig->setMetadataBrokerList($brokerList);
        } catch (\Kafka\Exception\Config $e) {
            throw new \InvalidArgumentException(
                "Invalid Kafka broker configuration: {$e->getMessage()}. " .
                    "Brokers: {$brokerList}. " .
                    "Format should be: host:port,host:port (e.g., localhost:9092)"
            );
        }

        // Verify it was set
        $actualBrokerList = $producerConfig->getMetadataBrokerList();
        if (empty($actualBrokerList)) {
            throw new \RuntimeException(
                "Failed to set Kafka metadata broker list. " .
                    "Expected: {$brokerList}, Got: " . var_export($actualBrokerList, true)
            );
        }

        // Apply producer config with performance optimizations
        $producerConfigArray = $this->sanitizeKafkaConfig($this->config['producer_config'] ?? []);

        // Set default performance optimizations if not specified
        if (!isset($producerConfigArray['compression.type'])) {
            $producerConfigArray['compression.type'] = 'gzip'; // Safe default compression
        }
        if (!isset($producerConfigArray['batch.size'])) {
            $producerConfigArray['batch.size'] = '16384'; // 16KB batch
        }
        if (!isset($producerConfigArray['linger.ms'])) {
            $producerConfigArray['linger.ms'] = '10'; // Wait 10ms for batch
        }

        // Apply buffer size from config
        $this->bufferSize = (int) ($this->config['buffer_size'] ?? 100);
        $this->flushInterval = (int) ($this->config['flush_interval_ms'] ?? 100);

        foreach ($producerConfigArray as $key => $value) {
            try {
                $producerConfig->set($key, $value);
            } catch (\Throwable $e) {
                // Ignore invalid config keys
                error_log("Warning: Invalid producer config key '{$key}': {$e->getMessage()}");
            }
        }

        // Producer will be created lazily when needed (on first publish)
        // This avoids connection errors if Kafka is not running
        // Consumer will be created when subscribing
        // (nmred/kafka-php uses separate consumer instances)
    }

    /**
     * Get Kafka topic name for a channel.
     *
     * Uses topic strategy for flexible topic mapping.
     *
     * @param string $channel Channel name
     * @return string Topic name
     */
    private function getTopicName(string $channel): string
    {
        return $this->topicStrategy->getTopicName($channel);
    }

    /**
     * Get partition number for a channel.
     *
     * Uses topic strategy for consistent partitioning.
     *
     * @param string $channel Channel name
     * @param string $topicName Topic name
     * @return int Partition number
     */
    private function getPartition(string $channel, string $topicName): int
    {
        // Cache partition calculation
        $cacheKey = "{$topicName}:{$channel}";
        if (isset($this->partitionCache[$cacheKey])) {
            return $this->partitionCache[$cacheKey];
        }

        // Get partition count for topic (default to 10 if not configured)
        $partitionCount = 10; // Default, can be configured per topic
        if ($this->topicStrategy instanceof \Toporia\Framework\Realtime\Brokers\Kafka\TopicStrategy\GroupedTopicStrategy) {
            $partitionCount = $this->topicStrategy->getPartitionCount($channel);
        }

        $partition = $this->topicStrategy->getPartition($channel, $partitionCount);
        $this->partitionCache[$cacheKey] = $partition;

        return $partition;
    }

    /**
     * Get message key for a channel.
     *
     * Used for partitioning and message ordering.
     *
     * @param string $channel Channel name
     * @return string|null Message key
     */
    private function getMessageKey(string $channel): ?string
    {
        return $this->topicStrategy->getMessageKey($channel);
    }

    /**
     * {@inheritdoc}
     *
     * Performance Optimizations:
     * - Topic caching (rdkafka): Reuse topic instances
     * - Message batching (rdkafka): Accumulate messages before sending
     * - Lazy producer initialization: Create only when needed
     * - Async flush: Batch flush instead of per-message
     *
     * @param string $channel Channel name
     * @param MessageInterface $message Message to publish
     * @return void
     */
    public function publish(string $channel, MessageInterface $message): void
    {
        if (!$this->connected) {
            throw new \RuntimeException('Kafka broker not connected');
        }

        $topicName = $this->getTopicName($channel);
        $partition = $this->getPartition($channel, $topicName);
        $key = $this->getMessageKey($channel);
        $payload = $message->toJson();

        // Debug logging
        error_log("Kafka publish: channel={$channel}, topic={$topicName}, partition={$partition}, key=" . ($key ?? 'null'));

        // Set error handler to suppress precision warnings for large Kafka offsets/timestamps
        $originalErrorHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Suppress precision loss warnings from Kafka library (handled internally)
            if (str_contains($errstr, 'Implicit conversion') || str_contains($errstr, 'loses precision')) {
                return true; // Suppress the error
            }
            return false; // Let other errors through
        }, E_WARNING | E_NOTICE | E_DEPRECATED);

        try {
            // Publish to Kafka topic
            if ($this->producer instanceof \RdKafka\Producer) {
                // Using enqueue/rdkafka - optimized with batching and topic caching
                $this->publishRdKafka($topicName, $partition, $key, $payload);
            } elseif (class_exists(\Kafka\Producer::class)) {
                // Using nmred/kafka-php - lazy initialization
                if ($this->producer === null) {
                    /** @var \Kafka\Producer $producer */
                    $this->producer = new \Kafka\Producer();
                }

                /** @var \Kafka\Producer $producer */
                $producer = $this->producer;

                // Suppress precision warnings during send (Kafka library handles large values internally)
                @$producer->send([
                    [
                        'topic' => $topicName,
                        'value' => $payload,
                        'partition' => $partition,
                        'key' => $key,
                    ]
                ]);
            } else {
                throw new \RuntimeException('Kafka producer not initialized');
            }
        } catch (\TypeError $e) {
            // Handle precision loss errors from Kafka library
            if (!str_contains($e->getMessage(), 'Implicit conversion') && !str_contains($e->getMessage(), 'loses precision')) {
                throw $e; // Re-throw other TypeErrors
            }
        } finally {
            // Restore original error handler
            if ($originalErrorHandler !== null) {
                restore_error_handler();
            }
        }
    }

    /**
     * Publish using rdkafka with performance optimizations.
     *
     * Optimizations:
     * - Topic caching: Reuse topic instances (O(1) lookup)
     * - Message batching: Accumulate messages before flush
     * - Periodic flush: Flush based on time or buffer size
     *
     * @param string $topicName Topic name
     * @param string $payload Message payload
     * @return void
     */
    private function publishRdKafka(string $topicName, int $partition, ?string $key, string $payload): void
    {
        /** @var \RdKafka\Producer $producer */
        $producer = $this->producer;

        // Get or create cached topic (performance: O(1) lookup)
        if (!isset($this->topicCache[$topicName])) {
            $this->topicCache[$topicName] = $producer->newTopic($topicName);
        }
        $topic = $this->topicCache[$topicName];

        // Add to buffer for batching
        $this->messageBuffer[] = [
            'topic' => $topic,
            'partition' => $partition,
            'key' => $key,
            'payload' => $payload,
            'timestamp' => microtime(true) * 1000 // milliseconds
        ];

        // Flush if buffer is full (batch optimization)
        if (count($this->messageBuffer) >= $this->bufferSize) {
            $this->flushRdKafka($producer);
            return;
        }

        // Periodic flush based on time (even if buffer not full)
        $now = (int) (microtime(true) * 1000);
        if ($now - $this->lastFlushTime >= $this->flushInterval) {
            $this->flushRdKafka($producer);
        }
    }

    /**
     * Flush buffered messages to Kafka (rdkafka).
     *
     * Performance: Batch send reduces network round-trips.
     *
     * @param \RdKafka\Producer $producer Producer instance
     * @return void
     */
    private function flushRdKafka(\RdKafka\Producer $producer): void
    {
        if (empty($this->messageBuffer)) {
            return;
        }

        // Send all buffered messages
        foreach ($this->messageBuffer as $item) {
            /** @var \RdKafka\ProducerTopic $topic */
            $topic = $item['topic'];
            $partition = $item['partition'] ?? RD_KAFKA_PARTITION_UA;
            $key = $item['key'] ?? null;
            $payload = $item['payload'];

            // Use partition and key for better distribution
            // producev() signature: producev(partition, msgflags, payload, key, headers)
            if ($key !== null && method_exists($topic, 'producev')) {
                $topic->producev($partition, 0, $payload, $key);
            } else {
                // Fallback: use produce() with partition
                // Note: key won't be used, but partition will help distribution
                $topic->produce($partition, 0, $payload);
            }
        }

        // Flush producer (send all buffered messages)
        // Use poll() to trigger delivery reports and flush
        $producer->poll(0);

        // Force flush if supported (rdkafka >= 1.0.0)
        if (method_exists($producer, 'flush')) {
            $producer->flush(1000); // 1 second timeout
        } else {
            // Fallback: poll multiple times to ensure flush
            for ($i = 0; $i < 10; $i++) {
                $producer->poll(0);
            }
        }

        // Clear buffer
        $this->messageBuffer = [];
        $this->lastFlushTime = (int) (microtime(true) * 1000);
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(string $channel, callable $callback): void
    {
        if (!$this->connected) {
            throw new \RuntimeException('Kafka broker not connected');
        }

        $topicName = $this->getTopicName($channel);

        // Debug logging
        error_log("Kafka subscribe: channel={$channel}, topic={$topicName}");

        // Store callback with channel mapping for later use
        if (!isset($this->subscriptions[$topicName])) {
            $this->subscriptions[$topicName] = [];
        }
        $this->subscriptions[$topicName][$channel] = $callback;

        // Subscribe to topic (consumer will be started separately via command)
        // This method just registers the subscription
    }

    /**
     * Start consuming messages from subscribed topics.
     *
     * This method is called by the Kafka consumer command.
     * It runs in a loop, consuming messages and invoking callbacks.
     *
     * Performance Optimizations:
     * - Batch processing for high throughput
     * - Non-blocking poll with timeout
     * - Graceful shutdown support
     * - Error handling and retry logic
     *
     * @param int $timeoutMs Poll timeout in milliseconds
     * @param int $batchSize Maximum messages per batch
     * @return void
     */
    public function consume(int $timeoutMs = 1000, int $batchSize = 100): void
    {
        if (empty($this->subscriptions)) {
            return; // No subscriptions
        }

        $this->consuming = true;
        $topics = array_keys($this->subscriptions);

        // Debug logging
        error_log("Kafka consume: topics=" . implode(',', $topics) . ", subscriptions=" . json_encode(array_keys($this->subscriptions)));

        if ($this->consumer instanceof \RdKafka\KafkaConsumer) {
            // Using enqueue/rdkafka
            $this->consumeRdKafka($topics, $timeoutMs, $batchSize);
        } elseif (class_exists(\Kafka\ConsumerConfig::class)) {
            // Using nmred/kafka-php
            $this->consumeKafkaPhp($topics, $timeoutMs, $batchSize);
        } else {
            throw new \RuntimeException('Kafka consumer not initialized. Please install a Kafka client library.');
        }
    }

    /**
     * Consume using enqueue/rdkafka.
     *
     * @param array<string> $topics Topic names
     * @param int $timeoutMs Poll timeout
     * @param int $batchSize Batch size
     * @return void
     */
    private function consumeRdKafka(array $topics, int $timeoutMs, int $batchSize): void
    {
        /** @var \RdKafka\KafkaConsumer $consumer */
        $consumer = $this->consumer;

        // Subscribe to topics
        error_log("Kafka consumer subscribing to topics: " . implode(', ', $topics));
        try {
            $consumer->subscribe($topics);
            error_log("Kafka consumer subscribed successfully");
        } catch (\Throwable $e) {
            error_log("Kafka consumer subscribe error: {$e->getMessage()}");
            throw $e;
        }

        $processed = 0;
        $batch = [];

        // Set error handler to suppress precision warnings for large Kafka offsets
        $originalErrorHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$originalErrorHandler) {
            // Suppress precision loss warnings for Kafka offsets (they're handled internally)
            if (str_contains($errstr, 'Implicit conversion') || str_contains($errstr, 'loses precision')) {
                return true; // Suppress the error
            }
            // Call original error handler for other errors
            if ($originalErrorHandler) {
                return $originalErrorHandler($errno, $errstr, $errfile, $errline);
            }
            return false;
        }, E_WARNING | E_NOTICE);

        try {
            $pollCount = 0;
            while ($this->consuming) {
                // Poll for messages (non-blocking with timeout)
                $message = @$consumer->consume($timeoutMs);

                // Log every 10 polls to show consumer is alive
                $pollCount++;
                if ($pollCount % 10 === 0) {
                    error_log("Kafka consumer polling... (poll #{$pollCount})");
                }

                if ($message === null) {
                    // Log timeout every 100 polls to show consumer is alive
                    if ($pollCount % 100 === 0) {
                        error_log("Kafka consumer: No messages (timeout), poll #{$pollCount}");
                    }
                    continue; // Timeout, no message
                }

                // Handle errors
                switch ($message->err) {
                    case RD_KAFKA_RESP_ERR_NO_ERROR:
                        // Valid message
                        $topicName = @$message->topic_name ?? 'unknown';
                        error_log("Kafka received message: topic={$topicName}, offset=" . (@$message->offset ?? 'unknown'));
                        $batch[] = $message;

                        // Process batch when full
                        if (count($batch) >= $batchSize) {
                            $this->processBatch($batch);
                            $batch = [];
                        }
                        break;

                    case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                        // End of partition (normal)
                        error_log("Kafka consumer: End of partition (EOF) - no more messages in partition");
                        break;

                    case RD_KAFKA_RESP_ERR__TIMED_OUT:
                        // Timeout (normal, continue)
                        // Already logged above
                        break;

                    default:
                        // Error
                        error_log("Kafka consumer error: {$message->errstr()} (code: {$message->err})");
                        break;
                }

                // Process remaining batch periodically (every 10 messages or every 100ms)
                // This ensures messages are processed even if batch not full
                static $lastBatchFlushTime = 0;
                $now = (int) (microtime(true) * 1000);
                $shouldFlush = count($batch) > 0 && (
                    $processed % 10 === 0 || // Every 10 messages
                    ($now - $lastBatchFlushTime) >= 100 // Or every 100ms
                );

                if ($shouldFlush) {
                    $this->processBatch($batch);
                    $batch = [];
                    $lastBatchFlushTime = $now;
                }

                $processed++;
            }
        } finally {
            // Restore original error handler
            if ($originalErrorHandler !== null) {
                restore_error_handler();
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $this->processBatch($batch);
        }
    }

    /**
     * Consume using nmred/kafka-php.
     *
     * @param array<string> $topics Topic names
     * @param int $timeoutMs Poll timeout
     * @param int $batchSize Batch size
     * @return void
     */
    private function consumeKafkaPhp(array $topics, int $timeoutMs, int $batchSize): void
    {
        if (!class_exists(\Kafka\ConsumerConfig::class)) {
            throw new \RuntimeException('nmred/kafka-php library not found');
        }

        // Create consumer config
        /** @var \Kafka\ConsumerConfig $consumerConfig */
        $consumerConfig = \Kafka\ConsumerConfig::getInstance();
        $consumerConfig->clear(); // Clear any existing config

        $brokerList = implode(',', $this->brokers);

        try {
            $consumerConfig->setMetadataBrokerList($brokerList);
        } catch (\Kafka\Exception\Config $e) {
            throw new \InvalidArgumentException(
                "Invalid Kafka broker configuration: {$e->getMessage()}. " .
                    "Brokers: {$brokerList}. " .
                    "Make sure Kafka server is running and brokers are accessible."
            );
        }

        $consumerConfig->setGroupId($this->consumerGroup);
        $consumerConfig->setTopics($topics);
        $consumerConfig->setOffsetReset('earliest'); // Always start from earliest for testing
        $consumerConfig->setMaxBytes(1024 * 1024); // 1MB

        // Log consumer config for debugging
        error_log("Kafka consumer config: groupId={$this->consumerGroup}, topics=" . implode(',', $topics) . ", offsetReset=earliest");

        // Verify config was set
        $actualBrokerList = $consumerConfig->getMetadataBrokerList();
        if (empty($actualBrokerList)) {
            throw new \RuntimeException(
                "Failed to set Kafka consumer metadata broker list. " .
                    "Expected: {$brokerList}, Got: " . var_export($actualBrokerList, true) . ". " .
                    "Make sure Kafka server is running at: {$brokerList}"
            );
        }

        /** @var \Kafka\Consumer $consumer */
        $consumer = new \Kafka\Consumer();

        // nmred/kafka-php uses event-driven pattern with start() and callback
        // Note: This is blocking and runs until stopConsuming() is called
        // Wrap in try-catch to provide better error messages
        try {
            $batch = [];
            error_log("Kafka consumer (nmred/kafka-php) starting, subscribed topics: " . implode(', ', $topics));
            error_log("Kafka consumer subscriptions: " . json_encode(array_keys($this->subscriptions)));
            // Log subscriptions structure (callbacks can't be serialized, so just log structure)
            $subscriptionsInfo = [];
            foreach ($this->subscriptions as $topic => $subs) {
                if (is_array($subs)) {
                    $subscriptionsInfo[$topic] = array_keys($subs);
                } else {
                    $subscriptionsInfo[$topic] = is_callable($subs) ? 'callable' : gettype($subs);
                }
            }
            error_log("Kafka consumer subscriptions structure: " . json_encode($subscriptionsInfo, JSON_PRETTY_PRINT));

            $messageCount = 0;
            $consumer->start(function ($topic, $partition, $message) use (&$batch, $batchSize, $topics, &$messageCount) {
                $messageCount++;
                error_log("Kafka consumer callback invoked #{$messageCount}: topic={$topic}, partition={$partition}");
                if (!$this->consuming) {
                    error_log("Kafka consumer: stopConsuming() called, returning false");
                    return false; // Stop consuming
                }

                try {
                    error_log("Kafka consumer (nmred/kafka-php) received message: topic={$topic}, partition={$partition}");

                    // Check if we have subscription for this topic
                    $subscriptions = $this->subscriptions[$topic] ?? null;
                    if (!$subscriptions) {
                        error_log("Kafka consumer: No subscription found for topic '{$topic}'. Available topics: " . implode(', ', array_keys($this->subscriptions)));
                        return true; // Continue but skip this message
                    }

                    // Decode message value
                    $payload = $message['value'] ?? '';
                    if (empty($payload)) {
                        error_log("Kafka consumer: Empty payload for topic '{$topic}'");
                        return true;
                    }

                    error_log("Kafka consumer: Processing message from topic '{$topic}', payload length: " . strlen($payload));

                    // Create message object
                    $msg = Message::fromJson($payload);

                    // Handle array of callbacks per channel (new format)
                    if (is_array($subscriptions)) {
                        // For grouped topics, extract channel from message key or use topic name
                        // IMPORTANT: For 'orders.events' topic, the channel should be 'orders.events' itself
                        $channel = $message['key'] ?? $topic;

                        // If no key, try to find callback by topic name first
                        if (!isset($subscriptions[$channel])) {
                            // Try topic name as channel
                            $channel = $topic;
                        }

                        $callback = $subscriptions[$channel] ?? null;

                        if ($callback) {
                            error_log("Kafka consumer: Invoking callback for channel '{$channel}' on topic '{$topic}'");
                            $callback($msg);
                        } else {
                            // Fallback: try all callbacks (for backward compatibility)
                            error_log("Kafka consumer: No callback for channel '{$channel}', trying all callbacks. Available channels: " . implode(', ', array_keys($subscriptions)));
                            foreach ($subscriptions as $ch => $cb) {
                                if (is_callable($cb)) {
                                    error_log("Kafka consumer: Trying callback for channel '{$ch}'");
                                    $cb($msg);
                                    break; // Use first available callback
                                }
                            }
                        }
                    } elseif (is_callable($subscriptions)) {
                        // Old format: single callback
                        error_log("Kafka consumer: Invoking single callback for topic '{$topic}'");
                        $subscriptions($msg);
                    } else {
                        error_log("Kafka consumer: Subscriptions is neither array nor callable. Type: " . gettype($subscriptions));
                    }

                    $batch[] = ['topic' => $topic, 'message' => $msg];

                    // Process batch when full
                    if (count($batch) >= $batchSize) {
                        error_log("Kafka consumer: Batch full (" . count($batch) . " messages), resetting batch");
                        $batch = []; // Reset batch (messages already processed via callback)
                    }
                } catch (\Throwable $e) {
                    error_log("Error processing Kafka message on {$topic}: {$e->getMessage()}");
                    error_log("Stack trace: " . $e->getTraceAsString());
                }

                return true; // Continue consuming
            }, true); // isBlock = true (blocking mode)
        } catch (\Kafka\Exception $e) {
            throw new \RuntimeException(
                "Kafka consumer error: {$e->getMessage()}. " .
                    "Make sure Kafka server is running at: " . implode(',', $this->brokers) . " " .
                    "and topics exist: " . implode(',', $topics)
            );
        }
    }

    /**
     * Process batch of messages (rdkafka format).
     *
     * @param array<\RdKafka\Message> $batch Message batch
     * @return void
     */
    private function processBatch(array $batch): void
    {
        /** @var \RdKafka\KafkaConsumer|null $consumer */
        $consumer = $this->consumer instanceof \RdKafka\KafkaConsumer ? $this->consumer : null;

        foreach ($batch as $message) {
            try {
                // Access message properties safely (offset/partition may be very large floats)
                // Suppress precision warnings for large Kafka offsets
                $topicName = @$message->topic_name ?? '';
                $payload = @$message->payload ?? '';
                $key = @$message->key ?? null;

                if (empty($topicName) || empty($payload)) {
                    error_log("Invalid message: missing topic or payload");
                    continue;
                }

                $this->processMessage($topicName, $payload, $key);

                // Manual commit after successful processing
                if ($this->manualCommit && $consumer) {
                    try {
                        // Suppress precision loss warnings for large offsets
                        // Kafka offsets can be very large (64-bit), but PHP int is platform-dependent
                        // The commit() method will handle the conversion internally
                        @$consumer->commit($message);
                    } catch (\RdKafka\Exception $e) {
                        // Log but don't fail the batch - offset commit is best-effort
                        error_log("Failed to commit offset: {$e->getMessage()}");
                    } catch (\TypeError $e) {
                        // Handle precision loss warning for very large offsets
                        // This can happen with 64-bit offsets on 32-bit PHP or with very large offset values
                        // Try to commit asynchronously or skip commit for this message
                        // The consumer will still process messages correctly
                    } catch (\Throwable $e) {
                        // Catch any other errors during commit (including precision warnings)
                        if (!str_contains($e->getMessage(), 'Implicit conversion') && !str_contains($e->getMessage(), 'loses precision')) {
                            error_log("Error committing offset: {$e->getMessage()}");
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Don't commit on error - message will be retried
                // Suppress precision warnings from message property access
                error_log("Error processing message: {$e->getMessage()}");
                throw $e; // Re-throw to trigger DLQ
            }
        }
    }

    /**
     * Process batch of messages (kafka-php format).
     *
     * @param array<array> $batch Message batch
     * @return void
     */
    private function processBatchKafkaPhp(array $batch): void
    {
        foreach ($batch as $message) {
            $topicName = $message['topic_name'];
            $payload = $message['message']['value'];
            $this->processMessage($topicName, $payload);
        }
    }

    /**
     * Process a single message.
     *
     * Supports both old format (single callback) and new format (array of callbacks per channel).
     *
     * @param string $topicName Topic name
     * @param string $payload Message payload
     * @param string|null $messageKey Message key (channel name if using grouped strategy)
     * @return void
     */
    private function processMessage(string $topicName, string $payload, ?string $messageKey = null): void
    {
        $subscriptions = $this->subscriptions[$topicName] ?? null;

        if (!$subscriptions) {
            return; // No subscription for this topic
        }

        try {
            // Decode message
            $message = Message::fromJson($payload);

            // Determine channel from message key or topic name
            $channel = $messageKey ?? $this->extractChannelFromTopic($topicName);

            // Handle new format: array of callbacks per channel
            if (is_array($subscriptions)) {
                $callback = $subscriptions[$channel] ?? null;
                if ($callback) {
                    $callback($message);
                } else {
                    // Fallback: try all callbacks (for backward compatibility)
                    foreach ($subscriptions as $cb) {
                        if (is_callable($cb)) {
                            $cb($message);
                        }
                    }
                }
            } elseif (is_callable($subscriptions)) {
                // Old format: single callback (backward compatibility)
                $subscriptions($message);
            }
        } catch (\Throwable $e) {
            error_log("Kafka message processing error on {$topicName}: {$e->getMessage()}");
            throw $e; // Re-throw for batch processing
        }
    }

    /**
     * Extract channel name from topic name.
     *
     * Used for backward compatibility with old topic naming.
     *
     * @param string $topicName Topic name
     * @return string Channel name
     */
    private function extractChannelFromTopic(string $topicName): string
    {
        // Remove topic prefix
        if (str_starts_with($topicName, $this->topicPrefix . '_')) {
            return str_replace($this->topicPrefix . '_', '', $topicName);
        }

        // For grouped topics, try to extract from message or use topic name
        return $topicName;
    }

    /**
     * Remove invalid/sentinel config values before passing to Kafka clients.
     *
     * @param array $config
     * @return array
     */
    private function sanitizeKafkaConfig(array $config): array
    {
        foreach (['compression.type', 'compression.codec'] as $compressionKey) {
            if (!isset($config[$compressionKey])) {
                continue;
            }

            $value = (string) $config[$compressionKey];
            if ($value === '' || strcasecmp($value, 'none') === 0 || strcasecmp($value, 'off') === 0) {
                unset($config[$compressionKey]);
            }
        }

        return $config;
    }

    /**
     * Stop consuming messages.
     *
     * @return void
     */
    public function stopConsuming(): void
    {
        $this->consuming = false;
    }

    /**
     * {@inheritdoc}
     */
    public function unsubscribe(string $channel): void
    {
        $topicName = $this->getTopicName($channel);

        // Handle both old format (single callback) and new format (array of callbacks)
        if (isset($this->subscriptions[$topicName])) {
            if (is_array($this->subscriptions[$topicName])) {
                unset($this->subscriptions[$topicName][$channel]);
                // Remove topic entry if no more channels
                if (empty($this->subscriptions[$topicName])) {
                    unset($this->subscriptions[$topicName]);
                }
            } else {
                // Old format: single callback
                unset($this->subscriptions[$topicName]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriberCount(string $channel): int
    {
        $topicName = $this->getTopicName($channel);
        $subscriptions = $this->subscriptions[$topicName] ?? null;

        if (!$subscriptions) {
            return 0;
        }

        // Handle both formats
        if (is_array($subscriptions)) {
            return isset($subscriptions[$channel]) ? 1 : 0;
        }

        return 1; // Old format: single callback
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        if (!$this->connected) {
            return;
        }

        $this->stopConsuming();

        // Flush any remaining buffered messages before disconnect
        if ($this->producer instanceof \RdKafka\Producer && !empty($this->messageBuffer)) {
            $this->flushRdKafka($this->producer);
        }

        // Close connections
        if ($this->producer instanceof \RdKafka\Producer) {
            // Flush before closing
            if (method_exists($this->producer, 'flush')) {
                $this->producer->flush(5000); // 5 second timeout for final flush
            }
            $this->producer = null;
        }

        if ($this->consumer instanceof \RdKafka\KafkaConsumer) {
            $this->consumer->unsubscribe();
            $this->consumer = null;
        }

        // Clear caches
        $this->topicCache = [];
        $this->messageBuffer = [];

        $this->connected = false;
        $this->subscriptions = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'kafka';
    }

    /**
     * Destructor - ensure clean disconnect.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
