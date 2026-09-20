<?php
declare(strict_types=1);

namespace Api\Controller\Telemetry;

use Api\Controller\Common\BaseController;
use Api\System\Library\Logger\JsonLogger;

final class LoginDebugLogger extends BaseController
{
    public function log(): void
    {
        $payload = $this->request()->allInput();

        $sanitized = $this->sanitizePayload($payload);

        $ip = (string)($this->request()->server['REMOTE_ADDR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $userAgent = (string)($this->request()->server['HTTP_USER_AGENT'] ?? '');
        $actorPublicId = (string)($this->user()['user']['public_id'] ?? '');

        // Route login-debug telemetry through the same AppLog/JsonLogger::security()
        // + sanitizePayload()/isSensitiveKey() redaction pipeline used by
        // TelemetryController::frontendEvent() so sensitive fields (password,
        // token, secret, authorization, api_key, cookie, ...) never reach the
        // on-disk log in plaintext.
        /** @var JsonLogger $logger */
        $logger = $this->container->get('logger');
        $logger->security([
            'actor_public_id' => $actorPublicId,
            'event_type' => 'login_debug',
            'ip' => $ip,
            'user_agent' => $userAgent,
            'details' => $sanitized,
        ]);

        http_response_code(204);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower(trim((string)$key));
            if ($this->isSensitiveKey($normalizedKey)) {
                $result[(string)$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $result[(string)$key] = $this->sanitizePayload($value);
                continue;
            }

            $scalar = is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE);
            $result[(string)$key] = mb_substr((string)$scalar, 0, 1000);
        }

        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        $fragments = [
            'password',
            'secret',
            'token',
            'authorization',
            'cookie',
            'api_key',
            'apikey',
            'prompt',
            'raw_prompt',
        ];

        foreach ($fragments as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
