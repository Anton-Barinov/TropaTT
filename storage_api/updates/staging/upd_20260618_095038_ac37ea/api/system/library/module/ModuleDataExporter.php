<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class ModuleDataExporter
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Export module data as JSON.
     * @return string JSON string
     */
    public function export(string $moduleName, string $format = 'json'): string
    {
        $data = $this->collectModuleData($moduleName);

        if ($format === 'csv') {
            return $this->toCsv($data);
        }

        return json_encode([
            'module' => $moduleName,
            'exported_at' => date('c'),
            'data' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Import JSON data into a module.
     * @return array{imported: int, errors: array<string>}
     */
    public function import(string $moduleName, string $jsonData): array
    {
        $result = ['imported' => 0, 'errors' => []];

        $parsed = json_decode($jsonData, true);
        if (!is_array($parsed)) {
            $result['errors'][] = 'Invalid JSON';
            return $result;
        }

        $data = $parsed['data'] ?? $parsed;

        foreach ($data as $tableName => $rows) {
            if (!is_array($rows) || $rows === []) {
                continue;
            }

            $tablePrefix = str_replace('.', '_', $moduleName) . '_';
            if (!str_starts_with($tableName, $tablePrefix)) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                try {
                    $columns = array_keys($row);
                    $placeholders = array_map(fn($c) => ':' . $c, $columns);
                    $stmt = $this->pdo->prepare("INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")");
                    $stmt->execute($row);
                    $result['imported']++;
                } catch (\Throwable $e) {
                    $result['errors'][] = "{$tableName}: " . $e->getMessage();
                }
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function collectModuleData(string $moduleName): array
    {
        $data = [];
        $tablePrefix = str_replace('.', '_', $moduleName) . '_';

        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE :prefix");
        $stmt->execute(['prefix' => $tablePrefix . '%']);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            try {
                $rows = $this->pdo->query("SELECT * FROM {$table}");
                $tableData = $rows !== false ? $rows->fetchAll(PDO::FETCH_ASSOC) : [];
                $data[$table] = $tableData;
            } catch (\Throwable) {
            }
        }

        return $data;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $data
     */
    private function toCsv(array $data): string
    {
        $output = '';
        foreach ($data as $tableName => $rows) {
            if ($rows === []) {
                continue;
            }

            $output .= "# Table: {$tableName}\n";
            $columns = array_keys($rows[0]);
            $output .= implode(',', $columns) . "\n";

            foreach ($rows as $row) {
                $vals = array_map(function ($v) {
                    if ($v === null) {
                        return 'NULL';
                    }
                    return '"' . str_replace('"', '""', (string)$v) . '"';
                }, $row);
                $output .= implode(',', $vals) . "\n";
            }
            $output .= "\n";
        }

        return $output;
    }
}
