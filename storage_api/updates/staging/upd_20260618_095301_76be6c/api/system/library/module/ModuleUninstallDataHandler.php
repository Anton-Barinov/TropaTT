<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;
use RuntimeException;

final class ModuleUninstallDataHandler
{
    public const ARCHIVE = 'archive';
    public const DELETE = 'delete';
    public const KEEP = 'keep';

    private string $archiveDir;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ModuleBackupManager $backup,
        string $storageBase,
    ) {
        $this->archiveDir = rtrim($storageBase, '/') . '/archives/modules';
        if (!is_dir($this->archiveDir)) {
            @mkdir($this->archiveDir, 0755, true);
        }
    }

    public function handleUninstall(string $moduleName, string $strategy = self::ARCHIVE): void
    {
        if ($strategy === self::ARCHIVE) {
            $this->backup->backupModuleData($moduleName);
            $this->dropModuleTables($moduleName);
        } elseif ($strategy === self::DELETE) {
            $this->dropModuleTables($moduleName);
        }
    }

    public function restoreFromArchive(string $moduleName): bool
    {
        $ts = date('Ymd_His');
        $archiveFile = $this->archiveDir . '/' . $moduleName . '_data_' . $ts . '.sql';

        if (!is_file($archiveFile)) {
            return false;
        }

        $sql = file_get_contents($archiveFile);
        if ($sql === false || trim($sql) === '') {
            return false;
        }

        $this->pdo->exec($sql);
        return true;
    }

    private function dropModuleTables(string $moduleName): void
    {
        $tablePrefix = str_replace('.', '_', $moduleName) . '_';
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE :prefix");
        $stmt->execute(['prefix' => $tablePrefix . '%']);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
