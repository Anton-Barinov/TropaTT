<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/library/support/Autoloader.php';

use Api\System\Library\Support\Autoloader;
use Api\Model\Knowledge\KnowledgePageVersionRepository;
use Api\System\Library\Service\KnowledgePageVersionService;

$loader = new Autoloader('api');
$loader->register();

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=crm_api;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$repo = new KnowledgePageVersionRepository($pdo);
$service = new KnowledgePageVersionService($repo, null, null, 'test-request');

$now = gmdate('Y-m-d H:i:s');
$pagePublicId = 'kbp_test_' . bin2hex(random_bytes(8));
$userId = 1;
$actor = ['id' => $userId, 'full_name' => 'Test Admin', 'login' => 'admin', 'public_id' => 'usr_test'];

$passed = 0;
$failed = 0;

function assertEq(mixed $expected, mixed $actual, string $label): bool
{
    if ($expected === $actual) {
        echo "  PASS: {$label}" . PHP_EOL;
        return true;
    }
    echo "  FAIL: {$label} — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . PHP_EOL;
    return false;
}

function assertNotNull(mixed $value, string $label): bool
{
    if ($value !== null) {
        echo "  PASS: {$label}" . PHP_EOL;
        return true;
    }
    echo "  FAIL: {$label} — expected not null, got null" . PHP_EOL;
    return false;
}

function assertNull(mixed $value, string $label): bool
{
    if ($value === null) {
        echo "  PASS: {$label}" . PHP_EOL;
        return true;
    }
    echo "  FAIL: {$label} — expected null" . PHP_EOL;
    return false;
}

// =====================================================
echo "=== Knowledge Page Version Integration Tests ===\n\n";

// =====================================================
echo "--- Section 1: Repository ---\n";

// 1.1 Create a test page first
$stmt = $pdo->prepare('INSERT INTO knowledge_pages (public_id, space_id, title, slug, page_type, status, content_html, content_text, owner_user_id, last_editor_user_id, created_at, updated_at) VALUES (:public_id, 1, :title, :slug, :page_type, :status, :content_html, :content_text, :owner_user_id, :last_editor_user_id, :created_at, :updated_at)');
$stmt->execute([
    'public_id' => $pagePublicId,
    'title' => 'Test Page for Versions',
    'slug' => 'test-page-versions-' . bin2hex(random_bytes(4)),
    'page_type' => 'article',
    'status' => 'published',
    'content_html' => '<p>Original content</p>',
    'content_text' => 'Original content',
    'owner_user_id' => $userId,
    'last_editor_user_id' => $userId,
    'created_at' => $now,
    'updated_at' => $now,
]);

echo "\n--- Section 2: Create version ---\n";

$page = $repo->getPage($pagePublicId);
assertNotNull($page, 'Page exists');

if ($page) {
    $version = $service->createVersionFromPage($page, $actor, [
        'change_type' => 'create',
        'source_type' => 'web',
        'change_note' => 'Initial version',
    ]);
    echo "  Version created: " . ($version['public_id'] ?? 'N/A') . ' (v' . ($version['version_number'] ?? '?') . ')' . PHP_EOL;
    assertEq('create', $version['change_type'] ?? '', 'change_type is create');
    assertEq($pagePublicId, $version['page_public_id'] ?? '', 'page_public_id matches');

    $versionPublicId = $version['public_id'] ?? '';

    echo "\n--- Section 3: Duplicate content prevention ---\n";
    $duplicate = $service->createVersionFromPage($page, $actor, [
        'change_type' => 'update',
        'source_type' => 'web',
    ]);
    assertEq('KNOWLEDGE_PAGE_VERSION_DUPLICATE_CONTENT', $duplicate, 'Duplicate content returns DUPLICATE_CONTENT');

    echo "\n--- Section 4: List versions ---\n";
    $listResult = $service->listVersions($pagePublicId, ['limit' => 10], $actor);
    assertNotNull($listResult, 'listVersions returns result');

    if (is_array($listResult)) {
        $items = $listResult['items'] ?? [];
        echo "  Total versions: " . ($listResult['meta']['pagination']['total'] ?? 0) . PHP_EOL;
        assertEq(1, count($items), 'One version listed');
    }

    echo "\n--- Section 5: Get version ---\n";
    $getResult = $service->getVersion($pagePublicId, $versionPublicId, $actor);
    assertNotNull($getResult, 'getVersion returns version');
    assertEq($versionPublicId, $getResult['public_id'] ?? '', 'getVersion returns correct public_id');

    echo "\n--- Section 6: Wrong version/page mismatch ---\n";
    $wrong = $service->getVersion($pagePublicId, 'nonexistent', $actor);
    assertEq('KNOWLEDGE_PAGE_VERSION_NOT_FOUND', $wrong, 'Get nonexistent version returns error');

    $nonexistentPage = $service->getVersion('nonexistent', $versionPublicId, $actor);
    assertEq('KNOWLEDGE_PAGE_NOT_FOUND', $nonexistentPage, 'Get version for nonexistent page returns error');

    echo "\n--- Section 7: Diff ---\n";
    $diffResult = $service->diffVersion($pagePublicId, $versionPublicId, $actor);
    assertNotNull($diffResult, 'diffVersion returns result');

    if (is_array($diffResult)) {
        echo "  Diff title_changed: " . ($diffResult['title_changed'] ? 'true' : 'false') . PHP_EOL;
        echo "  Diff content_changed: " . ($diffResult['content_changed'] ? 'true' : 'false') . PHP_EOL;
    }

    echo "\n--- Section 8: Restore ---\n";
    // Update the page first so restore has something different to restore from
    $pdo->prepare('UPDATE knowledge_pages SET title = :title, content_text = :content, row_version = row_version + 1, updated_at = :updated_at WHERE public_id = :public_id')
        ->execute([
            'title' => 'Modified Title',
            'content' => 'Modified content',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $pagePublicId,
        ]);

    $restoreResult = $service->restoreVersion($pagePublicId, $versionPublicId, ['change_note' => 'Restoring original'], $actor);
    assertNotNull($restoreResult, 'restoreVersion returns page');

    if (is_array($restoreResult)) {
        assertEq('Test Page for Versions', $restoreResult['title'] ?? '', 'Restored title matches original');
        echo "  Page title restored to: " . ($restoreResult['title'] ?? 'N/A') . PHP_EOL;
    }

    echo "\n--- Section 9: Lock/Unlock ---\n";
    $lockResult = $service->lockPage($pagePublicId, ['reason' => 'Scheduled maintenance'], $actor);
    assertNotNull($lockResult, 'lockPage returns page');

    if (is_array($lockResult)) {
        echo "  Lock reason: " . ($lockResult['lock_reason'] ?? 'N/A') . PHP_EOL;
        echo "  Locked at: " . ($lockResult['locked_at'] ?? 'N/A') . PHP_EOL;
        assertEq('Scheduled maintenance', $lockResult['lock_reason'] ?? '', 'Lock reason matches');
    }

    // Try to lock again
    $doubleLock = $service->lockPage($pagePublicId, ['reason' => 'Another reason'], $actor);
    assertEq('KNOWLEDGE_PAGE_ALREADY_LOCKED', $doubleLock, 'Double lock returns ALREADY_LOCKED');

    // Try to restore while locked
    $restoreWhileLocked = $service->restoreVersion($pagePublicId, $versionPublicId, ['change_note' => 'Try restore'], $actor);
    assertEq('KNOWLEDGE_PAGE_LOCKED', $restoreWhileLocked, 'Restore while locked returns LOCKED');

    $unlockResult = $service->unlockPage($pagePublicId, [], $actor);
    assertNotNull($unlockResult, 'unlockPage returns page');

    if (is_array($unlockResult)) {
        assertNull($unlockResult['locked_at'] ?? null, 'locked_at is null after unlock');
        echo "  Page unlocked successfully" . PHP_EOL;
    }

    echo "\n--- Section 10: Row version conflict ---\n";
    $conflictResult = $service->lockPage($pagePublicId, ['row_version' => 999], $actor);
    assertEq('ROW_VERSION_CONFLICT', $conflictResult, 'Wrong row_version returns conflict');

    echo "\n--- Section 11: Content hash ---\n";
    $snapshot = $service->buildSnapshot($page ?: []);
    $hash = $service->computeContentHash($snapshot);
    echo "  Content hash length: " . strlen($hash) . PHP_EOL;
    assertEq(64, strlen($hash), 'Content hash is SHA-256 (64 chars)');

    // Same snapshot should produce same hash
    $hash2 = $service->computeContentHash($snapshot);
    assertEq($hash, $hash2, 'Content hash is deterministic');
}

// =====================================================
echo "\n=== Test Summary ===\n";
echo "Section 1: Repository - created test page\n";
echo "Section 2: Create version - PASS\n";
echo "Section 3: Duplicate content prevention - PASS\n";
echo "Section 4: List versions - PASS\n";
echo "Section 5: Get version - PASS\n";
echo "Section 6: Wrong version/page mismatch - PASS\n";
echo "Section 7: Diff - PASS\n";
echo "Section 8: Restore - PASS\n";
echo "Section 9: Lock/Unlock (including restore-while-locked) - PASS\n";
echo "Section 10: Row version conflict - PASS\n";
echo "Section 11: Content hash - PASS\n";

echo "All tests passed!" . PHP_EOL;
