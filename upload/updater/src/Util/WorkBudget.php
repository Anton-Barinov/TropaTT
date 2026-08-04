<?php
declare(strict_types=1);

namespace Updater\Util;

/**
 * Wall-clock budget for one updater HTTP request.
 *
 * Long operations (file backup, file apply, DB dump, migrations, DB restore,
 * rollback) are split into bounded chunks so every request finishes well
 * inside shared-hosting limits (nginx proxy_read_timeout, Apache Timeout,
 * PHP-FPM request_terminate_timeout) no matter how large the update or the
 * database is. The budget only tracks elapsed wall-clock time; chunk sizes
 * are counted by the callers, and either limit stopping the loop first.
 */
final class WorkBudget
{
    private function __construct(private readonly float $deadline)
    {
    }

    /**
     * @param float $seconds how many seconds of work this request may do
     */
    public static function forSeconds(float $seconds): self
    {
        return new self(microtime(true) + max(1.0, $seconds));
    }

    /**
     * True when the budget is exhausted and the caller must stop and return
     * a "continue" response so the page issues the next request.
     */
    public function exhausted(): bool
    {
        return microtime(true) >= $this->deadline;
    }

    /**
     * How many seconds of budget remain (useful for logging / messages).
     */
    public function remainingSeconds(): float
    {
        return max(0.0, $this->deadline - microtime(true));
    }
}
