<?php

declare(strict_types=1);

namespace App\Application\Jobs;

use Toporia\Framework\Queue\Job;

/**
 * Process Report Job
 *
 * Example job with dependency injection.
 * Dependencies will be auto-wired from the container.
 */
final class ProcessReportJob extends Job
{
    /**
     * @param int $reportId Report ID to process
     * @param string $format Report format (pdf, csv, excel)
     */
    public function __construct(
        private int $reportId,
        private string $format = 'pdf'
    ) {
        parent::__construct();
        $this->tries(5); // Max 5 retry attempts
        $this->onQueue('reports'); // Use 'reports' queue
    }

    /**
     * Execute the job.
     *
     * You can inject dependencies in handle() method via container.
     *
     * @return void
     */
    public function handle(): void
    {
        // Simulate report processing
        error_log("Processing report #{$this->reportId} in {$this->format} format...");

        // Simulate some work
        sleep(2);

        // In real application:
        // 1. Fetch report data from database
        // 2. Generate report file (PDF, CSV, Excel)
        // 3. Upload to storage (S3, local filesystem)
        // 4. Send notification to user
        // 5. Update report status in database

        error_log("Report #{$this->reportId} processed successfully!");
    }

    /**
     * Handle job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        error_log("Failed to process report #{$this->reportId}: " . $exception->getMessage());

        // Update report status to 'failed' in database
        // Send notification to admin
    }
}
