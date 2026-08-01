<?php
declare(strict_types=1);

// Define Psr\Log\LoggerInterface only if not already available
namespace Psr\Log {
    if (!interface_exists('Psr\Log\LoggerInterface', false)) {
        interface LoggerInterface
        {
            public function emergency($message, array $context = []);
            public function alert($message, array $context = []);
            public function critical($message, array $context = []);
            public function error($message, array $context = []);
            public function warning($message, array $context = []);
            public function notice($message, array $context = []);
            public function info($message, array $context = []);
            public function debug($message, array $context = []);
            public function log($level, $message, array $context = []);
        }
    }
}

namespace {
    use Api\Model\Sticky\StickyNoteRepository;
    use Api\Model\Knowledge\KnowledgeRepository;
    use Api\Model\Project\ProjectRepository;
    use Api\Model\Common\UserRepository;
    use Api\Model\Task\TaskRepository;
    use Api\System\Library\Service\StickyNoteService;

    require_once __DIR__ . '/../system/library/support/Autoloader.php';

    $loader = new \Api\System\Library\Support\Autoloader('api');
    $loader->register();

    $pdo = new \PDO('mysql:host=127.0.0.1;port=3306;dbname=crm_api;charset=utf8mb4', 'root', '', [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ]);

    $logger = new \Api\System\Library\Logger\JsonLogger([], []);

    $service = new StickyNoteService(
        new StickyNoteRepository($pdo),
        new KnowledgeRepository($pdo),
        new ProjectRepository($pdo),
        null,
        $logger,
        'test-req'
    );

    $pass = 0;
    $fail = 0;

    function assertEq($expected, $actual, string $msg): void {
        global $pass, $fail;
        if ($expected === $actual) {
            $pass++;
        } else {
            $fail++;
            echo "  FAIL: {$msg}\n";
            echo "    Expected: " . var_export($expected, true) . "\n";
            echo "    Actual:   " . var_export($actual, true) . "\n";
        }
    }

    function assertNotNull($value, string $msg): void {
        global $pass, $fail;
        if ($value !== null) {
            $pass++;
        } else {
            $fail++;
            echo "  FAIL: {$msg} (expected not null)\n";
        }
    }

    function assertTrue($value, string $msg): void {
        global $pass, $fail;
        if ($value) {
            $pass++;
        } else {
            $fail++;
            echo "  FAIL: {$msg} (expected true)\n";
        }
    }

    echo "=== Sticky Note Integration Tests ===\n\n";

    // 0. Cleanup: remove any leftover data from previous runs
    $pdo->exec("DELETE sn FROM sticky_notes sn INNER JOIN users u ON u.id = sn.owner_user_id WHERE u.public_id = 'test_sticky_user'");

    // 1. Setup: find an available user
    echo "--- 1. Setup: find test user ---\n";
    $pdo->exec("INSERT IGNORE INTO users (public_id, login, full_name, created_at, updated_at) VALUES ('test_sticky_user', 'test_sticky', 'Test Sticky User', NOW(), NOW())");
    $stmt = $pdo->prepare("SELECT id, public_id FROM users WHERE public_id = :public_id LIMIT 1");
    $stmt->execute(['public_id' => 'test_sticky_user']);
    $user = $stmt->fetch(\PDO::FETCH_ASSOC);
    assertNotNull($user, 'Test user exists');
    $userId = (int)$user['id'];
    echo "   User ID: {$userId}\n";

    // 2. Create a personal sticky note
    echo "\n--- 2. Create personal sticky note ---\n";
    $note1 = $service->create(['title' => 'Test Note 1', 'body' => 'Hello world', 'color' => 'blue'], $userId);
    assertTrue(!isset($note1['error']), 'Create note 1 succeeded');
    assertEq('blue', $note1['color'] ?? '', 'Note 1 color is blue');
    echo "   Public ID: {$note1['public_id']}\n";

    // 3. Create another note with all fields
    echo "\n--- 3. Create note with all fields ---\n";
    $note2 = $service->create([
        'title' => 'Test Note 2',
        'body' => 'Sticky body content',
        'color' => 'green',
        'background_color' => '#f0f0f0',
        'visibility' => 'shared',
        'is_pinned' => true,
        'context_type' => 'personal',
        'meta_json' => ['source' => 'test'],
    ], $userId);
    assertTrue(!isset($note2['error']), 'Create note 2 succeeded');
    assertEq('shared', $note2['visibility'] ?? '', 'Note 2 visibility is shared');
    assertEq('1', (string)($note2['is_pinned'] ?? 0), 'Note 2 is pinned');

    // 4. List notes
    echo "\n--- 4. List notes ---\n";
    $list = $service->list([], $userId, true);
    assertEq(2, $list['total'] ?? 0, 'List returns 2 notes');
    echo "   Total: {$list['total']}\n";

    // 5. Get note by public ID
    echo "\n--- 5. Get note by public ID ---\n";
    $getResult = $service->get($note1['public_id'], $userId, false);
    assertTrue(!isset($getResult['error']), 'Get note succeeded');
    assertEq('Test Note 1', $getResult['title'] ?? '', 'Note title matches');

    // 6. Update note
    echo "\n--- 6. Update note ---\n";
    $updateResult = $service->update($note1['public_id'], ['title' => 'Updated Note 1', 'color' => 'purple'], $userId, false);
    assertTrue(!isset($updateResult['error']), 'Update note succeeded');
    assertEq('Updated Note 1', $updateResult['title'] ?? '', 'Title updated');
    assertEq('purple', $updateResult['color'] ?? '', 'Color updated');

    // 7. Get non-existent note
    echo "\n--- 7. Get non-existent note ---\n";
    $notFound = $service->get('stn_NONEXISTENT', $userId, false);
    assertEq('STICKY_NOTE_NOT_FOUND', $notFound['error'] ?? '', 'Non-existent note returns error');

    // 8. Access control: different user cannot access private note
    echo "\n--- 8. Access control ---\n";
    $otherUser = $service->get($note1['public_id'], 99999, false);
    assertEq('FORBIDDEN', $otherUser['error'] ?? '', 'Other user cannot access private note');

    // 9. Shared note accessible by other user (list)
    echo "\n--- 9. Shared note visibility ---\n";
    $otherList = $service->list([], 99999, false);
    $sharedVisible = false;
    foreach ($otherList['items'] as $item) {
        if ($item['public_id'] === $note2['public_id']) {
            $sharedVisible = true;
            break;
        }
    }
    assertTrue($sharedVisible, 'Other user can see shared note');

    // 10. Archive note
    echo "\n--- 10. Archive note ---\n";
    $archiveResult = $service->archive($note2['public_id'], $userId, false);
    assertTrue(!isset($archiveResult['error']), 'Archive note succeeded');
    assertTrue($archiveResult['success'] ?? false, 'Archive returned success');

    $listAfterArchive = $service->list([], $userId, false);
    assertEq(1, $listAfterArchive['total'] ?? 0, 'List returns 1 after archive');

    // 11. Unarchive note
    echo "\n--- 11. Unarchive note ---\n";
    $unarchiveResult = $service->unarchive($note2['public_id'], $userId, false);
    assertTrue(!isset($unarchiveResult['error']), 'Unarchive note succeeded');
    $listAfterUnarchive = $service->list([], $userId, false);
    assertEq(2, $listAfterUnarchive['total'] ?? 0, 'List returns 2 after unarchive');

    // 12. Reorder notes
    echo "\n--- 12. Reorder notes ---\n";
    $reorderResult = $service->reorder([
        ['public_id' => $note1['public_id'], 'sort_order' => 100],
        ['public_id' => $note2['public_id'], 'sort_order' => 200],
    ], $userId);
    assertTrue(!isset($reorderResult['error']), 'Reorder succeeded');

    // 13. Delete note
    echo "\n--- 13. Delete note ---\n";
    $deleteResult = $service->delete($note2['public_id'], $userId, false);
    assertTrue(!isset($deleteResult['error']), 'Delete note succeeded');
    $listAfterDelete = $service->list([], $userId, false);
    assertEq(1, $listAfterDelete['total'] ?? 0, 'List returns 1 after delete');

    // 14. Validation: invalid color
    echo "\n--- 14. Validation: invalid color ---\n";
    $invalidColor = $service->create(['title' => 'Bad Color', 'body' => 'x', 'color' => 'invalid'], $userId);
    assertEq('VALIDATION_ERROR', $invalidColor['error'] ?? '', 'Invalid color returns validation error');

    // 15. Validation: body too long
    echo "\n--- 15. Validation: body too long ---\n";
    $longBody = $service->create(['title' => 'Long', 'body' => str_repeat('x', 70000)], $userId);
    assertEq('VALIDATION_ERROR', $longBody['error'] ?? '', 'Body too long returns validation error');

    // 16. Create note with project context (requires valid project)
    echo "\n--- 16. Context validation ---\n";
    $invalidContext = $service->create([
        'title' => 'Project Note',
        'body' => 'test',
        'context_type' => 'project',
        'context_public_id' => 'nonexistent-project',
    ], $userId);
    assertTrue(isset($invalidContext['error']), 'Invalid project context returns error');

    // 17. Reorder with non-owned note (should fail for other user)
    echo "\n--- 17. Reorder access control ---\n";
    $reorderFail = $service->reorder([
        ['public_id' => $note1['public_id'], 'sort_order' => 50],
    ], 99999);
    assertEq('FORBIDDEN', $reorderFail['error'] ?? '', 'Reorder non-owned note returns forbidden');

    // 18. Already converted note check
    echo "\n--- 18. Already converted check ---\n";
    $repo = new StickyNoteRepository($pdo);
    $repo->markConverted($note1['public_id'], [
        'converted_to_entity_type' => 'test',
        'converted_to_entity_public_id' => 'test_123',
        'converted_at' => gmdate('Y-m-d H:i:s'),
        'converted_by_user_id' => $userId,
    ]);
    $convertResult = $service->convertToTask($note1['public_id'], ['title' => 'Test', 'project_public_id' => 'nonexistent'], $userId, true);
    assertEq('STICKY_NOTE_ALREADY_CONVERTED', $convertResult['error'] ?? '', 'Already converted note returns error');

    // 19. Root user bypasses ownership checks
    echo "\n--- 19. Root access ---\n";
    $rootGet = $service->get($note1['public_id'], 99999, true);
    assertTrue(!isset($rootGet['error']), 'Root can access any note');
    assertTrue(isset($rootGet['public_id']), 'Root gets note data');

    // 20. Filter by context type
    echo "\n--- 20. Filter by context type ---\n";
    $note3 = $service->create([
        'title' => 'Dashboard Note',
        'body' => 'on dashboard',
        'context_type' => 'dashboard',
    ], $userId);
    assertTrue(!isset($note3['error']), 'Dashboard note created');
    $filteredList = $service->list(['context_type' => 'dashboard'], $userId, true);
    assertEq(1, $filteredList['total'] ?? 0, 'Filter by dashboard context returns 1');

    // Cleanup
    $stmt = $pdo->prepare("DELETE FROM sticky_notes WHERE owner_user_id = :uid");
    $stmt->execute(['uid' => $userId]);

    echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
    exit($fail > 0 ? 1 : 0);
}
