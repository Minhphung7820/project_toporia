<?php

declare(strict_types=1);

namespace App\Jobs;

use Toporia\Framework\Queue\Job;

/**
 * Example Job
 *
 * Demonstrates how to create a queue job.
 */
class ExampleJob extends Job
{
    /**
     * Create a new job instance.
     *
     * @param string $message Message to process
     * @param array $data Additional data
     */
    public function __construct(
        private string $message,
        private array $data = []
    ) {
        parent::__construct();

        // Optional: Set max attempts
        $this->tries(5);

        // Optional: Set queue name
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     *
     * This is where your job logic goes.
     */
    public function handle(): void
    {
        echo "Processing job: {$this->getId()}\n";
        echo "Message: {$this->message}\n";

        // Simulate work
        sleep(2);

        // Process data
        if (!empty($this->data)) {
            echo "Data: " . json_encode($this->data) . "\n";
        }

        // Example: Send email, process payment, generate report, etc.
        // ...

        echo "Job completed successfully!\n";
    }

    /**
     * Handle job failure (called after max attempts exceeded).
     *
     * @param \Throwable $exception The exception that caused failure
     */
    public function failed(\Throwable $exception): void
    {
        // Log the failure
        error_log("Job {$this->getId()} failed: {$exception->getMessage()}");

        // Send notification to admin
        // Mail::send(...);

        // Store in database for review
        // DB::table('failed_job_alerts')->insert([...]);
    }

    /**
     * Called before handle() (optional hook).
     */
    protected function before(): void
    {
        echo "Job starting: {$this->getId()}\n";

        // Example: Start timer, log start time, etc.
    }

    /**
     * Called after successful handle() (optional hook).
     */
    protected function after(): void
    {
        echo "Job finished: {$this->getId()}\n";

        // Example: Log completion time, update metrics, etc.
    }
}
