<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class ModuleFeatureFlags
{
    private PDO $pdo;
    private string $tableName = 'feature_flags';

    /** @var array<string, array<string, bool>> */
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all feature flags for a module.
     * @return array<string, bool>
     */
    public function getFlags(string $moduleName): array
    {
        if (isset($this->cache[$moduleName])) {
            return $this->cache[$moduleName];
        }

        $flags = [];
        $code = 'module.' . $moduleName;
        try {
            $stmt = $this->pdo->prepare("SELECT code, is_enabled FROM {$this->tableName} WHERE code LIKE :prefix");
            $stmt->execute(['prefix' => $code . '.%']);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $flagName = substr($row['code'], strlen($code) + 1);
                $flags[$flagName] = (bool)($row['is_enabled'] ?? true);
            }
        } catch (\Throwable $e) {
            error_log('[ModuleFeatureFlags::isEnabled] ' . $e->getMessage());
        }

        $this->cache[$moduleName] = $flags;
        return $flags;
    }

    public function isEnabled(string $moduleName, string $flag): bool
    {
        $flags = $this->getFlags($moduleName);
        return $flags[$flag] ?? true;
    }

    public function setFlag(string $moduleName, string $flag, bool $enabled): void
    {
        $code = 'module.' . $moduleName . '.' . $flag;
        $now = date('Y-m-d H:i:s');

        try {
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (public_id, code, is_enabled, created_at, updated_at) VALUES (:pid, :code, :enabled, :now, :now) ON DUPLICATE KEY UPDATE is_enabled = :enabled2, updated_at = :now2");
            $stmt->execute([
                'pid' => 'ff_' . bin2hex(random_bytes(8)),
                'code' => $code,
                'enabled' => $enabled ? 1 : 0,
                'enabled2' => $enabled ? 1 : 0,
                'now' => $now,
                'now2' => $now,
            ]);
        } catch (\Throwable $e) {
            error_log('[ModuleFeatureFlags::setFlag] INSERT failed, trying REPLACE: ' . $e->getMessage());
            try {
                $stmt = $this->pdo->prepare("REPLACE INTO {$this->tableName} (public_id, code, is_enabled, created_at, updated_at) VALUES (:pid, :code, :enabled, :now, :now)");
                $stmt->execute([
                    'pid' => 'ff_' . bin2hex(random_bytes(8)),
                    'code' => $code,
                    'enabled' => $enabled ? 1 : 0,
                    'now' => $now,
                ]);
            } catch (\Throwable $e) {
                error_log('[ModuleFeatureFlags::setEnabled] REPLACE failed: ' . $e->getMessage());
            }
        }

        unset($this->cache[$moduleName]);
    }
}
