<?php

namespace Tropatt\InSales;

/**
 * Sliding-window rate limiter (InSales allows ~500 requests per 5 minutes).
 *
 * The state is a list of request timestamps; `allow()` is pure, so the caller
 * decides where to persist it (the cron/API client uses a JSON file).
 */
class RateLimiter
{
    /**
     * @return array{0:bool,1:array}
     */
    public static function allow(array $state, $limit, $windowSeconds, $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $limit = max(1, (int)$limit);
        $windowSeconds = max(1, (int)$windowSeconds);

        // Server-side signal wins: InSales answers 503 with Retry-After when the
        // 500 requests / 5 minutes quota is exhausted, and reports its own
        // accounting in API-Usage-Limit.
        $blockedUntil = (int)($state['blocked_until'] ?? 0);
        if ($blockedUntil > $now) {
            return array(false, array_merge($state, array('retry_after' => $blockedUntil - $now)));
        }

        $timestamps = array();
        foreach ((array)($state['timestamps'] ?? array()) as $timestamp) {
            if ((int)$timestamp > $now - $windowSeconds) {
                $timestamps[] = (int)$timestamp;
            }
        }

        $usage = is_array($state['api_usage_limit'] ?? null) ? $state['api_usage_limit'] : null;
        if ($usage !== null && (int)($usage['used'] ?? 0) >= $limit && (int)($usage['at'] ?? 0) > $now - $windowSeconds) {
            $retryAfter = max(1, ((int)$usage['at'] + $windowSeconds) - $now);

            return array(false, array_merge($state, array('timestamps' => $timestamps, 'retry_after' => $retryAfter)));
        }

        if (count($timestamps) >= $limit) {
            sort($timestamps);
            $retryAfter = $windowSeconds - ($now - (int)$timestamps[0]);

            return array(false, array('timestamps' => $timestamps, 'retry_after' => max(1, $retryAfter)));
        }

        $timestamps[] = $now;

        return array(true, array('timestamps' => $timestamps, 'retry_after' => 0));
    }

    /**
     * @return int
     */
    public static function remaining(array $state, $limit, $windowSeconds, $now = null)
    {
        $now = $now === null ? time() : (int)$now;
        $used = 0;

        foreach ((array)($state['timestamps'] ?? array()) as $timestamp) {
            if ((int)$timestamp > $now - (int)$windowSeconds) {
                $used++;
            }
        }

        return max(0, (int)$limit - $used);
    }
}
