<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Service;

use Module\Crm\JiraMigration\Repository\JiraMigrationRepository;

/**
 * Manages Jira API rate limiting with adaptive backoff.
 * Rate limits are stored and tracked via the repository.
 */
final class JiraRateLimitGuard
{
    private const MAX_REQUESTS_PER_WINDOW = 100;
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private JiraMigrationRepository $repo,
        private int $connectionId,
    ) {
        $this->repo->initRateLimit($connectionId);
    }

    public function waitIfNeeded(): void
    {
        $rateLimit = $this->repo->getRateLimit($this->connectionId);
        if ($rateLimit === null) {
            return;
        }

        // Check retry-after
        if (!empty($rateLimit['retry_after_until'])) {
            $retryUntil = strtotime((string)$rateLimit['retry_after_until']);
            if ($retryUntil > time()) {
                sleep($retryUntil - time() + 1);
                return;
            }
        }

        // Check window-based limit
        if (!empty($rateLimit['window_started_at'])) {
            $windowStart = strtotime((string)$rateLimit['window_started_at']);
            $requestsMade = (int)($rateLimit['requests_made'] ?? 0);
            $elapsed = time() - $windowStart;

            if ($elapsed < self::WINDOW_SECONDS && $requestsMade >= self::MAX_REQUESTS_PER_WINDOW) {
                sleep(self::WINDOW_SECONDS - $elapsed + 1);
            }
        }
    }

    public function recordRequest(bool $reset, ?int $retryAfterSeconds = null): void
    {
        $retryAfter = null;
        if ($retryAfterSeconds !== null) {
            $retryAfter = gmdate('Y-m-d H:i:s', time() + $retryAfterSeconds);
        }

        $this->repo->updateRateLimitAfterRequest($this->connectionId, $reset, $retryAfter);
    }
}
