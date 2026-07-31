<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiRuntimeRepository;

final class AiUsageService
{
    public function __construct(
        private readonly AiRuntimeRepository $runtime,
        private readonly LogsService $logs
    ) {
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>}
     */
    public function usageList(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->runtime->listUsageLogs($filters);
        $normalized = [];
        foreach ($items as $item) {
            $normalized[] = $this->normalizeUsageLog((array)$item);
        }

        return [
            'items' => $normalized,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>,meta:array<string,mixed>}
     */
    public function auditList(array $filters): array
    {
        $filters['action_prefix'] = 'ai_';
        $rows = $this->logs->auditList($filters);
        $items = is_array($rows['items'] ?? null) ? (array)$rows['items'] : [];

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized[] = $this->normalizeAuditLog($item);
        }

        return [
            'items' => $normalized,
            'meta' => (array)($rows['meta'] ?? []),
        ];
    }

    /** @param array<string,mixed> $row */
    private function normalizeUsageLog(array $row): array
    {
        $meta = $this->decodeJson((string)($row['request_meta'] ?? ''));
        $meta = $this->maskSecrets($meta);

        return [
            'public_id' => (string)($row['public_id'] ?? ''),
            'user_id' => (int)($row['user_id'] ?? 0) ?: null,
            'provider_public_id' => (string)($row['provider_public_id'] ?? ''),
            'action_type' => (string)($row['action_type'] ?? ''),
            'intent_code' => (string)($row['intent_code'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'error_code' => (string)($row['error_code'] ?? ''),
            'request_tokens' => (int)($row['request_tokens'] ?? 0),
            'response_tokens' => (int)($row['response_tokens'] ?? 0),
            'total_tokens' => (int)($row['total_tokens'] ?? 0),
            'latency_ms' => (int)($row['latency_ms'] ?? 0),
            'is_sensitive_context' => (bool)($row['is_sensitive_context'] ?? false),
            'request_meta' => $meta,
            'created_at' => (string)($row['created_at'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $row */
    private function normalizeAuditLog(array $row): array
    {
        $details = $this->decodeJson((string)($row['details'] ?? ''));
        $details = $this->maskSecrets($details);

        return [
            'public_id' => (string)($row['public_id'] ?? ''),
            'actor_public_id' => (string)($row['actor_public_id'] ?? ''),
            'entity_type' => (string)($row['entity_type'] ?? ''),
            'entity_public_id' => (string)($row['entity_public_id'] ?? ''),
            'action' => (string)($row['action'] ?? ''),
            'details' => $details,
            'created_at' => (string)($row['created_at'] ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    private function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function maskSecrets(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $name = is_string($key) ? strtolower($key) : '';
            if ($name !== '' && (
                str_contains($name, 'token')
                || str_contains($name, 'secret')
                || str_contains($name, 'password')
                || str_contains($name, 'authorization')
                || str_contains($name, 'api_key')
                || str_contains($name, 'cookie')
                || str_contains($name, 'prompt')
                || str_contains($name, 'instruction')
                || str_contains($name, 'message')
                || str_contains($name, 'content')
            )) {
                $result[$key] = '***';
                continue;
            }
            $result[$key] = $this->maskSecrets($item);
        }

        return $result;
    }
}
