<?php

declare(strict_types=1);

namespace App\Jobs\Examples;

use Toporia\Framework\Queue\Job;
use Toporia\Framework\Queue\Backoff\ExponentialBackoff;
use Toporia\Framework\Queue\Middleware\{RateLimited, WithoutOverlapping};

/**
 * Example Job: Process API Request
 *
 * Demonstrates all advanced queue features:
 * - Retry with exponential backoff
 * - Rate limiting middleware
 * - Prevent overlapping execution
 * - Dependency injection in handle()
 *
 * @package App\Jobs\Examples
 */
final class ProcessApiRequestJob extends Job
{
    /**
     * Maximum retry attempts.
     * Job will fail after 5 attempts.
     */
    protected int $maxAttempts = 5;

    /**
     * @param string $apiEndpoint API URL to call
     * @param array $data Data to send
     */
    public function __construct(
        private string $apiEndpoint,
        private array $data
    ) {
        parent::__construct();

        // Set exponential backoff: 2s, 4s, 8s, 16s, 32s (max 60s)
        $this->backoff = new ExponentialBackoff(base: 2, max: 60);
    }

    /**
     * Define job middleware.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [
            // Rate limit: max 10 API calls per minute
            new RateLimited(
                limiter: app('limiter'),
                maxAttempts: 10,
                decayMinutes: 1
            ),

            // Prevent overlapping requests to same endpoint
            (new WithoutOverlapping(app('cache')))
                ->by("api-request-{$this->apiEndpoint}")
                ->expireAfter(300), // 5 minutes max execution time
        ];
    }

    /**
     * Execute the job.
     *
     * Dependencies are automatically injected via container.
     *
     * @return void
     */
    public function handle(): void
    {
        // Simulate API call
        $response = $this->callApi($this->apiEndpoint, $this->data);

        // Process response
        $this->processResponse($response);
    }

    /**
     * Handle job failure after all retries exhausted.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        // Log to monitoring service
        error_log(sprintf(
            'API request failed after %d attempts: %s',
            $this->attempts,
            $exception->getMessage()
        ));

        // Send alert
        // Notification::send(new ApiRequestFailedNotification($this->apiEndpoint));
    }

    private function callApi(string $endpoint, array $data): array
    {
        // Simulate API call that might fail
        if (rand(1, 3) === 1) {
            throw new \RuntimeException('API temporarily unavailable');
        }

        return ['status' => 'success', 'data' => $data];
    }

    private function processResponse(array $response): void
    {
        // Process API response
        // ...
    }
}
