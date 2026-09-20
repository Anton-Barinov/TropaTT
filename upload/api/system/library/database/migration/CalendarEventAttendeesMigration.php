<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class CalendarEventAttendeesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260920_000001_calendar_event_attendees';
    }

    public function description(): string
    {
        return 'Create calendar_event_attendees table for relational event attendees';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $dt = match ($driver) {
            'sqlite' => 'TEXT',
            'pgsql' => 'TIMESTAMP WITHOUT TIME ZONE',
            default => 'DATETIME',
        };

        $sql = "CREATE TABLE IF NOT EXISTS calendar_event_attendees (
            event_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'accepted',
            created_at {$dt},
            PRIMARY KEY (event_id, user_id)
        )";
        $pdo->exec($sql);

        try {
            if ($driver === 'sqlite' || $driver === 'pgsql') {
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_calendar_event_attendees_user ON calendar_event_attendees (user_id)");
            } else {
                $pdo->exec("CREATE INDEX idx_calendar_event_attendees_user ON calendar_event_attendees (user_id)");
            }
        } catch (\Throwable $e) {
            // Index already exists
        }
    }
}
