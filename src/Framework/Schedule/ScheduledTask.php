<?php

declare(strict_types=1);

namespace Toporia\Framework\Schedule;

/**
 * Scheduled Task
 *
 * Represents a task that runs on a schedule.
 * Provides fluent interface for configuring task frequency.
 */
final class ScheduledTask
{
    private string $expression = '* * * * *';
    private ?string $timezone = null;
    private array $filters = [];
    private array $rejects = [];

    public function __construct(
        private mixed $callback,
        private ?string $description = null
    ) {}

    /**
     * Set the cron expression
     *
     * @param string $expression
     * @return self
     */
    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     * Run the task every minute
     *
     * @return self
     */
    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    /**
     * Run the task every X minutes
     *
     * @param int $minutes
     * @return self
     */
    public function everyMinutes(int $minutes): self
    {
        return $this->cron("*/{$minutes} * * * *");
    }

    /**
     * Run the task hourly
     *
     * @return self
     */
    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    /**
     * Run the task hourly at a specific minute
     *
     * @param int $minute
     * @return self
     */
    public function hourlyAt(int $minute): self
    {
        return $this->cron("{$minute} * * * *");
    }

    /**
     * Run the task daily
     *
     * @return self
     */
    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    /**
     * Run the task daily at a specific time
     *
     * @param string $time Format: 'HH:MM'
     * @return self
     */
    public function dailyAt(string $time): self
    {
        [$hour, $minute] = explode(':', $time);
        return $this->cron("{$minute} {$hour} * * *");
    }

    /**
     * Run the task weekly
     *
     * @return self
     */
    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    /**
     * Run the task monthly
     *
     * @return self
     */
    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    /**
     * Run the task on weekdays only
     *
     * @return self
     */
    public function weekdays(): self
    {
        return $this->cron('0 0 * * 1-5');
    }

    /**
     * Run the task on weekends only
     *
     * @return self
     */
    public function weekends(): self
    {
        return $this->cron('0 0 * * 0,6');
    }

    /**
     * Run the task on Mondays
     *
     * @return self
     */
    public function mondays(): self
    {
        return $this->cron('0 0 * * 1');
    }

    /**
     * Run the task on Tuesdays
     *
     * @return self
     */
    public function tuesdays(): self
    {
        return $this->cron('0 0 * * 2');
    }

    /**
     * Run the task on Wednesdays
     *
     * @return self
     */
    public function wednesdays(): self
    {
        return $this->cron('0 0 * * 3');
    }

    /**
     * Run the task on Thursdays
     *
     * @return self
     */
    public function thursdays(): self
    {
        return $this->cron('0 0 * * 4');
    }

    /**
     * Run the task on Fridays
     *
     * @return self
     */
    public function fridays(): self
    {
        return $this->cron('0 0 * * 5');
    }

    /**
     * Run the task on Saturdays
     *
     * @return self
     */
    public function saturdays(): self
    {
        return $this->cron('0 0 * * 6');
    }

    /**
     * Run the task on Sundays
     *
     * @return self
     */
    public function sundays(): self
    {
        return $this->cron('0 0 * * 0');
    }

    /**
     * Set the timezone for the task
     *
     * @param string $timezone
     * @return self
     */
    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * Add a filter to determine if the task should run
     *
     * @param callable $callback
     * @return self
     */
    public function when(callable $callback): self
    {
        $this->filters[] = $callback;
        return $this;
    }

    /**
     * Add a rejection filter
     *
     * @param callable $callback
     * @return self
     */
    public function skip(callable $callback): self
    {
        $this->rejects[] = $callback;
        return $this;
    }

    /**
     * Set task description
     *
     * @param string $description
     * @return self
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Check if the task is due to run
     *
     * @param \DateTime $currentTime
     * @return bool
     */
    public function isDue(\DateTime $currentTime): bool
    {
        // Check cron expression
        if (!$this->matchesCronExpression($currentTime)) {
            return false;
        }

        // Check filters
        foreach ($this->filters as $filter) {
            if (!$filter()) {
                return false;
            }
        }

        // Check rejects
        foreach ($this->rejects as $reject) {
            if ($reject()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute the task
     *
     * @return void
     */
    public function execute(): void
    {
        ($this->callback)();
    }

    /**
     * Get task description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description ?? 'Unnamed task';
    }

    /**
     * Get the cron expression
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Check if current time matches cron expression
     *
     * @param \DateTime $currentTime
     * @return bool
     */
    private function matchesCronExpression(\DateTime $currentTime): bool
    {
        [$minute, $hour, $day, $month, $dayOfWeek] = explode(' ', $this->expression);

        // Apply timezone if set
        if ($this->timezone !== null) {
            $currentTime = clone $currentTime;
            $currentTime->setTimezone(new \DateTimeZone($this->timezone));
        }

        return $this->matchesCronField($minute, $currentTime->format('i'))
            && $this->matchesCronField($hour, $currentTime->format('H'))
            && $this->matchesCronField($day, $currentTime->format('d'))
            && $this->matchesCronField($month, $currentTime->format('m'))
            && $this->matchesCronField($dayOfWeek, $currentTime->format('w'));
    }

    /**
     * Check if a value matches a cron field
     *
     * @param string $field
     * @param string $value
     * @return bool
     */
    private function matchesCronField(string $field, string $value): bool
    {
        // Match all
        if ($field === '*') {
            return true;
        }

        // Match specific value
        if ($field === $value) {
            return true;
        }

        // Match range (e.g., 1-5)
        if (str_contains($field, '-')) {
            [$min, $max] = explode('-', $field);
            return $value >= $min && $value <= $max;
        }

        // Match step (e.g., */5)
        if (str_contains($field, '/')) {
            [$base, $step] = explode('/', $field);
            if ($base === '*') {
                return (int)$value % (int)$step === 0;
            }
        }

        // Match list (e.g., 1,3,5)
        if (str_contains($field, ',')) {
            $values = explode(',', $field);
            return in_array($value, $values, true);
        }

        return false;
    }
}
