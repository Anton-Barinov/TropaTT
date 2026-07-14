<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


/**
 * Fix tag linking for knowledge base pages.
 * This script looks up actual slugs from the database and links tags correctly.
 */

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(__DIR__ . '/..');
$autoloader->register();

use Api\System\Library\Support\EnvLoader;

EnvLoader::loadFiles([
    dirname(__DIR__, 2) . '/.env',
    __DIR__ . '/../.env',
    dirname(__DIR__, 2) . '/.env.local',
    __DIR__ . '/../.env.local',
]);

$driver = trim((string)(getenv('DB_CONNECTION') ?: getenv('CRM_DB_DRIVER') ?: 'mysql'));
$host = trim((string)(getenv('DB_HOST') ?: '127.0.0.1'));
$port = (int)(getenv('DB_PORT') ?: 3306);
$database = trim((string)(getenv('DB_DATABASE') ?: 'crm_api'));
$username = trim((string)(getenv('DB_USERNAME') ?: 'root'));
$password = getenv('DB_PASSWORD') ?: trigger_error('DB_PASSWORD environment variable must be set in .env or environment', E_USER_ERROR);

$dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset=utf8mb4";
$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "✓ Connected to {$driver}:{$database}\n";

// Get all pages with their slugs and IDs
$pages = $pdo->query("SELECT id, public_id, slug, title FROM knowledge_pages")->fetchAll();
echo "✓ Found " . count($pages) . " pages\n";

// Get all tags
$tags = $pdo->query("SELECT id, public_id, code, title FROM tags")->fetchAll();
echo "✓ Found " . count($tags) . " tags\n";

// Build mapping: page_id => tags to assign
// We match by keywords in the title since slugs are Cyrillic
$tagAssignments = [
    'CRM' => ['crm', 'reglament', 'по', 'работе', 'crm'],
    'PHP' => ['php', 'кодирования'],
    'DevOps' => ['деплою', 'production', 'резервного', 'инцидента', 'vpn'],
    'Security' => ['безопасность', 'паролей', 'vpn', 'резервного', 'почты'],
    'Продажи' => ['продаж', 'заявок', 'звонка', 'возражениями', 'предложения'],
    'HR' => ['hr', 'онбординг', 'адаптации', 'удалённой', 'сотрудника'],
    'Поддержка' => ['поддержк', 'инцидента', 'баг', 'вопросы клиентов'],
    'Онбординг' => ['онбординг', 'адаптации', 'roadmap'],
    'Архитектура' => ['архитектур', 'микросервисов', 'комитета', 'стратегической'],
    'Регламент' => ['регламент', 'политик', 'стандарты', 'правила'],
    'FAQ' => ['faq', 'вопросы'],
    'Frontend' => ['frontend'],
];

// Map tag title to tag info
$tagByTitle = [];
foreach ($tags as $tag) {
    $tagByTitle[$tag['title']] = $tag;
}

$linked = 0;
foreach ($pages as $page) {
    $title = mb_strtolower($page['title']);
    foreach ($tagAssignments as $tagTitle => $keywords) {
        $match = false;
        foreach ($keywords as $kw) {
            if (mb_strpos($title, mb_strtolower($kw)) !== false) {
                $match = true;
                break;
            }
        }
        if (!$match) continue;
        
        $tagInfo = $tagByTitle[$tagTitle] ?? null;
        if (!$tagInfo) continue;
        
        // Check if already linked
        $chk = $pdo->prepare("SELECT id FROM entity_tags WHERE entity_type = 'knowledge_page' AND entity_public_id = :eid AND tag_id = :tid");
        $chk->execute(['eid' => $page['public_id'], 'tid' => $tagInfo['id']]);
        if ($chk->fetch()) continue;
        
        // Link tag
        $pdo->prepare("INSERT INTO entity_tags (entity_type, entity_public_id, tag_id, created_at) VALUES ('knowledge_page', :eid, :tid, :now)")->execute([
            'eid' => $page['public_id'],
            'tid' => $tagInfo['id'],
            'now' => gmdate('Y-m-d H:i:s'),
        ]);
        $linked++;
        echo "  ✓ Tagged '{$page['title']}' → {$tagTitle}\n";
    }
}

echo "\n✓ Total tag links created: {$linked}\n";

// Also create KB entity links for entity linking
echo "\n→ Creating entity links (projects/tasks)...\n";

// Get some projects
$projects = $pdo->query("SELECT id, public_id, title FROM projects LIMIT 5")->fetchAll();
$tasks = $pdo->query("SELECT id, public_id, title FROM tasks LIMIT 10")->fetchAll();

$linkedEntities = 0;

// Link development/architecture pages to projects
foreach ($pages as $page) {
    $title = mb_strtolower($page['title']);
    $shouldLinkToProject = mb_strpos($title, 'стандарты') !== false 
        || mb_strpos($title, 'деплою') !== false 
        || mb_strpos($title, 'архитектур') !== false;
    
    if ($shouldLinkToProject) {
        foreach ($projects as $proj) {
            $chk = $pdo->prepare("SELECT id FROM knowledge_entity_links WHERE page_id = :pid AND entity_type = 'project' AND entity_public_id = :eid");
            $chk->execute(['pid' => $page['id'], 'eid' => $proj['public_id']]);
            if ($chk->fetch()) continue;
            
            $pdo->prepare("INSERT INTO knowledge_entity_links (public_id, page_id, entity_type, entity_public_id, relation_type, created_by_user_id, created_at) VALUES (:pubid, :pid, 'project', :eid, 'related', NULL, :now)")->execute([
                'pubid' => 'kbl_' . strtoupper(bin2hex(random_bytes(10))),
                'pid' => $page['id'],
                'eid' => $proj['public_id'],
                'now' => gmdate('Y-m-d H:i:s'),
            ]);
            $linkedEntities++;
            echo "  ✓ Linked '{$page['title']}' → project: {$proj['title']}\n";
        }
    }
    
    // Link instruction/runbook pages to tasks
    $shouldLinkToTask = mb_strpos($title, 'инструкция') !== false 
        || mb_strpos($title, 'деплою') !== false 
        || mb_strpos($title, 'incident') !== false
        || mb_strpos($title, 'баг') !== false;
    
    if ($shouldLinkToTask) {
        foreach ($tasks as $task) {
            $chk = $pdo->prepare("SELECT id FROM knowledge_entity_links WHERE page_id = :pid AND entity_type = 'task' AND entity_public_id = :eid");
            $chk->execute(['pid' => $page['id'], 'eid' => $task['public_id']]);
            if ($chk->fetch()) continue;
            
            $pdo->prepare("INSERT INTO knowledge_entity_links (public_id, page_id, entity_type, entity_public_id, relation_type, created_by_user_id, created_at) VALUES (:pubid, :pid, 'task', :eid, 'instruction', NULL, :now)")->execute([
                'pubid' => 'kbl_' . strtoupper(bin2hex(random_bytes(10))),
                'pid' => $page['id'],
                'eid' => $task['public_id'],
                'now' => gmdate('Y-m-d H:i:s'),
            ]);
            $linkedEntities++;
            echo "  ✓ Linked '{$page['title']}' → task: {$task['title']}\n";
        }
    }
}

echo "\n✓ Total entity links created: {$linkedEntities}\n";

// Add admin user favorites and subscriptions
echo "\n→ Adding favorites and subscriptions...\n";
$stmt = $pdo->query("SELECT id FROM users WHERE login = 'admin' OR login = 'root' LIMIT 1");
$userId = (int)$stmt->fetchColumn();
if ($userId <= 0) $userId = 1;

$favCount = 0;
$favTitles = ['Стандарты кодирования PHP', 'Инструкция по деплою на production', 'Регламент обработки входящих заявок', 'Политика удалённой работы', 'Процесс обработки инцидента P1'];
foreach ($pages as $page) {
    foreach ($favTitles as $ft) {
        if (mb_strpos($page['title'], $ft) !== false || $page['title'] === $ft) {
            // Favorite
            $chk = $pdo->prepare("SELECT id FROM favorites WHERE entity_type = 'knowledge_page' AND entity_public_id = :eid AND user_id = :uid");
            $chk->execute(['eid' => $page['public_id'], 'uid' => $userId]);
            if (!$chk->fetch()) {
                $pdo->prepare("INSERT INTO favorites (public_id, user_id, entity_type, entity_public_id, created_at) VALUES (:pubid, :uid, 'knowledge_page', :eid, :now)")->execute([
                    'pubid' => 'fav_' . strtoupper(bin2hex(random_bytes(10))),
                    'uid' => $userId,
                    'eid' => $page['public_id'],
                    'now' => gmdate('Y-m-d H:i:s'),
                ]);
                $favCount++;
            }
            // Subscription
            $chk = $pdo->prepare("SELECT id FROM subscriptions WHERE entity_type = 'knowledge_page' AND entity_public_id = :eid AND user_id = :uid");
            $chk->execute(['eid' => $page['public_id'], 'uid' => $userId]);
            if (!$chk->fetch()) {
                $pdo->prepare("INSERT INTO subscriptions (public_id, user_id, entity_type, entity_public_id, created_at) VALUES (:pubid, :uid, 'knowledge_page', :eid, :now)")->execute([
                    'pubid' => 'sub_' . strtoupper(bin2hex(random_bytes(10))),
                    'uid' => $userId,
                    'eid' => $page['public_id'],
                    'now' => gmdate('Y-m-d H:i:s'),
                ]);
            }
            echo "  ✓ Fav+Sub: {$page['title']}\n";
        }
    }
}

// Summary
echo "\n═══════════════════════════════════════\n";
echo "  FIX SCRIPT COMPLETED\n";
echo "═══════════════════════════════════════\n";

$countSpaces = $pdo->query("SELECT COUNT(*) FROM knowledge_spaces")->fetchColumn();
$countPages = $pdo->query("SELECT COUNT(*) FROM knowledge_pages")->fetchColumn();
$countPublished = $pdo->query("SELECT COUNT(*) FROM knowledge_pages WHERE status = 'published'")->fetchColumn();
$countVersions = $pdo->query("SELECT COUNT(*) FROM knowledge_page_versions")->fetchColumn();
$countComments = $pdo->query("SELECT COUNT(*) FROM knowledge_comments")->fetchColumn();
$countTags = $pdo->query("SELECT COUNT(*) FROM entity_tags WHERE entity_type = 'knowledge_page'")->fetchColumn();
$countLinks = $pdo->query("SELECT COUNT(*) FROM knowledge_entity_links")->fetchColumn();
$countViews = $pdo->query("SELECT COUNT(*) FROM knowledge_page_views")->fetchColumn();
$countFav = $pdo->query("SELECT COUNT(*) FROM favorites WHERE entity_type = 'knowledge_page'")->fetchColumn();

echo "  Разделов:        {$countSpaces}\n";
echo "  Страниц:         {$countPages} (опубл: {$countPublished})\n";
echo "  Версий:          {$countVersions}\n";
echo "  Комментариев:    {$countComments}\n";
echo "  Тегов:           {$countTags}\n";
echo "  Связей:          {$countLinks}\n";
echo "  Просмотров:      {$countViews}\n";
echo "  Избранное:       {$countFav}\n";
echo "═══════════════════════════════════════\n";
echo "  База знаний полностью готова!\n";
echo "═══════════════════════════════════════\n";
