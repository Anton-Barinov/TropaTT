<?php
declare(strict_types=1);

namespace Api\Controller\Telemetry;

final class LoginDebugLogger
{
    public function log(): void
    {
        $logFile = dirname(__DIR__, 3) . '/storage_api/logs/login-debug.log';
        $input = file_get_contents('php://input') ?: '';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Strip embedded newlines from user-controlled fields to prevent
        // log injection (a CR/LF in $input would let a caller forge fake log
        // entries that look like server-side audit trails).
        $safeIp = str_replace(["\r", "\n"], ['', ''], (string)$ip);
        $safeInput = str_replace(["\r", "\n"], ['\r', '\n'], (string)$input);

        $line = "[$timestamp] [{$safeIp}] {$safeInput}\n";
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        http_response_code(204);
    }
}
