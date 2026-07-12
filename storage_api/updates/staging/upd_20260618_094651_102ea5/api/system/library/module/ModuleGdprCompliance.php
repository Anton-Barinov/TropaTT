<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class ModuleGdprCompliance
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function processesPersonalData(Manifest $manifest): bool
    {
        $gdpr = $this->getGdprConfig($manifest);
        return (bool)($gdpr['processes_personal_data'] ?? false);
    }

    /** @return array<int, array<string, mixed>> */
    public function exportUserData(string $moduleName, int $userId): array
    {
        $data = [];
        $prefix = str_replace('.', '_', $moduleName) . '_';

        try {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE :prefix");
            $stmt->execute(['prefix' => $prefix . '%']);
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                try {
                    $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE user_id = :uid");
                    $stmt->execute(['uid' => $userId]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($rows !== []) {
                        $data[$table] = $rows;
                    }
                } catch (\Throwable) {
                }
            }
        } catch (\Throwable) {
        }

        return $data;
    }

    public function deleteUserData(string $moduleName, int $userId): bool
    {
        $prefix = str_replace('.', '_', $moduleName) . '_';

        try {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE :prefix");
            $stmt->execute(['prefix' => $prefix . '%']);
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                try {
                    $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE user_id = :uid");
                    $stmt->execute(['uid' => $userId]);
                } catch (\Throwable) {
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function getGdprConfig(Manifest $manifest): array
    {
        return [];
    }
}
