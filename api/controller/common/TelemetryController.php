<?php
declare(strict_types=1);

namespace Api\Controller\Common;

use Api\System\Library\Logger\JsonLogger;

final class TelemetryController extends BaseController
{
    public function frontendEvent(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $eventType = strtolower(trim((string)($input['event_type'] ?? '')));
        if (!in_array($eventType, ['api_error', 'js_error', 'csp_violation'], true)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                ['field' => 'event_type', 'message' => 'event_type must be api_error, js_error, or csp_violation'],
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
                'route' => (string)($input['route'] ?? ''),
                'page_url' => (string)($input['page_url'] ?? ''),
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
        $input = $this->request()->allInput();
        $cspReport = is_array($input['csp-report'] ?? null) ? (array)$input['csp-report'] : $input;

        $sanitized = [
            'document_uri' => (string)($cspReport['document-uri'] ?? ''),
            'referrer' => (string)($cspReport['referrer'] ?? ''),
            'blocked_uri' => (string)($cspReport['blocked-uri'] ?? ''),
            'violated_directive' => (string)($cspReport['violated-directive'] ?? ''),
            'effective_directive' => (string)($cspReport['effective-directive'] ?? ''),
            'original_policy' => (string)($cspReport['original-policy'] ?? ''),
            'disposition' => (string)($cspReport['disposition'] ?? ''),
            'status_code' => (int)($cspReport['status-code'] ?? 0),
            'script_sample' => mb_substr((string)($cspReport['script-sample'] ?? ''), 0, 200),
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

        return $this->success('CSP_REPORT_ACCEPTED', 'CSP violation report accepted', [
            'accepted' => true,
            'captured_at' => gmdate('c'),
        ]);
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
