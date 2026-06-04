<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class ModuleAuditTrail
{
    private PDO $pdo;
    private string $tableName = 'module_audit_log';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function record(string $moduleName, string $action, string $entityType, int $entityId, array $changes = [], ?int $userId = null): void
    {
        $now = date('Y-m-d H:i:s');
        $details = json_encode(['action' => $action, 'changes' => $changes], JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, event_type, event_name, details, user_id, created_at) VALUES (:module, :type, :event, :details, :user, :now)");
            $stmt->execute([
                'module' => $moduleName,
                'type' => $action,
                'event' => $entityType . ':' . $entityId,
                'details' => $details,
                'user' => $userId,
                'now' => $now,
            ]);
        } catch (\Throwable) {
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getHistory(string $moduleName, array $filters = [], int $limit = 100): array
    {
        $where = ['module_name = :module'];
        $params = ['module' => $moduleName];

        if (isset($filters['action'])) {
            $where[] = 'event_type = :action';
            $params['action'] = $filters['action'];
        }

        $sql = "SELECT * FROM {$this->tableName} WHERE " . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT :limit';
        $params['limit'] = $limit;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
