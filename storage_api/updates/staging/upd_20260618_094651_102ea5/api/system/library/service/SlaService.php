<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Sla\SlaRepository;
use Api\System\Library\Support\Ulid;

final class SlaService
{
    public function __construct(private readonly SlaRepository $sla)
    {
    }

    public function list(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->sla->list($filters);
        $items = array_map([$this, 'normalizePolicy'], $items);

        return [
            'items' => $items,
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

    public function create(array $input): array
    {
        $publicId = Ulid::generate('sla');
        $now = gmdate('Y-m-d H:i:s');

        $this->sla->create([
            'public_id' => $publicId,
            'title' => trim((string)$input['title']),
            'response_minutes' => max(1, (int)$input['response_minutes']),
            'resolve_minutes' => max(1, (int)$input['resolve_minutes']),
            'escalation_payload' => $this->encodePayload($input['escalation_payload'] ?? []),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->get($publicId) ?? ['public_id' => $publicId];
    }

    public function get(string $publicId): ?array
    {
        $item = $this->sla->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        return $this->normalizePolicy($item);
    }

    public function update(string $publicId, array $input): ?array
    {
        $existing = $this->sla->findByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        $set = ['updated_at' => gmdate('Y-m-d H:i:s')];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('response_minutes', $input)) {
            $set['response_minutes'] = max(1, (int)$input['response_minutes']);
        }
        if (array_key_exists('resolve_minutes', $input)) {
            $set['resolve_minutes'] = max(1, (int)$input['resolve_minutes']);
        }
        if (array_key_exists('escalation_payload', $input)) {
            $set['escalation_payload'] = $this->encodePayload($input['escalation_payload']);
        }

        $this->sla->updateByPublicId($publicId, $set);
        return $this->get($publicId);
    }

    public function delete(string $publicId): bool
    {
        return $this->sla->deleteByPublicId($publicId);
    }

    public function report(): array
    {
        return $this->sla->reportSummary();
    }

    private function encodePayload(mixed $payload): string
    {
        if (is_array($payload)) {
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string,mixed> $item */
    private function normalizePolicy(array $item): array
    {
        $item['escalation_payload'] = json_decode((string)($item['escalation_payload'] ?? '{}'), true) ?: [];
        return $item;
    }

    public function assignToTask(string $taskPublicId, string $slaPublicId): ?array
    {
        $policy = $this->sla->findByPublicId($slaPublicId);
        if (!$policy) return null;

        $task = $this->sla->findTaskByPublicId($taskPublicId);
        if (!$task) return null;

        $now = date('Y-m-d H:i:s');
        $responseDeadline = date('Y-m-d H:i:s', strtotime($now) + ((int)$policy['response_minutes'] * 60));
        $resolveDeadline = date('Y-m-d H:i:s', strtotime($now) + ((int)$policy['resolve_minutes'] * 60));

        $this->sla->updateTaskSla($task['id'], (int)$policy['id'], $responseDeadline, $resolveDeadline);

        return [
            'task_id' => $taskPublicId,
            'sla_policy_id' => $slaPublicId,
            'response_deadline' => $responseDeadline,
            'resolve_deadline' => $resolveDeadline,
        ];
    }

    public function checkBreaches(): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->sla->markBreached($now);
    }
}
