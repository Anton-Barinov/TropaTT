<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class AdminAiContextBuilder
{
    public function __construct(
        private readonly AdminWidgetService $widgets,
        private readonly LogsService $logs,
        private readonly WebhookService $webhooks,
        private readonly WorkflowService $workflow,
        private readonly AiMaskingService $masking
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function buildLogReviewContext(array $input): array
    {
        $limit = max(1, min(100, (int)($input['limit'] ?? 20)));
        $security = $this->logs->securityList(['limit' => $limit]);
        $items = is_array($security['items'] ?? null) ? (array)$security['items'] : [];

        $sanitizedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sanitizedItems[] = [
                'event_type' => trim((string)($item['event_type'] ?? '')),
                'created_at' => (string)($item['created_at'] ?? ''),
                'actor_user_id' => (string)($item['actor_user_id'] ?? ''),
                'details' => $this->masking->maskSensitiveText((string)json_encode($item['details'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        }

        return [
            'widgets_summary' => $this->widgets->summary(),
            'widgets_system' => $this->widgets->system(),
            'security_logs' => $sanitizedItems,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function buildWebhookHealthContext(array $input): array
    {
        $limit = max(1, min(100, (int)($input['limit'] ?? 20)));
        $subscriptionsData = $this->webhooks->listSubscriptions(['limit' => $limit, 'page' => 1]);
        $deliveriesData = $this->webhooks->listDeliveries(['limit' => max(20, $limit * 2), 'page' => 1]);

        $subscriptions = is_array($subscriptionsData['items'] ?? null) ? (array)$subscriptionsData['items'] : [];
        $deliveries = is_array($deliveriesData['items'] ?? null) ? (array)$deliveriesData['items'] : [];

        $normalizedSubscriptions = [];
        foreach ($subscriptions as $item) {
            if (!is_array($item)) {
                continue;
            }
            $endpointHost = $this->endpointHost((string)($item['endpoint'] ?? ''));
            $events = is_array($item['events'] ?? null) ? (array)$item['events'] : [];
            $normalizedSubscriptions[] = [
                'webhook_public_id' => (string)($item['public_id'] ?? ''),
                'title' => (string)($item['title'] ?? ''),
                'endpoint_host' => $endpointHost,
                'is_active' => (int)($item['is_active'] ?? 0) === 1,
                'events' => array_slice(array_values(array_map(static fn($v): string => trim((string)$v), $events)), 0, 20),
                'created_at' => (string)($item['created_at'] ?? ''),
                'updated_at' => (string)($item['updated_at'] ?? ''),
            ];
        }

        $normalizedDeliveries = [];
        foreach ($deliveries as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalizedDeliveries[] = [
                'delivery_public_id' => (string)($item['public_id'] ?? ''),
                'webhook_public_id' => (string)($item['webhook_public_id'] ?? ''),
                'event_code' => (string)($item['event_code'] ?? ''),
                'status' => (string)($item['status'] ?? ''),
                'response_code' => (int)($item['response_code'] ?? 0),
                'created_at' => (string)($item['created_at'] ?? ''),
            ];
        }

        return [
            'widgets_summary' => $this->widgets->summary(),
            'widgets_system' => $this->widgets->system(),
            'webhook_summary' => $this->webhooks->summary(),
            'webhook_subscriptions' => $normalizedSubscriptions,
            'webhook_deliveries' => $normalizedDeliveries,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function buildWorkflowRuleAuditContext(array $input, array $actor): array
    {
        $limit = max(1, min(100, (int)($input['limit'] ?? 20)));
        $rulesData = $this->workflow->listRules(['limit' => $limit, 'page' => 1], $actor);
        $runsData = $this->workflow->listRuns(['limit' => max(20, $limit * 2), 'page' => 1], $actor);

        $rules = is_array($rulesData['items'] ?? null) ? (array)$rulesData['items'] : [];
        $runs = is_array($runsData['items'] ?? null) ? (array)$runsData['items'] : [];

        $normalizedRules = [];
        foreach ($rules as $item) {
            if (!is_array($item)) {
                continue;
            }
            $payloadRaw = json_encode($item['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $normalizedRules[] = [
                'rule_public_id' => (string)($item['public_id'] ?? ''),
                'title' => (string)($item['title'] ?? ''),
                'trigger_code' => (string)($item['trigger_code'] ?? ''),
                'action_code' => (string)($item['action_code'] ?? ''),
                'is_enabled' => (bool)($item['is_enabled'] ?? false),
                'payload_masked' => $this->masking->maskSensitiveText(is_string($payloadRaw) ? $payloadRaw : ''),
                'created_at' => (string)($item['created_at'] ?? ''),
                'updated_at' => (string)($item['updated_at'] ?? ''),
            ];
        }

        $normalizedRuns = [];
        foreach ($runs as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalizedRuns[] = [
                'run_public_id' => (string)($item['public_id'] ?? ''),
                'rule_public_id' => (string)($item['rule_public_id'] ?? ''),
                'rule_title' => (string)($item['rule_title'] ?? ''),
                'status' => (string)($item['status'] ?? ''),
                'error_masked' => $this->masking->maskSensitiveText((string)($item['error'] ?? '')),
                'created_at' => (string)($item['created_at'] ?? ''),
            ];
        }

        return [
            'workflow_rules' => $normalizedRules,
            'workflow_runs' => $normalizedRuns,
        ];
    }

    private function endpointHost(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return '';
        }
        $host = (string)(parse_url($endpoint, PHP_URL_HOST) ?: '');
        if ($host === '') {
            return '';
        }
        $scheme = strtolower((string)(parse_url($endpoint, PHP_URL_SCHEME) ?: ''));
        $port = (int)(parse_url($endpoint, PHP_URL_PORT) ?: 0);
        if ($port > 0) {
            return ($scheme !== '' ? ($scheme . '://') : '') . $host . ':' . $port;
        }

        return ($scheme !== '' ? ($scheme . '://') : '') . $host;
    }
}
