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
    public function set(string $scope, string $key, mixed $value, ?int $actorUserId = null, ?array $metadata = null): array
    {
        $scope = trim($scope) !== '' ? trim($scope) : 'global';
        $key = trim($key);
        if ($key === '') {
            return ['error' => 'Key name cannot be empty.'];
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
