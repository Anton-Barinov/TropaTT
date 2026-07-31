<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class ModuleBackupManager
{
    private PDO $pdo;
    private string $backupDir;

    public function __construct(PDO $pdo, string $storageBase)
    {
        $this->pdo = $pdo;
        $this->backupDir = rtrim($storageBase, '/') . '/backups/modules';
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Backup all installed modules (files + database).
     * @return string Backup directory path
     */
    public function backupAll(): string
    {
        $ts = date('Y-m-d_Hi00');
        $dir = $this->backupDir . '/' . $ts . '_module_backup';
        @mkdir($dir, 0755, true);

        $meta = ['backup_at' => date('c'), 'modules' => []];
        file_put_contents($dir . '/manifest.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $dir;
    }

    /**
     * Backup a single module.
     * @return string Backup file path
     */
    public function backupModule(string $moduleName, string $moduleDir): string
    {
        $ts = date('Ymd_His');
        $file = $this->backupDir . '/' . $moduleName . '_' . $ts . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create backup: {$file}");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($moduleDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $item */
        foreach ($files as $item) {
            $filePath = $item->getRealPath();
            $relativePath = substr($filePath, strlen($moduleDir) + 1);

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        return $file;
    }

    /**
     * Backup only the database tables of a module.
     * @return string SQL dump file path
     */
    public function backupModuleData(string $moduleName): string
    {
        $ts = date('Ymd_His');
        $file = $this->backupDir . '/' . $moduleName . '_data_' . $ts . '.sql';
        $sql = '';

        $tablePrefix = str_replace('.', '_', $moduleName) . '_';
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE :prefix");
        $stmt->execute(['prefix' => $tablePrefix . '%']);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $sql .= "CREATE TABLE IF NOT EXISTS {$table} (\n";
            $columns = $this->pdo->query("PRAGMA table_info({$table})");
            $colDefs = [];
            while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
                $colDefs[] = "  {$col['name']} {$col['type']}" . ($col['notnull'] ? ' NOT NULL' : '') . ($col['dflt_value'] !== null ? " DEFAULT {$col['dflt_value']}" : '');
            }
            $sql .= implode(",\n", $colDefs) . "\n);\n\n";

            $rows = $this->pdo->query("SELECT * FROM {$table}");
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $this->pdo->quote((string)$v), $row);
                $sql .= "INSERT INTO {$table} VALUES (" . implode(', ', $vals) . ");\n";
            }
            $sql .= "\n";
        }

        file_put_contents($file, $sql);
        return $file;
    }

    /**
     * List available backups.
     * @return array<int, array{name: string, size: int, created: int}>
     */
    public function listBackups(): array
    {
        $backups = [];
        if (!is_dir($this->backupDir)) {
            return $backups;
        }

        $items = scandir($this->backupDir);
        if ($items === false) {
            return $backups;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $this->backupDir . '/' . $item;
            if (is_file($path)) {
                $backups[] = [
                    'name' => $item,
                    'size' => (int)filesize($path),
                    'created' => (int)filemtime($path),
                ];
            }
        }

        return $backups;
    }
}
