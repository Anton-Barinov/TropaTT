<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class CalendarEventSourcePrivacyMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260814_000001_calendar_event_source_privacy';
    }

    public function description(): string
    {
        return 'Add source ownership metadata to calendar events for private integrations';
    }

    public function up(PDO $pdo, string $driver): void
    {
        foreach ([
            ['source_type', 'VARCHAR(64) NULL'],
            ['source_owner_user_id', 'INTEGER NULL'],
            ['source_external_id', 'VARCHAR(255) NULL'],
        ] as [$column, $definition]) {
            if ($this->columnExists($pdo, $driver, $column)) {
                continue;
            }

            $sql = $driver === 'sqlsrv'
                ? "ALTER TABLE calendar_events ADD {$column} {$definition}"
                : "ALTER TABLE calendar_events ADD COLUMN {$column} {$definition}";
            $pdo->exec($sql);
        }

        try {
            if ($driver === 'mysql') {
                $pdo->exec('CREATE INDEX idx_calendar_events_source_owner ON calendar_events(source_type, source_owner_user_id)');
            } elseif ($driver === 'sqlite') {
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_calendar_events_source_owner ON calendar_events(source_type, source_owner_user_id)');
            } elseif ($driver === 'pgsql') {
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_calendar_events_source_owner ON calendar_events(source_type, source_owner_user_id)');
            } else {
                $pdo->exec('CREATE INDEX idx_calendar_events_source_owner ON calendar_events(source_type, source_owner_user_id)');
            }
        } catch (\Throwable $e) {
            // Existing index or a driver-specific duplicate error is harmless.
        }
    }

    private function columnExists(PDO $pdo, string $driver, string $column): bool
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
                $stmt->execute(['table_name' => 'calendar_events', 'column_name' => $column]);
                return $stmt->fetchColumn() !== false;
            }
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table_name AND column_name = :column_name LIMIT 1');
                $stmt->execute(['table_name' => 'calendar_events', 'column_name' => $column]);
                return $stmt->fetchColumn() !== false;
            }
            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID(:table_name) AND name = :column_name');
                $stmt->execute(['table_name' => 'calendar_events', 'column_name' => $column]);
                return $stmt->fetchColumn() !== false;
            }
            foreach (($pdo->query('PRAGMA table_info(calendar_events)')->fetchAll() ?: []) as $row) {
                if ((string)($row['name'] ?? '') === $column) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            error_log('[CalendarEventSourcePrivacyMigration::columnExists] ' . $e->getMessage());
        }

        return false;
    }
}
