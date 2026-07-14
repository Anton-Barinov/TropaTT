<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

use Api\Model\Task\TaskActivityRepository;
use Api\System\Library\Service\TaskActivityService;

function statusReasonAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE task_activity_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        task_id INTEGER,
        task_public_id TEXT,
        actor_user_id INTEGER NULL,
        actor_type TEXT,
        actor_public_id TEXT,
        actor_display_name TEXT,
        event_type TEXT,
        field_name TEXT,
        old_value TEXT,
        new_value TEXT,
        old_label TEXT,
        new_label TEXT,
        related_entity_type TEXT,
        related_entity_id INTEGER NULL,
        related_entity_public_id TEXT,
        related_entity_label TEXT,
        message_key TEXT,
        message_text TEXT,
        payload_json TEXT NULL,
        visibility TEXT,
        request_id TEXT,
        source_type TEXT,
        source_ref TEXT,
        created_at TEXT,
        deleted_at TEXT NULL
    )');

    $service = new TaskActivityService(new TaskActivityRepository($pdo));
    $service->recordFieldChanged(
        ['id' => 1, 'public_id' => 'tsk_status_reason'],
        'status_code',
        'new',
        'in_progress',
        ['id' => 7, 'public_id' => 'usr_author', 'full_name' => 'Автор'],
        ['source_type' => 'web', 'status_reason' => 'Получены данные от клиента']
    );

    $row = $pdo->query('SELECT event_type, old_label, new_label, payload_json FROM task_activity_events')->fetch(PDO::FETCH_ASSOC);
    $payload = json_decode((string)($row['payload_json'] ?? ''), true);
    statusReasonAssert(($row['event_type'] ?? '') === 'task.status_changed', 'Status change must be recorded as a task status event');
    statusReasonAssert(($row['old_label'] ?? '') === 'Новая', 'Old status label must be retained');
    statusReasonAssert(($row['new_label'] ?? '') === 'В работе', 'New status label must be retained');
    statusReasonAssert(($payload['reason'] ?? '') === 'Получены данные от клиента', 'Status reason must be stored in the activity payload');

    echo "[OK] task_status_reason_activity_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] task_status_reason_activity_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
