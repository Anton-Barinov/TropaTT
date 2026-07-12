<?php
declare(strict_types=1);

if (!function_exists('ai_diag_log')) {
    /**
     * Write AI diagnostic message to a dedicated log file instead of stderr.
     * Prevents NGINX from logging successful AI operations as [error].
     */
    function ai_diag_log(string $message): void
    {
        $base = rtrim((string)(getenv('CRM_STORAGE_BASE') ?: (dirname(__DIR__, 3) . '/../storage_api')), '/');
        $logDir = $base . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/ai-diag.log';
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . "\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
