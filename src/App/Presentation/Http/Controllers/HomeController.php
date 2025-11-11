<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Domain\Product\ProductModel;
use App\Domain\User\UserModel;
use App\Jobs\SendEmailJob;
use App\Notifications\UserCreatedNotification;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Queue\Contracts\QueueManagerInterface;
use Toporia\Framework\Support\Accessors\URL;

/**
 * Home Controller
 *
 * Demo: Modern approach WITHOUT extending BaseController.
 * Uses method injection + helper functions for maximum flexibility.
 */
final class HomeController extends BaseController
{
    /**
     * Product listing with pagination.
     *
     * Demo: Laravel-style job dispatching with fluent API.
     *
     * Performance:
     * - O(1) job dispatch (no blocking)
     * - O(N) database query (where N = result set size)
     * - Zero overhead from dispatch() helper
     *
     * Clean Architecture:
     * - Controller → Job → Application Layer
     * - Dependency Injection via Container
     * - SOLID principles throughout
     */
    public function index(Request $request)
    {
        dd(URL::current());
        // DEMO: Create user and send notification via queue
        // This will create ONE job per request
        // $user = UserModel::create([
        //     'name' => 'New User ' . uniqid(),
        //     'email' => 'minhphung485@gmail.com',
        //     'password' => password_hash('secret123', PASSWORD_BCRYPT)
        // ]);

        // Send notification FROM user model TO admin email via QUEUE
        // $user->notifyLater(
        //     new UserCreatedNotification(
        //         userName: $user->name,
        //         userEmail: $user->email,
        //         recipientEmail: 'minhphung485@gmail.com'
        //     ),
        //     queueName: 'notifications'
        // );

        // SendEmailJob::dispatch(
        //     to: 'minhphung485@gmail.com',
        //     subject: 'Test Email from Toporia Framework',
        //     message: '<h1>Hello from Toporia!</h1><p>This email was sent from a queued job.</p>',
        //     from: 'tmpdz7820@gmail.com'
        // );

        // Alternative: Fluent API with queue and delay
        // SendEmailJob::dispatch($to, $subject, $message, $from)
        //     ->onQueue('emails')
        //     ->delay(60);

        // Alternative: Delayed dispatch
        // SendEmailJob::dispatchAfter(60, $to, $subject, $message, $from);

        // Alternative: Synchronous dispatch (blocking)
        // $result = SendEmailJob::dispatchSync($to, $subject, $message, $from);

        // Alternative: Using dispatch() helper
        // dispatch(new SendEmailJob(...));

        $products = ProductModel::query()
            ->where(function ($q) {
                $q->where('stock', '>', 0);
            })
            ->select([
                'is_active',
                'COUNT(*) as count_active'
            ])
            ->groupBy('is_active')
            ->paginate(12);

        // Using trait helper method
        return $this->json([
            'products' => $products,
            'email_job_dispatched' => true,
            'dispatch_method' => 'dispatch() helper with auto-DI',
            'request_path' => $request->path(),
            'method' => $request->method()
        ]);
    }

    /**
     * Dashboard view.
     *
     * Demo: Using trait's view() method + helper functions
     */
    public function dashboard()
    {
        $user = auth()->user();

        // Using trait helper method
        return $this->view('home/index', [
            'user' => $user,
            'path' => request()->path()
        ]);
    }

    /**
     * API endpoint example.
     *
     * Demo: Pure method injection, no trait needed
     */
    public function api(Request $request, Response $response)
    {
        return $response->json([
            'message' => 'Hello from API',
            'query' => $request->query('search'),
        ]);
    }
}
