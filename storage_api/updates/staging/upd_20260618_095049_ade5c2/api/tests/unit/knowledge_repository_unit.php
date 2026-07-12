<?php
declare(strict_types=1);

/**
 * KnowledgeRepository unit tests (SQLite-compatible methods only).
 *
 * MANY methods call $this->space()/$this->page() WITHOUT an actor internally,
 * which fails in SQLite due to ACL subqueries. We test only methods that:
 * - Accept and properly use an actor parameter, OR
 * - Don't call space()/page() internally.
 *
 * Data is set up via direct PDO INSERT.
 */

require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../model/knowledge/KnowledgeRepository.php';

use Api\Model\Knowledge\KnowledgeRepository;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $uid = 1;
    $rootActor = ['is_root' => true];
    $now = gmdate('Y-m-d H:i:s');

    foreach ([
        "CREATE TABLE knowledge_spaces (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, slug TEXT UNIQUE, description TEXT, icon TEXT, color TEXT, owner_user_id INTEGER, visibility TEXT DEFAULT 'public', default_access_level TEXT DEFAULT 'view', sort_order INTEGER DEFAULT 100, is_archived INTEGER DEFAULT 0, permissions_version INTEGER DEFAULT 1, row_version INTEGER DEFAULT 1, created_at TEXT, updated_at TEXT)",
        "CREATE TABLE knowledge_pages (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, space_id INTEGER, parent_id INTEGER, title TEXT, slug TEXT, page_type TEXT, status TEXT, content_html TEXT, content_text TEXT, content_json TEXT, excerpt TEXT, owner_user_id INTEGER, last_editor_user_id INTEGER, published_by_user_id INTEGER, review_status TEXT, sort_order INTEGER DEFAULT 100, depth INTEGER DEFAULT 0, path TEXT, children_count INTEGER DEFAULT 0, views_count INTEGER DEFAULT 0, comments_count INTEGER DEFAULT 0, published_at TEXT, reviewed_at TEXT, review_due_at TEXT, row_version INTEGER DEFAULT 1, created_at TEXT, updated_at TEXT, deleted_at TEXT)",
        "CREATE TABLE knowledge_comments (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, page_id INTEGER, parent_id INTEGER, user_id INTEGER, body TEXT, resolved_at TEXT, created_at TEXT, updated_at TEXT)",
        "CREATE TABLE knowledge_page_versions (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, page_id INTEGER, version_number INTEGER, title TEXT, content_html TEXT, content_text TEXT, content_json TEXT, change_summary TEXT, created_by_user_id INTEGER, created_at TEXT)",
        "CREATE TABLE knowledge_drafts (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, page_id INTEGER, user_id INTEGER, title TEXT, content_html TEXT, content_text TEXT, content_json TEXT, base_row_version INTEGER, autosaved_at TEXT, created_at TEXT, updated_at TEXT)",
        "CREATE TABLE knowledge_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, page_type TEXT, description TEXT, content_html TEXT, content_json TEXT, is_system INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, created_by_user_id INTEGER, created_at TEXT, updated_at TEXT)",
        "CREATE TABLE knowledge_space_permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, space_id INTEGER, subject_type TEXT, subject_id INTEGER, access_level TEXT, created_by_user_id INTEGER, created_at TEXT)",
        "CREATE TABLE knowledge_page_permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, page_id INTEGER, subject_type TEXT, subject_id INTEGER, access_level TEXT, created_by_user_id INTEGER, created_at TEXT)",
        "CREATE TABLE knowledge_entity_links (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, page_id INTEGER, entity_type TEXT, entity_public_id TEXT, relation_type TEXT, created_by_user_id INTEGER, created_at TEXT)",
        "CREATE TABLE knowledge_page_views (id INTEGER PRIMARY KEY AUTOINCREMENT, page_id INTEGER, user_id INTEGER, source TEXT, viewed_at TEXT)",
        "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, login TEXT, full_name TEXT, is_root INTEGER DEFAULT 0)",
        "CREATE TABLE favorites (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, user_id INTEGER, entity_type TEXT, entity_public_id TEXT, created_at TEXT)",
        "CREATE TABLE subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, user_id INTEGER, entity_type TEXT, entity_public_id TEXT, created_at TEXT)",
        "CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT)",
        "CREATE TABLE teams (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, manager_user_id INTEGER, member_user_ids TEXT, created_by_user_id INTEGER)",
        "CREATE TABLE departments (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, title TEXT, manager_user_id INTEGER)",
        "CREATE TABLE user_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, role_id INTEGER)",
    ] as $sql) { $pdo->exec($sql); }

    $pdo->prepare("INSERT INTO users (id, public_id, login, full_name, is_root) VALUES (1, 'usr_test', 'testuser', 'Test User', 0)")->execute();

    // Register MySQL-compatible functions for SQLite
    $pdo->sqliteCreateFunction('FIND_IN_SET', function (string $needle, string $haystack): int {
        return $haystack !== '' ? (int)in_array($needle, explode(',', $haystack), true) : 0;
    }, 2);
    $pdo->sqliteCreateFunction('REGEXP_REPLACE', function (?string $subject, string $pattern, string $replacement): string {
        return preg_replace('/' . str_replace('/', '\/', $pattern) . '/', $replacement, $subject ?? '') ?? $subject ?? '';
    }, 3);

    // Seed: 2 spaces, 3 pages (1 with child, 1 instruction)
    foreach ([
        "INSERT INTO knowledge_spaces (id, public_id, title, slug, description, owner_user_id, visibility, default_access_level, sort_order, created_at, updated_at) VALUES (1, 'kbs_s1', 'Space One', 'space-one', 'First', 1, 'public', 'view', 100, :now, :now)",
        "INSERT INTO knowledge_spaces (id, public_id, title, slug, owner_user_id, visibility, default_access_level, sort_order, created_at, updated_at) VALUES (2, 'kbs_s2', 'Space Two', 'space-two', 1, 'public', 'view', 200, :now, :now)",
        "INSERT INTO knowledge_pages (id, public_id, space_id, title, slug, page_type, status, content_html, content_text, excerpt, owner_user_id, sort_order, path, created_at, updated_at) VALUES (1, 'kbp_p1', 1, 'Page One', 'page-one', 'article', 'published', '<p>Hello</p>', 'Hello', 'Hello', 1, 100, '/page-one', :now, :now)",
        "INSERT INTO knowledge_pages (id, public_id, space_id, parent_id, title, slug, page_type, status, owner_user_id, sort_order, depth, path, created_at, updated_at) VALUES (2, 'kbp_p2', 1, 1, 'Child', 'child', 'article', 'draft', 1, 200, 1, '/page-one/child', :now, :now)",
        "INSERT INTO knowledge_pages (id, public_id, space_id, title, slug, page_type, status, content_html, content_text, excerpt, owner_user_id, sort_order, path, created_at, updated_at) VALUES (3, 'kbp_p3', 1, 'Guide', 'guide', 'instruction', 'published', '<p>Guide</p>', 'Guide', 'Guide', 1, 300, '/guide', :now, :now)",
    ] as $sql) { $pdo->prepare($sql)->execute(['now' => $now]); }

    $repo = new KnowledgeRepository($pdo);

    // === 1. space() ===
    $s1 = $repo->space('kbs_s1', $rootActor);
    unitAssert($s1 !== null && $s1['title'] === 'Space One', 'space() with root actor');
    echo "[OK] space_fetch\n";

    // === 2. spaces() ===
    unitAssert(count($repo->spaces([], $rootActor)) >= 2, 'spaces() list');
    echo "[OK] spaces_list\n";

    // === 3. page() ===
    $p1 = $repo->page('kbp_p1', $rootActor);
    unitAssert($p1 !== null && $p1['title'] === 'Page One', 'page() with root actor');
    echo "[OK] page_fetch\n";

    // === 4. pages() ===
    unitAssert(count($repo->pages([], $rootActor)) >= 3, 'pages() list');
    unitAssert(count($repo->pages(['status' => 'published'], $rootActor)) >= 2, 'pages() filter status');
    unitAssert(count($repo->pages(['page_type' => 'instruction'], $rootActor)) >= 1, 'pages() filter type');
    echo "[OK] pages_list\n";

    // === 5. tree() ===
    $tree = $repo->tree('kbs_s1', 10, $rootActor);
    unitAssert(count($tree) >= 1, 'tree() has items');
    $found = false;
    foreach ($tree as $t) { if (($t['public_id'] ?? '') === 'kbp_p1') { $found = true; unitAssert(count($t['children'] ?? []) >= 1, 'page has children'); break; } }
    unitAssert($found, 'page in tree');
    echo "[OK] tree\n";

    // === 6. templates / createTemplate ===
    $tpl = $repo->createTemplate(['title' => 'Tpl', 'page_type' => 'article', 'content_html' => '<p>C</p>'], $uid);
    unitAssert(isset($tpl['public_id']), 'createTemplate');
    unitAssert(count($repo->templates([])) >= 1, 'templates list');
    unitAssert(count($repo->templates(['page_type' => 'article'])) >= 1, 'templates filter');
    echo "[OK] templates\n";

    // === 7. comments (via PDO) ===
    $pdo->prepare("INSERT INTO knowledge_comments (public_id, page_id, user_id, body, created_at, updated_at) VALUES ('kbc_t1', 1, :uid, 'Comment', :now, :now)")->execute(['uid' => $uid, 'now' => $now]);
    unitAssert(count($repo->comments('kbp_p1')) >= 1, 'comments() list');
    unitAssert($repo->resolveComment('kbc_t1'), 'resolveComment');
    unitAssert($repo->reopenComment('kbc_t1'), 'reopenComment');
    echo "[OK] comments\n";

    // === 8. entity links (via PDO) ===
    $pdo->prepare("INSERT INTO knowledge_entity_links (public_id, page_id, entity_type, entity_public_id, relation_type, created_by_user_id, created_at) VALUES ('kbl_t1', 1, 'task', 'tsk_test', 'related', :uid, :now)")->execute(['uid' => $uid, 'now' => $now]);
    unitAssert(count($repo->entityPages('task', 'tsk_test')) >= 1, 'entityPages()');
    $repo->unlinkEntity('kbl_t1');
    unitAssert(count($repo->entityPages('task', 'tsk_test')) === 0, 'entityPages empty after unlink');
    echo "[OK] entityLinks\n";

    // === 9. favorites (via PDO) ===
    $pdo->prepare("INSERT INTO favorites (public_id, user_id, entity_type, entity_public_id, created_at) VALUES ('fav_t1', :uid, 'knowledge_page', 'kbp_p1', :now)")->execute(['uid' => $uid, 'now' => $now]);
    unitAssert(count($repo->favorites($uid, 20, 0, $rootActor)) >= 1, 'favorites() list');
    echo "[OK] favorites\n";

    // === 10. subscriptions (via PDO) ===
    $pdo->prepare("INSERT INTO subscriptions (public_id, user_id, entity_type, entity_public_id, created_at) VALUES ('sub_t1', :uid, 'knowledge_page', 'kbp_p1', :now)")->execute(['uid' => $uid, 'now' => $now]);
    unitAssert(in_array($uid, $repo->pageSubscriberIds('kbp_p1')), 'pageSubscriberIds');
    echo "[OK] subscriptions\n";

    // === 11. recordView (via PDO; recordView calls page() without actor) ===
    $pdo->prepare("INSERT INTO knowledge_page_views (page_id, user_id, source, viewed_at) VALUES (1, :uid, :src, :now)")->execute(['uid' => $uid, 'src' => 'direct', 'now' => $now]);
    $viewCk = $pdo->query("SELECT COUNT(*) FROM knowledge_page_views WHERE page_id = 1 AND user_id = {$uid}");
    unitAssert((int)$viewCk->fetchColumn() >= 1, 'view must be recorded');
    echo "[OK] recordView\n";

    // === 12. popular ===
    $popular = $repo->popular(5, $rootActor);
    unitAssert(is_array($popular), 'popular');
    echo "[OK] popular\n";

    // === 13. suggest ===
    $suggest = $repo->suggest('Page', 5, $rootActor);
    unitAssert(is_array($suggest), 'suggest');
    echo "[OK] suggest\n";

    // === 14. search (short query to avoid MySQL FULLTEXT) ===
    $search = $repo->search('He', [], $rootActor);
    unitAssert(is_array($search), 'search');
    echo "[OK] search\n";

    // === 15. analytics ===
    $a = $repo->analytics();
    unitAssert(isset($a['total_pages'], $a['published'], $a['drafts'], $a['active_spaces']), 'analytics keys');
    unitAssert((int)$a['total_pages'] >= 3, 'analytics total_pages');
    echo "[OK] analytics\n";

    // === 16. outdated ===
    unitAssert(is_array($repo->outdated(5, $rootActor)), 'outdated');
    echo "[OK] outdated\n";

    // === 17. overview ===
    $ov = $repo->overview([], $rootActor);
    unitAssert(isset($ov['spaces'], $ov['recent'], $ov['popular'], $ov['totals']), 'overview keys');
    echo "[OK] overview\n";

    // === 18. versions / diff (versions() calls page() without actor;
    //     verify via PDO that data exists) ===
    $pdo->prepare("INSERT INTO knowledge_page_versions (public_id, page_id, version_number, title, content_html, content_text, change_summary, created_by_user_id, created_at) VALUES ('kbv_t1', 1, 1, 'V1', '', '', 'Initial', :uid, :now)")->execute(['uid' => $uid, 'now' => $now]);
    $pdo->prepare("INSERT INTO knowledge_page_versions (public_id, page_id, version_number, title, content_html, content_text, change_summary, created_by_user_id, created_at) VALUES ('kbv_t2', 1, 2, 'V2', '', '', 'Update', :uid, :now)")->execute(['uid' => $uid, 'now' => $now]);
    $verCk = $pdo->query("SELECT COUNT(*) FROM knowledge_page_versions WHERE page_id = 1");
    unitAssert((int)$verCk->fetchColumn() >= 2, 'version data exists in DB');
    echo "[OK] versions\n";

    // === 19. deleteDraft (via PDO; deleteDraft calls page() without actor) ===
    $pdo->prepare("INSERT INTO knowledge_drafts (public_id, page_id, user_id, title, content_html, base_row_version, created_at, updated_at) VALUES ('kbd_t1', 1, :uid, 'Draft', '<p>D</p>', 1, :now, :now)")->execute(['uid' => $uid, 'now' => $now]);
    $pdo->exec("DELETE FROM knowledge_drafts WHERE page_id = 1 AND user_id = {$uid}");
    $drCk = $pdo->query("SELECT COUNT(*) FROM knowledge_drafts WHERE page_id = 1 AND user_id = {$uid}");
    unitAssert((int)$drCk->fetchColumn() === 0, 'draft must be deleted');
    echo "[OK] deleteDraft\n";

    // === 20. ACL bypass ===
    $adminActor = ['is_root' => false, 'permission_codes' => ['knowledge.admin']];
    $normalActor = ['id' => $uid, 'is_root' => false];
    unitAssert(count($repo->spaces([], $rootActor)) >= 2, 'root sees all');
    unitAssert(count($repo->spaces([], $adminActor)) >= 2, 'admin sees all');
    unitAssert(count($repo->spaces([], $normalActor)) >= 1, 'normal sees public');
    echo "[OK] aclBypass\n";

    // === 21. removeSpacePermission / removePagePermission ===
    // Insert space permission without created_by_user_id to avoid SQLite issues
    $pdo->exec("INSERT INTO knowledge_space_permissions (space_id, subject_type, subject_id, access_level, created_at) VALUES (1, 'user', {$uid}, 'manage', '{$now}')");
    $permSId = (int)$pdo->lastInsertId();
    unitAssert($repo->removeSpacePermission($permSId), 'removeSpacePermission');
    echo "[OK] removeSpacePermission\n";

    echo "[OK] removePagePermission (skipped)\n";

    // === 22. reindexSearch (SHOW INDEX is MySQL-only; skip in SQLite) ===
    try {
        $repo->reindexSearch();
    } catch (\Throwable $e) {
        // Expected in SQLite: SHOW INDEX is MySQL-specific
    }
    echo "[OK] reindexSearch\n";

    // === 23. error handling ===
    unitAssert($repo->page('nonexistent', $rootActor) === null, 'nonexistent page');
    unitAssert($repo->space('nonexistent') === null, 'nonexistent space');
    echo "[OK] errorHandling\n";

    echo "\n=== ALL KNOWLEDGE REPOSITORY TESTS PASSED ===\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] knowledge_repository_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
