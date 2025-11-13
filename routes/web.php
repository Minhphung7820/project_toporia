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
            'POST /api/orders' => 'Create order and publish order.created event',
            'POST /api/orders/{orderId}/ship' => 'Ship order and publish order.shipped event',
            'POST /api/orders/{orderId}/deliver' => 'Deliver order and publish order.delivered event',
        ],
        'example' => [
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
