<?php

declare(strict_types=1);

/**
 * Web Routes
 *
 * Define your application routes here.
 * The $router variable is automatically injected by RouteServiceProvider.
 */

use Toporia\Framework\Routing\Router;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use App\Presentation\Http\Middleware\Authenticate;
use App\Presentation\Http\Controllers\HomeController;
use App\Presentation\Http\Controllers\AuthController;
use App\Presentation\Http\Controllers\ProductsController;
use App\Presentation\Http\Controllers\FileUploadController;
use App\Presentation\Http\Action\Product\CreateProductAction;
use App\Jobs\TestRabbitMQJob;
use Toporia\Framework\Support\Accessors\Log;

/** @var Router $router */

// Public routes
$router->get('/', [HomeController::class, 'index']);
$router->get('/test-rabbitmq', function (Request $request, Response $response) {
    // Dispatch test job
    dispatch(new TestRabbitMQJob('Test message from route!'));

    return $response->json([
        'message' => 'RabbitMQ job dispatched successfully!',
        'queue' => 'default',
        'check_dashboard' => 'http://localhost:15672',
        'credentials' => 'guest / guest',
        'note' => 'Make sure worker is running: php console queue:work'
    ]);
});
$router->get('/login', [AuthController::class, 'showLoginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegisterForm']);
$router->post('/register', [AuthController::class, 'register']);

// Auth routes
$router->post('/logout', [AuthController::class, 'logout']);

// API routes - Authentication
$router->post('/api/login', [AuthController::class, 'login']);
$router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/logout', [AuthController::class, 'logout']);
$router->get('/api/me', [AuthController::class, 'me'])->middleware(['auth:api']);

// Protected routes (require authentication)
$router->get('/dashboard', [HomeController::class, 'dashboard'], [Authenticate::class]);
$router->get('/products/create', [ProductsController::class, 'create'], [Authenticate::class]);
$router->post('/products', [ProductsController::class, 'store'], [Authenticate::class]);
$router->get('/products/{id}', [ProductsController::class, 'show']);

// API routes (ADR pattern)
$router->post('/v2/products', [CreateProductAction::class, '__invoke']);

// Elasticsearch Search API
$router->get('/api/products/search', function (Request $request, Response $response) {
    $search = container('search');
    $query = $request->query('q', '');
    $page = (int) $request->query('page', 1);
    $perPage = (int) $request->query('per_page', 15);
    $minPrice = $request->query('min_price');
    $maxPrice = $request->query('max_price');
    $status = $request->query('status');

    // Build search query
    $searchQuery = $search->query();

    // Full-text search on title and description
    if (!empty($query)) {
        $searchQuery->match('title', $query);
        $searchQuery->match('description', $query);
    }

    // Price range filter
    if ($minPrice !== null || $maxPrice !== null) {
        $range = [];
        if ($minPrice !== null) {
            $range['gte'] = (float) $minPrice;
        }
        if ($maxPrice !== null) {
            $range['lte'] = (float) $maxPrice;
        }
        $searchQuery->range('price', $range);
    }

    // Status filter
    if ($status !== null) {
        $searchQuery->term('status', $status);
    }

    // Default: only show active products
    if ($status === null) {
        $searchQuery->term('status', 'active');
    }

    // Sort by relevance (default) or price
    $sortBy = $request->query('sort', 'relevance');
    if ($sortBy === 'price_asc') {
        $searchQuery->sort('price', 'asc');
    } elseif ($sortBy === 'price_desc') {
        $searchQuery->sort('price', 'desc');
    }

    // Pagination
    $searchQuery->paginate($page, $perPage);

    // Execute search
    $index = \App\Infrastructure\Persistence\Models\ProductModel::searchIndexName();
    $results = $search->search($index, $searchQuery->toArray());

    // Format response
    $hits = $results['hits']['hits'] ?? [];
    $total = $results['hits']['total']['value'] ?? 0;

    return $response->json([
        'success' => true,
        'data' => array_map(fn($hit) => $hit['_source'], $hits),
        'meta' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ],
        'query' => [
            'q' => $query,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'status' => $status,
            'sort' => $sortBy,
        ],
    ]);
});

// File Upload routes
$router->get('/upload', [FileUploadController::class, 'showForm']);
$router->post('/upload/local', [FileUploadController::class, 'uploadLocal']);
$router->post('/upload/s3', [FileUploadController::class, 'uploadToS3']);
$router->get('/upload/list', [FileUploadController::class, 'listFiles']);
$router->get('/upload/download/{filename}', [FileUploadController::class, 'download']);
$router->delete('/upload/{filename}', [FileUploadController::class, 'delete']);

// Kafka Test routes
$router->get('/test-kafka', function (Request $request, Response $response) {
    $realtime = realtime();
    $channel = $request->query('channel', 'test.channel');
    $event = $request->query('event', 'test-event');
    $message = $request->query('message', 'Hello from Kafka producer!');

    // Broadcast message (this will publish to Kafka broker)
    $realtime->broadcast($channel, $event, [
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'server' => gethostname(),
        'request_id' => uniqid('req_', true),
    ]);

    return $response->json([
        'success' => true,
        'message' => 'Message published to Kafka broker',
        'channel' => $channel,
        'event' => $event,
        'data' => [
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ],
        'note' => 'Run consumer: php console test:kafka:consume --channels=' . $channel,
    ]);
});

$router->post('/test-kafka', function (Request $request, Response $response) {
    $realtime = realtime();
    $data = $request->input(); // Get parsed JSON body

    $channel = $data['channel'] ?? 'test.channel';
    $event = $data['event'] ?? 'test-event';
    $messageData = $data['data'] ?? ['message' => 'Hello from Kafka producer!'];

    // Broadcast message (this will publish to Kafka broker)
    $realtime->broadcast($channel, $event, $messageData);

    return $response->json([
        'success' => true,
        'message' => 'Message published to Kafka broker',
        'channel' => $channel,
        'event' => $event,
        'data' => $messageData,
        'note' => 'Run consumer: php console test:kafka:consume --channels=' . $channel,
    ]);
});

// Order Tracking Test Routes (Business Logic)
$router->post('/api/orders', function (Request $request, Response $response) {
    $realtime = realtime();
    $data = $request->input();

    // Simulate order creation (in real app, this would be Order::create())
    $orderId = uniqid('order_', true);
    $order = [
        'order_id' => $orderId,
        'user_id' => $data['user_id'] ?? rand(1, 1000),
        'total' => $data['total'] ?? rand(100, 10000),
        'items' => $data['items'] ?? [
            ['product_id' => 1, 'quantity' => 2, 'price' => 50],
            ['product_id' => 2, 'quantity' => 1, 'price' => 100],
        ],
        'status' => 'created',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    // Publish order event to Kafka (business logic topic)
    $kafkaBroker = $realtime->broker('kafka');

    if ($kafkaBroker) {
        $kafkaBroker->publish('orders.events', \Toporia\Framework\Realtime\Message::event(
            'orders.events',  // Topic (business logic, not realtime channel)
            'order.created',  // Event type
            $order            // Event data
        ));
    }

    return $response->json([
        'success' => true,
        'message' => 'Order created and event published to Kafka',
        'order' => $order,
        'note' => 'Run consumer: php console order:tracking:consume',
    ]);
});

$router->post('/api/orders/{orderId}/ship', function (Request $request, Response $response, string $orderId) {
    $realtime = realtime();
    $data = $request->input();

    // Simulate order shipping
    $trackingNumber = $data['tracking_number'] ?? 'TRACK-' . strtoupper(substr(uniqid(), -10));
    $orderData = [
        'order_id' => $orderId,
        'event' => 'order.shipped',
        'tracking_number' => $trackingNumber,
        'shipped_at' => date('Y-m-d H:i:s'),
        'carrier' => $data['carrier'] ?? 'DHL',
    ];

    // Publish shipping event to Kafka
    $kafkaBroker = $realtime->broker('kafka');

    if ($kafkaBroker) {
        $kafkaBroker->publish('orders.events', \Toporia\Framework\Realtime\Message::event(
            'orders.events',
            'order.shipped',
            $orderData
        ));
    }

    return $response->json([
        'success' => true,
        'message' => 'Order shipped event published to Kafka',
        'order' => $orderData,
        'note' => 'Run consumer: php console order:tracking:consume',
    ]);
});

$router->post('/api/orders/{orderId}/deliver', function (Request $request, Response $response, string $orderId) {
    $realtime = realtime();

    // Simulate order delivery
    $orderData = [
        'order_id' => $orderId,
        'event' => 'order.delivered',
        'delivered_at' => date('Y-m-d H:i:s'),
    ];

    // Publish delivery event to Kafka
    $kafkaBroker = $realtime->broker('kafka');

    if ($kafkaBroker) {
        $kafkaBroker->publish('orders.events', \Toporia\Framework\Realtime\Message::event(
            'orders.events',
            'order.delivered',
            $orderData
        ));
    }

    return $response->json([
        'success' => true,
        'message' => 'Order delivered event published to Kafka',
        'order' => $orderData,
        'note' => 'Run consumer: php console order:tracking:consume',
    ]);
});

$router->get('/api/orders/test', function (Request $request, Response $response) {
    return $response->json([
        'message' => 'Order Tracking Test Endpoints',
        'endpoints' => [
            'GET /api/orders/produce' => 'Quick test - publish order.created event (query params)',
            'POST /api/orders' => 'Create order and publish order.created event',
            'POST /api/orders/{orderId}/ship' => 'Ship order and publish order.shipped event',
            'POST /api/orders/{orderId}/deliver' => 'Deliver order and publish order.delivered event',
        ],
        'example' => [
            'quick_test' => [
                'method' => 'GET',
                'url' => '/api/orders/produce?event=order.created&order_id=123&user_id=456',
            ],
            'create_order' => [
                'method' => 'POST',
                'url' => '/api/orders',
                'body' => [
                    'user_id' => 123,
                    'total' => 500,
                    'items' => [
                        ['product_id' => 1, 'quantity' => 2, 'price' => 50],
                    ],
                ],
            ],
            'ship_order' => [
                'method' => 'POST',
                'url' => '/api/orders/{orderId}/ship',
                'body' => [
                    'tracking_number' => 'TRACK123456',
                    'carrier' => 'DHL',
                ],
            ],
        ],
        'consumer' => 'Run: php console order:tracking:consume',
    ]);
});

// Quick test route - GET để dễ test producer
$router->get('/api/orders/produce', function (Request $request, Response $response) {
    $realtime = realtime();
    // Get parameters from query string
    $event = $request->query('event', 'order.created');
    $orderId = $request->query('order_id', uniqid('order_', true));
    $userId = $request->query('user_id', rand(1, 1000));
    $total = $request->query('total', rand(100, 10000));

    // Build order data based on event type
    $orderData = [
        'order_id' => $orderId,
        'user_id' => (int) $userId,
        'total' => (float) $total,
    ];

    // Add event-specific data
    switch ($event) {
        case 'order.created':
            $orderData['status'] = 'created';
            $orderData['created_at'] = date('Y-m-d H:i:s');
            break;
        case 'order.shipped':
            $orderData['tracking_number'] = $request->query('tracking_number', 'TRACK-' . strtoupper(substr(uniqid(), -10)));
            $orderData['carrier'] = $request->query('carrier', 'DHL');
            $orderData['shipped_at'] = date('Y-m-d H:i:s');
            break;
        case 'order.delivered':
            $orderData['delivered_at'] = date('Y-m-d H:i:s');
            break;
        case 'order.cancelled':
            $orderData['reason'] = $request->query('reason', 'Customer request');
            $orderData['cancelled_at'] = date('Y-m-d H:i:s');
            break;
    }

    // Publish to Kafka topic 'orders.events' (business logic topic)
    $kafkaBroker = $realtime->broker('kafka');

    if (!$kafkaBroker) {
        return $response->json([
            'success' => false,
            'error' => 'Kafka broker not available. Make sure Kafka is configured in config/realtime.php',
        ], 500);
    }

    try {
        // Check if Kafka is actually available before publishing
        // This prevents segmentation faults when Kafka is down
        $kafkaBroker->publish('orders.events', \Toporia\Framework\Realtime\Message::event(
            'orders.events',  // Topic name (business logic, not realtime channel)
            $event,            // Event type (order.created, order.shipped, etc.)
            $orderData         // Event data
        ));

        return $response->json([
            'success' => true,
            'message' => "Order event '{$event}' published to Kafka",
            'topic' => 'orders.events',
            'event' => $event,
            'data' => $orderData,
            'consumer' => 'Run: php console order:tracking:consume',
            'note' => 'Check consumer terminal to see the message being processed',
        ]);
    } catch (\Throwable $e) {
        // Log the error for debugging
        Log::error('Kafka publish failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'event' => $event,
            'order_data' => $orderData,
        ]);

        return $response->json([
            'success' => false,
            'error' => 'Failed to publish to Kafka: ' . $e->getMessage(),
            'message' => 'Kafka may be unavailable. Check if Kafka containers are running: docker ps | grep kafka',
            'hint' => 'Start Kafka: docker start project_topo_zookeeper project_topo_kafka',
        ], 500);
    }
});
