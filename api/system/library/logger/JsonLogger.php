<?php
declare(strict_types=1);

namespace Api\System\Library\Logger;

final class JsonLogger
{
    /** @var array<int,string> */
    private const SENSITIVE_KEY_PARTS = ['password', 'token', 'secret', 'api_key', 'authorization', 'cookie', 'set-cookie'];

    /** @param array<string,mixed> $channels */
    public function __construct(
        private readonly array $channels,
        private readonly array $maskedKeys = [],
        private readonly mixed $dbWriter = null
    ) {
    }

    /** @param array<string,mixed> $context */
    public function log(string $channel, string $level, string $message, array $context = []): void
    {
        $path = $this->channels[$channel] ?? null;
        if (!is_string($path) || $path === '') {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $row = [
            'timestamp' => gmdate('c'),
            'level' => strtolower($level),
            'message' => $message,
            'context' => $this->mask($context),
        ];

        @file_put_contents($path, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }

    /** @param array<string,mixed> $context */
    public function request(array $context): void
    {
        $this->log('request', 'info', 'request_completed', $context);
        $this->writeToDb('request', $this->mask($context));
    }

    /** @param array<string,mixed> $context */
    public function audit(array $context): void
    {
        $context = $this->normalizeAuditContext($context);
        $this->log('audit', 'info', 'audit_event', $context);
        $this->writeToDb('audit', $this->mask($context));
    }

    /** @param array<string,mixed> $context */
    public function security(array $context): void
    {
        $this->log('security', 'warning', 'security_event', $context);
        $this->writeToDb('security', $this->mask($context));
    }

    /** @param array<string,mixed> $context */
    public function error(string|array $message, array $context = []): void
    {
        if (is_array($message)) {
            $context = $message;
            $message = 'unhandled_error';
        }
        $this->log('error', 'error', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('application', 'warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('application', 'info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function notice(string $message, array $context = []): void
    {
        $this->log('application', 'notice', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log('application', 'debug', $message, $context);
    }

    /** @param array<string,mixed> $context */
    private function writeToDb(string $channel, array $context): void
    {
        if (!is_callable($this->dbWriter)) {
            return;
        }

        try {
            ($this->dbWriter)($channel, $context);
        } catch (\Throwable) {
            // Keep logger safe: DB issues must not break request flow.
        }
    }

    /** @param array<string,mixed> $data */
    private function mask(array $data): array
    {
        $result = [];
        foreach ($data as $k => $v) {
            if ($this->isSensitiveKey((string)$k)) {
                $result[$k] = '***';
                continue;
            }

            if (is_array($v)) {
                $result[$k] = $this->mask($v);
                continue;
            }

            if (is_string($v)) {
                $result[$k] = $this->maskString($v);
                continue;
            }

            $result[$k] = $v;
        }

        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        $masked = array_map(static fn(string $item): string => strtolower($item), $this->maskedKeys);
        if (in_array($normalized, $masked, true)) {
            return true;
        }

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }

    private function maskString(string $value): string
    {
        $masked = $value;
        $patterns = [
            '/(bearer\s+)[A-Za-z0-9\.\-_]+/i' => '$1***',
            '/((?:password|token|secret|api[_-]?key|authorization|cookie|set-cookie)\s*[=:]\s*)[^,\s;"]+/i' => '$1***',
            '/("(?:password|token|secret|api[_-]?key|authorization|cookie|set-cookie)"\s*:\s*")[^"]*(")/i' => '$1***$2',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $next = preg_replace($pattern, $replacement, $masked);
            if (is_string($next)) {
                $masked = $next;
            }
        }

        return $masked;
    }

    /** @param array<string,mixed> $context */
    private function normalizeAuditContext(array $context): array
    {
        $action = (string)($context['action'] ?? '');
        if (!str_starts_with($action, 'ai_')) {
            return $context;
        }

        $providerCode = trim((string)($context['provider_code'] ?? ''));
        if ($providerCode !== '' && !array_key_exists('provider_type', $context)) {
            $context['provider_type'] = $providerCode;
        }

        return $context;
    }
}
