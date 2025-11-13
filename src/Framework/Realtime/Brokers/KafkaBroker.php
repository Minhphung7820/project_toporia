<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Brokers;

use Toporia\Framework\Realtime\Contracts\{BrokerInterface, MessageInterface};
use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\Realtime\Message;

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
     * @var string Topic prefix for all channels
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
        // Try to detect and initialize Kafka client
        // Priority: enqueue/rdkafka > nmred/kafka-php

        if (class_exists(\RdKafka\Producer::class)) {
            // Using enqueue/rdkafka (librdkafka)
            $this->initializeRdKafka();
        } elseif (class_exists(\Kafka\Producer::class)) {
            // Using nmred/kafka-php
            $this->initializeKafkaPhp();
        } else {
            throw new \RuntimeException(
                'Kafka client library not found. ' .
                    'Please install one of: enqueue/rdkafka or nmred/kafka-php. ' .
                    'Example: composer require enqueue/rdkafka'
            );
        }

        $this->connected = true;
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
        $producerConfigArray = $this->config['producer_config'] ?? [];
        foreach ($producerConfigArray as $key => $value) {
            $producerConfig->set($key, (string) $value);
        }

        // Apply custom consumer config
        $consumerConfigArray = $this->config['consumer_config'] ?? [];
        foreach ($consumerConfigArray as $key => $value) {
            $consumerConfig->set($key, (string) $value);
        }

        // Set consumer group
        $consumerConfig->set('group.id', $this->consumerGroup);
        $consumerConfig->set('enable.auto.commit', 'true');
        $consumerConfig->set('auto.offset.reset', 'earliest');

        // Create producer
        $producer = new \RdKafka\Producer($producerConfig);
        $producer->addBrokers(implode(',', $this->brokers));
        $this->producer = $producer;

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
        $producerConfigArray = $this->config['producer_config'] ?? [];

        // Set default performance optimizations if not specified
        if (!isset($producerConfigArray['compression.type'])) {
            $producerConfigArray['compression.type'] = 'snappy'; // Fast compression
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
     * @param string $channel Channel name
     * @return string Topic name
     */
    private function getTopicName(string $channel): string
    {
        // Sanitize channel name for Kafka topic naming
        // Kafka topics: alphanumeric, dots, dashes, underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $channel);
        return "{$this->topicPrefix}_{$sanitized}";
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
        $payload = $message->toJson();

        // Publish to Kafka topic
        if ($this->producer instanceof \RdKafka\Producer) {
            // Using enqueue/rdkafka - optimized with batching and topic caching
            $this->publishRdKafka($topicName, $payload);
        } elseif (class_exists(\Kafka\Producer::class)) {
            // Using nmred/kafka-php - lazy initialization
            if ($this->producer === null) {
                /** @var \Kafka\Producer $producer */
                $this->producer = new \Kafka\Producer();
            }

            /** @var \Kafka\Producer $producer */
            $producer = $this->producer;
            $producer->send([
                [
                    'topic' => $topicName,
                    'value' => $payload,
                    'partition' => 0,
                ]
            ]);
        } else {
            throw new \RuntimeException('Kafka producer not initialized');
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
    private function publishRdKafka(string $topicName, string $payload): void
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
            $topic->produce(RD_KAFKA_PARTITION_UA, 0, $item['payload']);
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

        // Store callback
        $this->subscriptions[$topicName] = $callback;

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
        $consumer->subscribe($topics);

        $processed = 0;
        $batch = [];

        while ($this->consuming) {
            // Poll for messages (non-blocking with timeout)
            $message = $consumer->consume($timeoutMs);

            if ($message === null) {
                continue; // Timeout, no message
            }

            // Handle errors
            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    // Valid message
                    $batch[] = $message;

                    // Process batch when full
                    if (count($batch) >= $batchSize) {
                        $this->processBatch($batch);
                        $batch = [];
                    }
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    // End of partition (normal)
                    break;

                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    // Timeout (normal, continue)
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
        $consumerConfig->setOffsetReset('earliest');
        $consumerConfig->setMaxBytes(1024 * 1024); // 1MB

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
            $consumer->start(function ($topic, $partition, $message) use (&$batch, $batchSize) {
                if (!$this->consuming) {
                    return false; // Stop consuming
                }

                try {
                    // Extract channel name from topic (remove prefix)
                    $channel = str_replace("{$this->topicPrefix}_", '', $topic);

                    $callback = $this->subscriptions[$topic] ?? null;
                    if (!$callback) {
                        return true; // Continue but skip this message
                    }

                    // Decode message value
                    $payload = $message['value'] ?? '';
                    if (empty($payload)) {
                        return true;
                    }

                    // Create message object and invoke callback
                    $msg = Message::fromJson($payload);
                    $callback($msg);

                    $batch[] = ['topic' => $topic, 'message' => $msg];

                    // Process batch when full
                    if (count($batch) >= $batchSize) {
                        $batch = []; // Reset batch (messages already processed via callback)
                    }
                } catch (\Throwable $e) {
                    error_log("Error processing Kafka message on {$topic}: {$e->getMessage()}");
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
        foreach ($batch as $message) {
            $this->processMessage($message->topic_name, $message->payload);
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
     * @param string $topicName Topic name
     * @param string $payload Message payload
     * @return void
     */
    private function processMessage(string $topicName, string $payload): void
    {
        $callback = $this->subscriptions[$topicName] ?? null;

        if (!$callback) {
            return; // No subscription for this topic
        }

        try {
            // Decode message
            $message = Message::fromJson($payload);

            // Invoke callback
            $callback($message);
        } catch (\Throwable $e) {
            error_log("Kafka message processing error on {$topicName}: {$e->getMessage()}");
        }
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
        unset($this->subscriptions[$topicName]);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriberCount(string $channel): int
    {
        // Kafka doesn't provide direct subscriber count
        // This would require querying Kafka's consumer group API
        // For now, return 1 if subscribed, 0 otherwise
        $topicName = $this->getTopicName($channel);
        return isset($this->subscriptions[$topicName]) ? 1 : 0;
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
