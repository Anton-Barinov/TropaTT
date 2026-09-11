<?php
declare(strict_types=1);

namespace Api\Model\Agent;

use PDO;

final class AgentMemoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param string $scope
     * @param string $keyName
     * @return array<string,mixed>|null
     */
    public function get(string $scope, string $keyName): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM agent_memory WHERE scope = :scope AND key_name = :key_name LIMIT 1');
        $stmt->execute(['scope' => $scope, 'key_name' => $keyName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @param string $scope
     * @param string $keyName
     * @param string $valueText
     * @param string $valueType
     * @param int|null $ownerUserId
     * @param array<string,mixed>|null $metadata
     * @return array<string,mixed>
     */
    public function set(string $scope, string $keyName, string $valueText, string $valueType = 'string', ?int $ownerUserId = null, ?array $metadata = null): array
    {
        $existing = $this->get($scope, $keyName);
        $metadataJson = $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        if ($existing !== null) {
            $stmt = $this->pdo->prepare('UPDATE agent_memory SET value_text = :value_text, value_type = :value_type, owner_user_id = :owner_user_id, metadata_json = :metadata_json, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'value_text' => $valueText,
                'value_type' => $valueType,
                'owner_user_id' => $ownerUserId,
                'metadata_json' => $metadataJson,
                'id' => $existing['id'],
            ]);
            return (array)$this->get($scope, $keyName);
        }

        $publicId = 'mem_' . bin2hex(random_bytes(8));
        $stmt = $this->pdo->prepare('INSERT INTO agent_memory (public_id, scope, key_name, value_text, value_type, owner_user_id, metadata_json, created_at, updated_at) VALUES (:public_id, :scope, :key_name, :value_text, :value_type, :owner_user_id, :metadata_json, NOW(), NOW())');
        $stmt->execute([
            'public_id' => $publicId,
            'scope' => $scope,
            'key_name' => $keyName,
            'value_text' => $valueText,
            'value_type' => $valueType,
            'owner_user_id' => $ownerUserId,
            'metadata_json' => $metadataJson,
        ]);

        return (array)$this->get($scope, $keyName);
    }

    /**
     * @param string|null $scope
     * @param string|null $prefix
     * @param int $limit
     * @param int $offset
     * @return array<int,array<string,mixed>>
     */
    public function list(?string $scope = null, ?string $prefix = null, int $limit = 50, int $offset = 0): array
    {
        $conditions = ['1=1'];
        $params = [];

        if ($scope !== null && $scope !== '') {
            $conditions[] = 'scope = :scope';
            $params['scope'] = $scope;
        }

        if ($prefix !== null && $prefix !== '') {
            $conditions[] = 'key_name LIKE :prefix';
            $params['prefix'] = $prefix . '%';
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT public_id, scope, key_name, value_type, metadata_json, created_at, updated_at FROM agent_memory WHERE {$where} ORDER BY updated_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param string $scope
     * @param string $keyName
     * @return bool
     */
    public function delete(string $scope, string $keyName): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM agent_memory WHERE scope = :scope AND key_name = :key_name');
        $stmt->execute(['scope' => $scope, 'key_name' => $keyName]);
        return $stmt->rowCount() > 0;
    }
}
