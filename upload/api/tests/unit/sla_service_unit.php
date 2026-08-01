<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/sla/SlaRepository.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';
require_once __DIR__ . '/../../system/library/service/SlaService.php';

use Api\Model\Sla\SlaRepository;
use Api\System\Library\Service\SlaService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE sla_policies (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, response_minutes INTEGER, resolve_minutes INTEGER, escalation_payload TEXT, created_at TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, due_at TEXT NULL, deleted_at TEXT NULL, archived_at TEXT NULL)');

    $repo = new SlaRepository($pdo);
    $service = new SlaService($repo);

    $created = $service->create([
        'title' => 'Unit SLA',
        'response_minutes' => 0,
        'resolve_minutes' => -5,
        'escalation_payload' => ['level' => 'team_lead'],
    ]);

    $policyPublicId = (string)($created['public_id'] ?? '');
    unitAssert($policyPublicId !== '', 'Created SLA policy must have public_id');
    unitAssert((int)($created['response_minutes'] ?? 0) === 1, 'response_minutes must be normalized to >=1');
    unitAssert((int)($created['resolve_minutes'] ?? 0) === 1, 'resolve_minutes must be normalized to >=1');

    $unknown = $service->get('sla_missing');
    unitAssert($unknown === null, 'get() must return null for missing SLA');

    $updated = $service->update($policyPublicId, [
        'title' => 'Unit SLA Updated',
        'response_minutes' => 15,
        'resolve_minutes' => 0,
    ]);
    unitAssert(is_array($updated), 'update() must return updated SLA');
    unitAssert((string)($updated['title'] ?? '') === 'Unit SLA Updated', 'Updated SLA title mismatch');
    unitAssert((int)($updated['response_minutes'] ?? 0) === 15, 'Updated response_minutes mismatch');
    unitAssert((int)($updated['resolve_minutes'] ?? 0) === 1, 'Updated resolve_minutes must be normalized to >=1');

    $past = gmdate('Y-m-d H:i:s', time() - 3600);
    $future = gmdate('Y-m-d H:i:s', time() + 3600);
    $pdo->prepare('INSERT INTO tasks (due_at, deleted_at, archived_at) VALUES (:due_at, NULL, NULL)')->execute(['due_at' => $past]);
    $pdo->prepare('INSERT INTO tasks (due_at, deleted_at, archived_at) VALUES (:due_at, NULL, NULL)')->execute(['due_at' => $future]);
    $pdo->prepare('INSERT INTO tasks (due_at, deleted_at, archived_at) VALUES (:due_at, :deleted_at, NULL)')->execute(['due_at' => $past, 'deleted_at' => gmdate('Y-m-d H:i:s')]);

    $report = $service->report();
    unitAssert((int)($report['policies_total'] ?? 0) >= 1, 'SLA report must include policies_total');
    unitAssert((int)($report['tasks_overdue'] ?? 0) === 1, 'SLA report overdue count mismatch');

    $deleted = $service->delete($policyPublicId);
    unitAssert($deleted === true, 'delete() must return true for existing policy');
    $deletedAgain = $service->delete($policyPublicId);
    unitAssert($deletedAgain === false, 'delete() must return false for missing policy');

    echo "[OK] sla_service_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] sla_service_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
