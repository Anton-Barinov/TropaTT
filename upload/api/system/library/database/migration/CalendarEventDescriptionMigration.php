<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class CalendarEventDescriptionMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260424_000001_calendar_event_description';
    }

    public function description(): string
    {
        return 'Add description field to calendar events';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($this->columnExists($pdo, $driver)) {
            return;
        }

        $definition = $driver === 'sqlsrv' ? 'NVARCHAR(MAX) NULL' : 'TEXT NULL';
        $sql = match ($driver) {
            'sqlsrv' => 'ALTER TABLE calendar_events ADD description ' . $definition,
            default => 'ALTER TABLE calendar_events ADD COLUMN description ' . $definition,
        };

        $pdo->exec($sql);
    }

    private function columnExists(PDO $pdo, string $driver): bool
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
                $stmt->execute(['table_name' => 'calendar_events', 'column_name' => 'description']);
                return $stmt->fetchColumn() !== false;
            }

            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
                $stmt->execute(['table_name' => 'calendar_events', 'column_name' => 'description']);
                return $stmt->fetchColumn() !== false;
            }

            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(:table_name) AND name = :column_name');
                $stmt->execute(['table_name' => 'calendar_events', 'column_name' => 'description']);
                return $stmt->fetchColumn() !== false;
            }

            $rows = $pdo->query('PRAGMA table_info(calendar_events)')->fetchAll() ?: [];
            foreach ($rows as $row) {
                if ((string)($row['name'] ?? '') === 'description') {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            error_log('[CalendarEventDescriptionMigration::columnExists] ' . $e->getMessage());
            return false;
        }

        return false;
    }
}
