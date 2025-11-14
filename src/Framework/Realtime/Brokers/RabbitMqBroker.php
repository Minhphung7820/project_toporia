<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Brokers;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Toporia\Framework\Realtime\Contracts\BrokerInterface;
use Toporia\Framework\Realtime\Contracts\MessageInterface;
use Toporia\Framework\Realtime\Message;
use Toporia\Framework\Realtime\RealtimeManager;

/**
 * RabbitMQ Broker
 *
 * Durable AMQP broker with topic exchange routing.
 * Optimized for enterprise messaging with guaranteed delivery.
 */
final class RabbitMqBroker implements BrokerInterface
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;
    private string $exchange;
    private string $exchangeType;
    private bool $persistentMessages;
    private bool $connected = false;
    private bool $consuming = false;
    private ?string $queueName = null;
    private bool $consumerInitialized = false;

    /** @var array<string, callable> */
    private array $subscriptions = [];

    /** @var array<string, string> */
    private array $routingMap = [];

    /** @var array<int, string> */
    private array $consumerTags = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config = [],
        private readonly ?RealtimeManager $manager = null
    ) {
        $this->exchange = $config['exchange'] ?? 'realtime';
        $this->exchangeType = $config['exchange_type'] ?? 'topic';
        $this->persistentMessages = (bool) ($config['persistent_messages'] ?? true);

        $this->connect();
    }

    /**
     * Establish AMQP connection & channel.
     */
    private function connect(): void
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = (int) ($this->config['port'] ?? 5672);
        $user = $this->config['user'] ?? 'guest';
        $password = $this->config['password'] ?? 'guest';
        $vhost = $this->config['vhost'] ?? '/';

        $this->connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $this->channel = $this->connection->channel();

        $durable = (bool) ($this->config['exchange_durable'] ?? true);
        $autoDelete = (bool) ($this->config['exchange_auto_delete'] ?? false);

        $this->channel->exchange_declare(
            $this->exchange,
            $this->exchangeType,
            false,
            $durable,
            $autoDelete
        );

        $prefetch = (int) ($this->config['prefetch_count'] ?? 50);
        if ($prefetch > 0) {
            $this->channel->basic_qos(null, $prefetch, null);
        }

        $this->connected = true;
    }

    private function ensureConnection(): void
    {
        if ($this->connected && $this->connection?->isConnected()) {
            return;
        }

        $this->disconnect();
        $this->connect();
    }

    public function publish(string $channel, MessageInterface $message): void
    {
        $this->ensureConnection();

        $routingKey = $this->formatRoutingKey($channel);
        $payload = $message->toJson();

        $msg = new AMQPMessage($payload, [
            'content_type' => 'application/json',
            'delivery_mode' => $this->persistentMessages ? AMQPMessage::DELIVERY_MODE_PERSISTENT : AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
            'timestamp' => time(),
        ]);

        $this->channel?->basic_publish($msg, $this->exchange, $routingKey);
    }

    public function subscribe(string $channel, callable $callback): void
    {
        $this->ensureConnection();

        $routingKey = $this->formatRoutingKey($channel);
        $queue = $this->getQueueName();

        $this->channel?->queue_bind($queue, $this->exchange, $routingKey);
        $this->subscriptions[$channel] = $callback;
        $this->routingMap[$routingKey] = $channel;
    }

    /**
     * Consume messages from RabbitMQ queue.
     */
    public function consume(int $timeoutMs = 1000, int $batchSize = 100): void
    {
        if (empty($this->subscriptions) || !$this->channel) {
            return;
        }

        $this->consuming = true;

        if (!$this->consumerInitialized) {
            $tag = $this->channel->basic_consume(
                $this->getQueueName(),
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) {
                    $this->handleIncomingMessage($message);
                }
            );

            if ($tag) {
                $this->consumerTags[] = $tag;
            }

            $this->consumerInitialized = true;
        }

        $timeoutSeconds = max($timeoutMs, 1000) / 1000;

        try {
            while ($this->consuming && $this->channel->is_consuming()) {
                try {
                    $this->channel->wait(null, false, $timeoutSeconds);
                } catch (AMQPTimeoutException) {
                    // Timeout is used to periodically check $this->consuming
                    continue;
                }
            }
        } finally {
            $this->stopConsuming();
        }
    }

    private function handleIncomingMessage(AMQPMessage $message): void
    {
        $routingKey = $message->getRoutingKey();
        $channelName = $this->routingMap[$routingKey] ?? $routingKey;
        $callback = $this->subscriptions[$channelName] ?? null;

        if (!$callback) {
            $message->ack();
            return;
        }

        try {
            $decoded = Message::fromJson($message->getBody());
            $callback($decoded);
            $message->ack();
        } catch (\Throwable $e) {
            $message->nack(true);
            error_log("RabbitMQ consumer error on {$routingKey}: {$e->getMessage()}");
        }
    }

    public function stopConsuming(): void
    {
        $this->consuming = false;

        if (!$this->channel) {
            return;
        }

        foreach ($this->consumerTags as $tag) {
            try {
                $this->channel->basic_cancel($tag);
            } catch (\Throwable $e) {
                error_log("RabbitMQ cancel error: {$e->getMessage()}");
            }
        }

        $this->consumerTags = [];
        $this->consumerInitialized = false;
    }

    public function unsubscribe(string $channel): void
    {
        if (!isset($this->subscriptions[$channel]) || !$this->channel) {
            return;
        }

        $routingKey = $this->formatRoutingKey($channel);
        $this->channel->queue_unbind($this->getQueueName(), $this->exchange, $routingKey);

        unset($this->subscriptions[$channel], $this->routingMap[$routingKey]);
    }

    public function getSubscriberCount(string $channel): int
    {
        if (!$this->channel || $this->queueName === null) {
            return 0;
        }

        try {
            [$queue,, $consumerCount] = $this->channel->queue_declare($this->queueName, true);
            return (int) $consumerCount;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->connection?->isConnected();
    }

    public function disconnect(): void
    {
        $this->stopConsuming();

        try {
            $this->channel?->close();
        } catch (\Throwable $e) {
            error_log("RabbitMQ channel close error: {$e->getMessage()}");
        }

        try {
            $this->connection?->close();
        } catch (\Throwable $e) {
            error_log("RabbitMQ connection close error: {$e->getMessage()}");
        }

        $this->channel = null;
        $this->connection = null;
        $this->connected = false;
        $this->queueName = null;
        $this->consumerInitialized = false;
    }

    public function getName(): string
    {
        return 'rabbitmq';
    }

    private function getQueueName(): string
    {
        if ($this->queueName !== null) {
            return $this->queueName;
        }

        $durable = (bool) ($this->config['queue_durable'] ?? false);
        $exclusive = (bool) ($this->config['queue_exclusive'] ?? true);
        $autoDelete = (bool) ($this->config['queue_auto_delete'] ?? true);
        $queuePrefix = $this->config['queue_prefix'] ?? 'realtime';
        $queueName = $exclusive ? '' : sprintf('%s.%s.%d', $queuePrefix, gethostname() ?: 'app', getmypid());

        [$queue] = $this->channel->queue_declare($queueName, false, $durable, $exclusive, $autoDelete);
        $this->queueName = $queue;

        return $this->queueName;
    }

    private function formatRoutingKey(string $channel): string
    {
        return str_replace(':', '.', $channel);
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
