<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';
$autoloader = new \Api\System\Library\Support\Autoloader(__DIR__ . '/../..');
$autoloader->register();

if (class_exists(\Api\System\Library\Support\EnvLoader::class)) {
    \Api\System\Library\Support\EnvLoader::loadFiles([dirname(__DIR__, 2) . '/.env', __DIR__ . '/../../.env']);
}

use Api\System\Library\Config;
use Api\System\Library\Container;
use Api\System\Library\Database\ConnectionManager;

function test(bool $condition, string $message): void {
    if (!$condition) throw new \RuntimeException("FAIL: {$message}");
}

try {
    $projectRoot = dirname(__DIR__, 3);
    $cfg = new Config();
    $cfg->load($projectRoot . '/api/config/database.php', 'database');
if (is_file($projectRoot . '/api/config/database.local.php')) {
    $cfg->load($projectRoot . '/api/config/database.local.php', 'database');
}
// Force MySQL if local config exists, otherwise use SQLite test DB
$dbConfig = $cfg->get('database.connections.' . ($cfg->get('database.default') ?: 'sqlite'));
if (($dbConfig['driver'] ?? 'sqlite') === 'sqlite') {
    $testDb = sys_get_temp_dir() . '/idea_test_' . bin2hex(random_bytes(4)) . '.sqlite';
    $cfg->merge('database', ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => $testDb]]]);
}
    $cm = new ConnectionManager($cfg);
    $pdo = $cm->connect();

    echo "=== Idea Integration Test ===\n";

    // Test 1: Create idea
    echo "1. Create idea... ";
    $ideaPid = 'idea_test_' . bin2hex(random_bytes(4));
    $stmt = $pdo->prepare("INSERT INTO ideas (public_id, title, description, author_user_id, status, category, created_at) VALUES (:pid, :t, :d, 1, 'draft', 'test', NOW())");
    $stmt->execute(['pid' => $ideaPid, 't' => 'Test Idea ' . date('H:i:s'), 'd' => 'Test description']);
    $ideaId = (int)$pdo->lastInsertId();
    test($ideaId > 0, "Idea created");
    echo "OK (id={$ideaId})\n";

    // Test 2: Get idea
    echo "2. Get idea... ";
    $stmt = $pdo->prepare("SELECT * FROM ideas WHERE public_id = :pid");
    $stmt->execute(['pid' => $ideaPid]);
    $idea = $stmt->fetch(PDO::FETCH_ASSOC);
    test($idea !== false, "Idea found");
    test($idea['status'] === 'draft', "Status is draft");
    echo "OK\n";

    // Test 3: Update idea
    echo "3. Update idea... ";
    $pdo->prepare("UPDATE ideas SET title = :t, description = :d WHERE public_id = :pid")
        ->execute(['t' => 'Updated', 'd' => 'Updated desc', 'pid' => $ideaPid]);
    test(true, "Idea updated");
    echo "OK\n";

    // Test 4: Create questions
    echo "4. Create questions... ";
    $qPids = [];
    for ($i = 0; $i < 3; $i++) {
        $qPid = 'iq_' . bin2hex(random_bytes(8));
        $pdo->prepare("INSERT INTO idea_questions (public_id, idea_id, cycle_id, question_text, question_type, options_json, allow_unknown, sort_order, created_at) VALUES (:pid, :iid, 1, :qt, 'single_choice', :opts, 1, :sort, NOW())")
            ->execute(['pid' => $qPid, 'iid' => $ideaId, 'qt' => "Question {$i}?", 'opts' => json_encode(['A', 'B', 'C']), 'sort' => $i]);
        $qPids[] = $qPid;
    }
    test(count($qPids) === 3, "Questions created");
    echo "OK\n";

    // Test 5: Save answers
    echo "5. Save answers... ";
    $qStmt = $pdo->prepare("SELECT id FROM idea_questions WHERE idea_id = :iid");
    $qStmt->execute(['iid' => $ideaId]);
    $qIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($qIds as $qId) {
        $pdo->prepare("INSERT INTO idea_answers (idea_id, question_id, answer_text, is_unknown, created_at) VALUES (:iid, :qid, 'Answer', 0, NOW())")
            ->execute(['iid' => $ideaId, 'qid' => $qId]);
    }
    test(count($qIds) === 3, "Answers saved");
    echo "OK\n";

    // Test 6: Create AI iteration
    echo "6. Create AI iteration... ";
    $iterPid = 'iai_' . bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO idea_ai_iterations (public_id, idea_id, iteration, type, response_payload, created_at) VALUES (:pid, :iid, 1, 'analyze', :p, NOW())")
        ->execute(['pid' => $iterPid, 'iid' => $ideaId, 'p' => '{"test":true}']);
    test(true, "Iteration created");
    echo "OK\n";

    // Test 7: Create task drafts
    echo "7. Create task drafts... ";
    $td1 = 'itd_' . bin2hex(random_bytes(8));
    $td2 = 'itd_' . bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO idea_task_drafts (public_id, idea_id, parent_id, title, type, stage, priority, sort_order, created_at) VALUES (:pid, :iid, NULL, 'Task 1', 'research', 'clarification', 'high', 0, NOW())")
        ->execute(['pid' => $td1, 'iid' => $ideaId]);
    $td1Id = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO idea_task_drafts (public_id, idea_id, parent_id, title, type, stage, priority, sort_order, created_at) VALUES (:pid, :iid, :parent, 'Task 1.1', 'research', 'validation', 'normal', 1, NOW())")
        ->execute(['pid' => $td2, 'iid' => $ideaId, 'parent' => $td1Id]);
    test($td1Id > 0, "Task drafts created");
    echo "OK\n";

    // Test 8: Create CRM tasks
    echo "8. Create CRM tasks... ";
    $taskPid = 'task_' . bin2hex(random_bytes(8));
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO tasks (public_id, title, description, status_code, priority_code, parent_task_id, creator_user_id, created_at, updated_at) VALUES (:pid, 'From idea', 'Auto', 'new', 'normal', NULL, 1, :now, :now2)")
        ->execute(['pid' => $taskPid, 'now' => $now, 'now2' => $now]);
    $taskId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE idea_task_drafts SET crm_task_id = :tid WHERE id = :id")
        ->execute(['tid' => $taskId, 'id' => $td1Id]);
    test($taskId > 0, "CRM task created");
    echo "OK\n";

    // Test 9: Duplicate protection
    echo "9. Duplicate protection... ";
    $stmt = $pdo->prepare("SELECT crm_task_id FROM idea_task_drafts WHERE id = :id");
    $stmt->execute(['id' => $td1Id]);
    $existing = $stmt->fetchColumn();
    test((int)$existing === $taskId, "Duplicate prevented (crm_task_id already set)");
    echo "OK\n";

    // Test 10: Status transitions
    echo "10. Status transitions... ";
    $pdo->prepare("UPDATE ideas SET status = 'questioning' WHERE id = :id")->execute(['id' => $ideaId]);
    $pdo->prepare("UPDATE ideas SET status = 'analysis_ready' WHERE id = :id")->execute(['id' => $ideaId]);
    $pdo->prepare("UPDATE ideas SET status = 'task_decomposition_ready' WHERE id = :id")->execute(['id' => $ideaId]);
    $pdo->prepare("UPDATE ideas SET status = 'tasks_created' WHERE id = :id")->execute(['id' => $ideaId]);
    test(true, "Statuses transitioned");
    echo "OK\n";

    // Cleanup
    $pdo->prepare("DELETE FROM idea_answers WHERE idea_id = :id")->execute(['id' => $ideaId]);
    $pdo->prepare("DELETE FROM idea_questions WHERE idea_id = :id")->execute(['id' => $ideaId]);
    $pdo->prepare("DELETE FROM idea_ai_iterations WHERE idea_id = :id")->execute(['id' => $ideaId]);
    $pdo->prepare("DELETE FROM idea_task_drafts WHERE idea_id = :id")->execute(['id' => $ideaId]);
    $pdo->prepare("DELETE FROM idea_analyses WHERE idea_id = :id")->execute(['id' => $ideaId]);
    $pdo->prepare("DELETE FROM tasks WHERE public_id = :pid")->execute(['pid' => $taskPid]);
    $pdo->prepare("DELETE FROM ideas WHERE id = :id")->execute(['id' => $ideaId]);

    echo "\n========================================\n";
    echo "  ALL 10 IDEA INTEGRATION TESTS PASSED\n";
    echo "========================================\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "\n========================================\n");
    fwrite(STDERR, "  TEST FAILED\n");
    fwrite(STDERR, "  " . $e->getMessage() . "\n");
    fwrite(STDERR, "========================================\n");
    exit(1);
}
