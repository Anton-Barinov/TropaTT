<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Agent\AgentMemoryRepository;
use Api\System\Library\Logger\JsonLogger;

final class AgentMemoryService
{
    public function __construct(
        private readonly AgentMemoryRepository $repo,
        private readonly JsonLogger $logger,
        private readonly string $requestId,
    ) {
    }

    /**
     * @param string $scope
     * @param string $key
     * @return array<string,mixed>|null
     */
    public function get(string $scope, string $key): ?array
    {
        $scope = trim($scope) !== '' ? trim($scope) : 'global';
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        $item = $this->repo->get($scope, $key);
        if ($item === null) {
            return null;
        }

        return $this->formatMemory($item);
    }

    /**
     * @param string $scope
     * @param string $key
     * @param mixed $value
     * @param int|null $actorUserId
     * @param array<string,mixed>|null $metadata
     * @return array<string,mixed>
     */
    public function set(string $scope, string $key, mixed $value, ?int $actorUserId = null, ?array $metadata = null, ?string $entityType = null, ?string $entityPublicId = null): array
    {
        $scope = trim($scope) !== '' ? trim($scope) : 'global';
        $key = trim($key);
        if ($key === '') {
            return ['error' => 'Key name cannot be empty.'];
        }

        if ($entityType !== null && trim($entityType) !== '') {
            $metadata ??= [];
            $metadata['entity_type'] = trim($entityType);
        }
        if ($entityPublicId !== null && trim($entityPublicId) !== '') {
            $metadata ??= [];
            $metadata['entity_public_id'] = trim($entityPublicId);
        }

        $valueType = 'string';
        $valueText = '';

        if (is_array($value) || is_object($value)) {
            $valueType = 'json';
            $valueText = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        } elseif (is_bool($value)) {
            $valueType = 'bool';
            $valueText = $value ? '1' : '0';
        } elseif (is_int($value) || is_float($value)) {
            $valueType = 'number';
            $valueText = (string)$value;
        } else {
            $valueType = 'string';
            $valueText = (string)$value;
        }

        $item = $this->repo->set($scope, $key, $valueText, $valueType, $actorUserId, $metadata);
        $this->logger->info('agent_memory_set', [
            'scope' => $scope,
            'key' => $key,
            'actor_user_id' => $actorUserId,
            'request_id' => $this->requestId,
        ]);

        return ['memory' => $this->formatMemory($item)];
    }

    /**
     * @param string|null $scope
     * @param string|null $prefix
     * @param int $limit
     * @param int $offset
     * @return array<string,mixed>
     */
    public function list(?string $scope = null, ?string $prefix = null, int $limit = 50, int $offset = 0): array
    {
        $items = $this->repo->list($scope, $prefix, $limit, $offset);
        $formatted = array_map(function (array $item): array {
            $meta = !empty($item['metadata_json']) ? json_decode((string)$item['metadata_json'], true) : null;
            return [
                'public_id' => $item['public_id'],
                'scope' => $item['scope'],
                'key' => $item['key_name'],
                'value_type' => $item['value_type'],
                'metadata' => $meta,
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ];
        }, $items);

        return ['items' => $formatted];
    }

    /**
     * @param string $scope
     * @param string $key
     * @return bool
     */
    public function delete(string $scope, string $key): bool
    {
        $scope = trim($scope) !== '' ? trim($scope) : 'global';
        $key = trim($key);
        if ($key === '') {
            return false;
        }

        $deleted = $this->repo->delete($scope, $key);
        if ($deleted) {
            $this->logger->info('agent_memory_deleted', [
                'scope' => $scope,
                'key' => $key,
                'request_id' => $this->requestId,
            ]);
        }

        return $deleted;
    }

    /**
     * Search memory entries by substring, scope, or entity relation.
     *
     * @param string|null $query
     * @param string|null $scope
     * @param string|null $entityType
     * @param string|null $entityPublicId
     * @param int $limit
     * @param int $offset
     * @return array<string,mixed>
     */
    public function search(?string $query = null, ?string $scope = null, ?string $entityType = null, ?string $entityPublicId = null, int $limit = 50, int $offset = 0): array
    {
        $items = $this->repo->search($query, $scope, $entityType, $entityPublicId, $limit, $offset);
        $formatted = array_map(fn(array $item): array => $this->formatMemory($item), $items);

        return ['items' => $formatted, 'total' => count($formatted)];
    }

    /**
     * Export knowledge/memory graph for a specific CRM entity (task, project, client).
     *
     * @param string $entityType
     * @param string $entityPublicId
     * @param int $limit
     * @return array<string,mixed>
     */
    public function exportGraph(string $entityType, string $entityPublicId, int $limit = 100): array
    {
        $entityType = trim($entityType);
        $entityPublicId = trim($entityPublicId);
        if ($entityType === '' || $entityPublicId === '') {
            return ['error' => 'entity_type and entity_public_id are required for export_graph.'];
        }

        $items = $this->repo->exportGraph($entityType, $entityPublicId, $limit);
        $nodes = [];
        $edges = [];

        foreach ($items as $item) {
            $formatted = $this->formatMemory($item);
            $nodes[] = [
                'id' => $formatted['public_id'],
                'label' => $formatted['key'],
                'type' => $formatted['value_type'],
                'scope' => $formatted['scope'],
                'value' => $formatted['value'],
            ];

            $edges[] = [
                'source' => $formatted['public_id'],
                'target' => $entityPublicId,
                'relation' => 'attached_to',
                'target_type' => $entityType,
            ];
        }

        return [
            'graph' => [
                'entity_type' => $entityType,
                'entity_public_id' => $entityPublicId,
                'nodes' => $nodes,
                'edges' => $edges,
                'count' => count($nodes),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function formatMemory(array $item): array
    {
        $type = (string)($item['value_type'] ?? 'string');
        $raw = (string)($item['value_text'] ?? '');
        $val = match ($type) {
            'json' => json_decode($raw, true) ?? $raw,
            'bool' => $raw === '1' || $raw === 'true',
            'number' => is_numeric($raw) ? (str_contains($raw, '.') ? (float)$raw : (int)$raw) : $raw,
            default => $raw,
        };

        $meta = !empty($item['metadata_json']) ? json_decode((string)$item['metadata_json'], true) : null;

        return [
            'public_id' => $item['public_id'],
            'scope' => $item['scope'],
            'key' => $item['key_name'],
            'value' => $val,
            'value_type' => $type,
            'metadata' => $meta,
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'],
        ];
    }
}
