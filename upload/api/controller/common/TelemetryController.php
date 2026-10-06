<?php
declare(strict_types=1);

namespace Api\Controller\Common;

use Api\System\Library\Logger\JsonLogger;

final class TelemetryController extends BaseController
{
    private const MAX_REPORT_BODY_BYTES = 16 * 1024;
    private const MAX_LOG_FIELD_LENGTH = 512;

    public function frontendEvent(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        if (strlen($this->request()->rawBody) > self::MAX_REPORT_BODY_BYTES) {
            return $this->error('PAYLOAD_TOO_LARGE', $this->t('common/messages.payload_too_large', 'Payload too large'), 413);
        }

        $input = $this->request()->allInput();
        $eventType = strtolower(trim((string)($input['event_type'] ?? '')));
        if (!in_array($eventType, ['api_error', 'js_error', 'csp_violation'], true)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                ['field' => 'event_type', 'message' => $this->t('common/messages.invalid_event_type')],
            ]);
        }

        $payload = is_array($input['payload'] ?? null) ? (array)$input['payload'] : [];
        $sanitized = $this->sanitizePayload($payload);

        /** @var JsonLogger $logger */
        $logger = $this->container->get('logger');
        $logger->security([
            'actor_public_id' => (string)($auth['user']['public_id'] ?? ''),
            'event_type' => match ($eventType) {
                'api_error' => 'frontend_api_error',
                'js_error' => 'frontend_js_error',
                'csp_violation' => 'frontend_csp_violation',
                default => 'frontend_unknown_error',
            },
            'ip' => (string)($this->request()->server['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string)($this->request()->server['HTTP_USER_AGENT'] ?? ''),
            'details' => [
                'route' => $this->sanitizeLogValue($input['route'] ?? '', 256, true),
                'page_url' => $this->sanitizeLogValue($input['page_url'] ?? '', 512, true),
                'payload' => $sanitized,
                'request_id' => (string)$this->request()->requestId,
                'correlation_id' => (string)$this->request()->correlationId,
            ],
        ]);

        return $this->success('TELEMETRY_ACCEPTED', $this->t('common/messages.saved', 'Saved'), [
            'accepted' => true,
            'event_type' => $eventType,
            'captured_at' => gmdate('c'),
        ]);
    }

    public function cspReport(): \Api\System\Library\Http\JsonResponse
    {
        if (strlen($this->request()->rawBody) > self::MAX_REPORT_BODY_BYTES) {
            return $this->error('PAYLOAD_TOO_LARGE', $this->t('common/messages.payload_too_large', 'Payload too large'), 413);
        }

        $rateLimit = $this->checkIpRateLimit('csp_report', 30, 60, 300);
        if (($rateLimit['blocked'] ?? false) === true) {
            $retryAfter = max(1, (int)($rateLimit['retry_after'] ?? 1));
            if (!headers_sent()) {
                header('Retry-After: ' . (string)$retryAfter);
            }
            return $this->error('RATE_LIMITED', $this->t('common/messages.rate_limited', 'Too many requests'), 429, [], [
                'retry_after' => $retryAfter,
            ]);
        }

        $input = $this->request()->allInput();
        $cspReport = is_array($input['csp-report'] ?? null) ? (array)$input['csp-report'] : $input;

        $sanitized = [
            'document_uri' => $this->sanitizeLogValue($cspReport['document-uri'] ?? '', self::MAX_LOG_FIELD_LENGTH, true),
            'referrer' => $this->sanitizeLogValue($cspReport['referrer'] ?? '', self::MAX_LOG_FIELD_LENGTH, true),
            'blocked_uri' => $this->sanitizeLogValue($cspReport['blocked-uri'] ?? '', self::MAX_LOG_FIELD_LENGTH, true),
            'violated_directive' => $this->sanitizeLogValue($cspReport['violated-directive'] ?? '', self::MAX_LOG_FIELD_LENGTH),
            'effective_directive' => $this->sanitizeLogValue($cspReport['effective-directive'] ?? '', self::MAX_LOG_FIELD_LENGTH),
            'original_policy' => $this->sanitizeLogValue($cspReport['original-policy'] ?? '', self::MAX_LOG_FIELD_LENGTH),
            'disposition' => $this->sanitizeLogValue($cspReport['disposition'] ?? '', 64),
            'status_code' => is_scalar($cspReport['status-code'] ?? null)
                ? max(0, min(999, (int)$cspReport['status-code']))
                : 0,
            'script_sample' => $this->sanitizeLogValue($cspReport['script-sample'] ?? '', 200),
        ];

        /** @var JsonLogger $logger */
        $logger = $this->container->get('logger');
        $logger->security([
            'actor_public_id' => '',
            'event_type' => 'frontend_csp_violation',
            'ip' => (string)($this->request()->server['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string)($this->request()->server['HTTP_USER_AGENT'] ?? ''),
            'details' => $sanitized,
        ]);

        return $this->success('CSP_REPORT_ACCEPTED', $this->t('common/messages.csp_accepted'), [
            'accepted' => true,
            'captured_at' => gmdate('c'),
        ]);
    }

    private function sanitizeLogValue(mixed $value, int $maxLength, bool $stripUrlQuery = false): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        $text = trim((string)$value);
        if ($stripUrlQuery && $text !== '') {
            $parts = parse_url($text);
            if (is_array($parts) && isset($parts['host'])) {
                $scheme = isset($parts['scheme']) ? strtolower((string)$parts['scheme']) . '://' : '';
                $host = (string)$parts['host'];
                $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
                $path = (string)($parts['path'] ?? '');
                $text = $scheme . $host . $port . $path;
            } elseif (is_array($parts) && isset($parts['path'])) {
                $text = (string)$parts['path'];
            }
        }

        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
        return mb_substr($text, 0, max(0, $maxLength));
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
