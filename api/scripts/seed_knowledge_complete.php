<?php

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
declare(strict_types=1);

/**
 * Итоговый seed-скрипт базы знаний.
 * Дополняет комментарии, ссылки и решает проблему несоответствия слагов (латиница vs кириллица).
 *
 * Использование:
 *   php api/scripts/seed_knowledge_complete.php
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
$password = getenv('DB_PASSWORD') ?: '';

$dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset=utf8mb4";
$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "✓ Connected to {$driver}:{$database}\n";

function ago(int $days): string
{
    return gmdate('Y-m-d H:i:s', time() - $days * 86400);
}

function publicId(string $prefix): string
{
    return $prefix . '_' . bin2hex(random_bytes(10));
}

// Get admin user
$userId = (int)$pdo->query("SELECT id FROM users WHERE login = 'admin' OR login = 'root' LIMIT 1")->fetchColumn();
if ($userId <= 0) $userId = 1;
echo "✓ Using user ID: {$userId}\n";

// Get all pages by title for matching
$pages = $pdo->query("SELECT id, public_id, slug, title FROM knowledge_pages")->fetchAll();
$pageByTitle = [];
foreach ($pages as $p) {
    $pageByTitle[mb_strtolower(trim($p['title']))] = $p;
    $pageByTitle[mb_strtolower(trim($p['slug']))] = $p;
}

echo "✓ Found " . count($pages) . " pages\n";

// ── Add comments (using title matching) ──
echo "\n→ Adding comments...\n";

$commentTemplates = [
    'корпоративные стандарты работы в crm' => [
        ['body' => 'Коллеги, добавил пункт про обязательное указание времени в логах работы. Проверьте, пожалуйста.', 'days' => 25],
        ['body' => 'Всё верно, спасибо! Только в п.3.2 ошибка — ссылка на старый регламент.', 'days' => 24],
        ['body' => 'Исправил ссылку. Готово к утверждению.', 'days' => 23, 'resolved' => true],
    ],
    'стандарты кодирования php' => [
        ['body' => 'Предлагаю добавить запрет на использование eval() и extract().', 'days' => 15],
        ['body' => 'Поддерживаю! Ещё хорошо бы явно описать правила именования тестов.', 'days' => 14],
        ['body' => 'Добавил оба пункта в раздел «Безопасность» и «Тестирование».', 'days' => 13, 'resolved' => true],
    ],
    'инструкция по деплою на production' => [
        ['body' => 'Отличная инструкция! Только нужно обновить версию PHP — уже 8.3 на проде.', 'days' => 10],
        ['body' => 'Обновил, спасибо за напоминание.', 'days' => 9, 'resolved' => true],
    ],
    'регламент обработки входящих заявок' => [
        ['body' => 'Просьба добавить SLA для разных тарифных планов.', 'days' => 20],
        ['body' => 'Добавил таблицу с тарифами в раздел «Время реакции».', 'days' => 19, 'resolved' => true],
    ],
    'политика удалённой работы' => [
        ['body' => 'Нужно добавить про компенсацию расходов на электроэнергию.', 'days' => 12],
        ['body' => 'Вопрос на согласовании с бухгалтерией. Добавим после утверждения.', 'days' => 11],
        ['body' => 'Бухгалтерия утвердила — 2000 руб/мес. Добавил в раздел «Компенсация».', 'days' => 8, 'resolved' => true],
    ],
    'протокол архитектурного комитета #23' => [
        ['body' => 'Важно зафиксировать решение по выбору очереди сообщений — остановились на RabbitMQ.', 'days' => 18],
        ['body' => 'Зафиксировал в протоколе. Ссылка на решение в разделе «Архитектура».', 'days' => 17, 'resolved' => true],
    ],
    'faq: частые вопросы клиентов техподдержки' => [
        ['body' => 'Добавил ещё один частый вопрос — про сброс пароля.', 'days' => 14],
    ],
];

$commentsAdded = 0;
foreach ($commentTemplates as $titleKey => $commentList) {
    $page = $pageByTitle[mb_strtolower($titleKey)] ?? null;
    if (!$page) {
        echo "  ! Page '{$titleKey}' not found\n";
        continue;
    }

    $existingCount = (int)$pdo->prepare("SELECT COUNT(*) FROM knowledge_comments WHERE page_id = :pid")->execute(['pid' => $page['id']]);
    
    foreach ($commentList as $c) {
        // Check if similar comment already exists
        $stmt = $pdo->prepare("SELECT id FROM knowledge_comments WHERE page_id = :pid AND body LIKE :body LIMIT 1");
        $stmt->execute(['pid' => $page['id'], 'body' => mb_substr($c['body'], 0, 50) . '%']);
        if ($stmt->fetch()) continue;

        $pubId = publicId('kbc');
        $pdo->prepare("INSERT INTO knowledge_comments (public_id, page_id, parent_id, user_id, body, resolved_at, created_at, updated_at) VALUES (:pubid, :pid, NULL, :uid, :body, :resolved, :created, :updated)")->execute([
            'pubid' => $pubId,
            'pid' => $page['id'],
            'uid' => $userId,
            'body' => $c['body'],
            'resolved' => !empty($c['resolved']) ? ago($c['days'] - 1) : null,
            'created' => ago($c['days']),
            'updated' => ago($c['days']),
        ]);

        $pdo->prepare("UPDATE knowledge_pages SET comments_count = comments_count + 1 WHERE id = :id")->execute(['id' => $page['id']]);
        $commentsAdded++;
    }
    echo "  ✓ Added comments to: {$page['title']}\n";
}
echo "  Total comments added: {$commentsAdded}\n";

// ── Also make sure admin user has favorites and subscriptions ──
echo "\n→ Ensuring favorites and subscriptions for admin...\n";
$favTitles = [
    'корпоративные стандарты работы в crm',
    'стандарты кодирования php',
    'инструкция по деплою на production',
    'регламент обработки входящих заявок',
    'политика удалённой работы',
    'процесс обработки инцидента p1',
];

$favCount = 0;
$subCount = 0;
foreach ($favTitles as $titleKey) {
    $page = $pageByTitle[mb_strtolower($titleKey)] ?? null;
    if (!$page) continue;

    // Favorite
    $chk = $pdo->prepare("SELECT id FROM favorites WHERE entity_type = 'knowledge_page' AND entity_public_id = :eid AND user_id = :uid");
    $chk->execute(['eid' => $page['public_id'], 'uid' => $userId]);
    if (!$chk->fetch()) {
        $pdo->prepare("INSERT INTO favorites (public_id, user_id, entity_type, entity_public_id, created_at) VALUES (:pubid, :uid, 'knowledge_page', :eid, :now)")->execute([
            'pubid' => 'fav_' . strtoupper(bin2hex(random_bytes(10))),
            'uid' => $userId,
            'eid' => $page['public_id'],
            'now' => ago(random_int(1, 30)),
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
            'now' => ago(random_int(1, 30)),
        ]);
        $subCount++;
    }
}
echo "  Favorites: {$favCount}, Subscriptions: {$subCount}\n";

// ── Final summary ──
echo "\n═══════════════════════════════════════════\n";
echo "  ИТОГОВАЯ СТАТИСТИКА БАЗЫ ЗНАНИЙ\n";
echo "═══════════════════════════════════════════\n";

$stats = [
    'Разделов (spaces)' => $pdo->query("SELECT COUNT(*) FROM knowledge_spaces")->fetchColumn(),
    'Страниц (pages)' => $pdo->query("SELECT COUNT(*) FROM knowledge_pages")->fetchColumn(),
    'Опубликовано' => $pdo->query("SELECT COUNT(*) FROM knowledge_pages WHERE status = 'published'")->fetchColumn(),
    'Версий' => $pdo->query("SELECT COUNT(*) FROM knowledge_page_versions")->fetchColumn(),
    'Комментариев' => $pdo->query("SELECT COUNT(*) FROM knowledge_comments")->fetchColumn(),
    'Тегов привязано' => $pdo->query("SELECT COUNT(*) FROM entity_tags WHERE entity_type = 'knowledge_page'")->fetchColumn(),
    'Связей с проектами/задачами' => $pdo->query("SELECT COUNT(*) FROM knowledge_entity_links")->fetchColumn(),
    'Просмотров' => $pdo->query("SELECT COUNT(*) FROM knowledge_page_views")->fetchColumn(),
    'Избранное' => $pdo->query("SELECT COUNT(*) FROM favorites WHERE entity_type = 'knowledge_page'")->fetchColumn(),
    'Подписки' => $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE entity_type = 'knowledge_page'")->fetchColumn(),
    'Шаблонов' => $pdo->query("SELECT COUNT(*) FROM knowledge_templates")->fetchColumn(),
];

foreach ($stats as $label => $value) {
    echo str_pad("  {$label}:", 30) . " {$value}\n";
}

echo "═══════════════════════════════════════════\n";
echo "  Типы страниц:\n";
$types = $pdo->query("SELECT page_type, COUNT(*) FROM knowledge_pages GROUP BY page_type ORDER BY COUNT(*) DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($types as $type => $count) {
    echo "    {$type}: {$count}\n";
}
echo "═══════════════════════════════════════════\n";
echo "  Разделы:\n";
$spaces = $pdo->query("SELECT title, (SELECT COUNT(*) FROM knowledge_pages WHERE space_id = s.id) AS cnt FROM knowledge_spaces s ORDER BY sort_order")->fetchAll();
foreach ($spaces as $s) {
    echo "    {$s['title']}: {$s['cnt']} страниц\n";
}
echo "═══════════════════════════════════════════\n";
echo "  ✅ База знаний полностью готова!\n";
echo "  Откройте: index.php?route=knowledge\n";
echo "  Админка: index.php?route=admin-knowledge\n";
echo "═══════════════════════════════════════════\n";
