<?php
declare(strict_types=1);

namespace Api\System\Library\Support;

use Api\System\Library\Logger\JsonLogger;

/**
 * Error logging for code that has no container (module subsystem, cron
 * scheduler, cache, bootstrap).
 *
 * `error_log()` output is discarded by stock PHP-FPM (no `error_log` in
 * php.ini, `catch_workers_output` off), so a failure there left operators with
 * nothing to read. The application logger is published here once the container
 * has built it; until then (very early bootstrap, a worker that failed before
 * boot) the helper falls back to `error_log()` so no message is ever lost.
 */
final class AppLog
{
    private static ?JsonLogger $logger = null;

    public static function setLogger(JsonLogger $logger): void
    {
        self::$logger = $logger;
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    private static function write(string $level, string $message, array $context): void
    {
        $logger = self::$logger;
        if ($logger !== null) {
            try {
                // Application channel: file + database, with the logger's masking.
                $logger->log('application', $level, $message, $context);
                return;
            } catch (\Throwable $e) {
                // Never let logging break the caller; fall through to error_log().
                error_log('[AppLog] logger failed: ' . $e->getMessage());
            }
        }

        error_log($context === []
            ? $message
            : $message . ' ' . (string)json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
