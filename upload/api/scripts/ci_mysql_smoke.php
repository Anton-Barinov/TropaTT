<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Api\Model\Knowledge\KnowledgeRepository;
use Api\System\Library\App;
use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Database\Migration\MigrationManager;
use Api\System\Library\Database\SchemaManager;
use Api\System\Library\Support\Autoloader;
use Api\System\Library\Support\EnvLoader;

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$basePath = dirname(__DIR__);
$autoloader = new Autoloader($basePath);
$autoloader->register();

EnvLoader::loadFiles([
    dirname($basePath) . '/.env',
    $basePath . '/.env',
    dirname($basePath) . '/.env.local',
    $basePath . '/.env.local',
]);

$config = new Config();
$config->load($basePath . '/config/database.php', 'database');
$config->load($basePath . '/config/install.php', 'install');
$connectionManager = new ConnectionManager($config);
$pdo = $connectionManager->connect();

$driver = (string)($config->get('database.default') ?: '');
if ($driver !== 'mysql') {
    fwrite(STDERR, "[FAIL] MySQL CI smoke test requires DB_CONNECTION=mysql, got: {$driver}\n");
    exit(1);
}

$migrations = new MigrationManager(new SchemaManager());
$firstRun = $migrations->migrateUp($pdo, $driver);
$secondRun = $migrations->migrateUp($pdo, $driver);
$status = $migrations->status($pdo, $driver);

if (($status['pending'] ?? []) !== []) {
    fwrite(STDERR, "[FAIL] Pending migrations remain: " . implode(', ', (array)$status['pending']) . "\n");
    exit(1);
}

$requiredTables = [
    'migrations',
    'users',
    'projects',
    'tasks',
    'knowledge_spaces',
    'knowledge_pages',
    'knowledge_entity_links',
];
$requiredColumns = [
    'import_jobs' => ['attempts', 'dead_letter'],
    'export_jobs' => ['attempts', 'dead_letter'],
    'webhook_deliveries' => ['attempts', 'dead_letter'],
];

$check = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = :table_name'
);
foreach ($requiredTables as $table) {
    $check->execute(['table_name' => $table]);
    if ((int)$check->fetchColumn() !== 1) {
        fwrite(STDERR, "[FAIL] Required table is missing: {$table}\n");
        exit(1);
    }
}

$index = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = :table_name
       AND index_name = :index_name'
);
$index->execute([
    'table_name' => 'knowledge_entity_links',
    'index_name' => 'uq_knowledge_links_page_entity',
]);
if ((int)$index->fetchColumn() < 1) {
    fwrite(STDERR, "[FAIL] Knowledge entity link uniqueness index is missing\n");
    exit(1);
}

$column = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
);
foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $columnName) {
        $column->execute(['table_name' => $table, 'column_name' => $columnName]);
        if ((int)$column->fetchColumn() !== 1) {
            fwrite(STDERR, "[FAIL] Required queue column is missing: {$table}.{$columnName}\n");
            exit(1);
        }
    }
}

// Exercise the authenticated API router in-process. The CI database is
// disposable, and this synthetic actor/session never contains production
// records or credentials.
$fixtureSuffix = strtolower(bin2hex(random_bytes(8)));
$apiToken = 'ci_api_' . $fixtureSuffix;
$fixtureUserPublicId = 'usr_ci_' . $fixtureSuffix;
$fixtureLogin = 'ci_' . $fixtureSuffix;
$fixtureSessionPublicId = 'ses_ci_' . $fixtureSuffix;
$fixtureSpacePublicId = 'kbs_ci_' . $fixtureSuffix;
$fixtureEntityPublicId = 'tsk_ci_' . $fixtureSuffix;
$fixturePagePublicId = '';
$fixtureLinkPublicId = '';
$installLock = (string)$config->get('install.lock_file', '');
$createdInstallLock = false;
if ($installLock !== '' && is_file($installLock)) {
    $existingLock = json_decode((string)file_get_contents($installLock), true);
    if (is_array($existingLock) && ($existingLock['ci_smoke'] ?? false) === true) {
        $createdInstallLock = true;
    }
}
if ($installLock !== '' && !$createdInstallLock && !is_file($installLock)) {
    $lockDirectory = dirname($installLock);
    if (!is_dir($lockDirectory) && !mkdir($lockDirectory, 0775, true) && !is_dir($lockDirectory)) {
        throw new RuntimeException('Unable to create CI smoke install-lock directory');
    }
    $lockPayload = json_encode(['ci_smoke' => true, 'created_at' => gmdate('c')], JSON_THROW_ON_ERROR);
    if (file_put_contents($installLock, $lockPayload) === false) {
        throw new RuntimeException('Unable to create CI smoke install lock');
    }
    $createdInstallLock = true;
}
$now = gmdate('Y-m-d H:i:s');

register_shutdown_function(static function () use (&$pdo, $fixtureUserPublicId, $fixtureSessionPublicId, &$fixtureSpacePublicId, &$fixturePagePublicId, &$fixtureLinkPublicId, $fixtureEntityPublicId, $installLock, $createdInstallLock): void {
    try {
        if ($fixtureLinkPublicId !== '') {
            $stmt = $pdo->prepare('DELETE FROM knowledge_entity_links WHERE public_id = :public_id');
            $stmt->execute(['public_id' => $fixtureLinkPublicId]);
        }
        if ($fixturePagePublicId !== '') {
            foreach (['knowledge_page_views', 'knowledge_comments', 'knowledge_drafts', 'knowledge_page_versions', 'knowledge_page_permissions', 'knowledge_search_index'] as $table) {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE page_id IN (SELECT id FROM knowledge_pages WHERE public_id = :public_id)");
                $stmt->execute(['public_id' => $fixturePagePublicId]);
            }
            $stmt = $pdo->prepare('DELETE FROM knowledge_pages WHERE public_id = :public_id');
            $stmt->execute(['public_id' => $fixturePagePublicId]);
        }
        $stmt = $pdo->prepare('DELETE FROM knowledge_entity_links WHERE entity_public_id = :entity_public_id');
        $stmt->execute(['entity_public_id' => $fixtureEntityPublicId]);
        $stmt = $pdo->prepare('DELETE FROM knowledge_spaces WHERE public_id = :public_id');
        $stmt->execute(['public_id' => $fixtureSpacePublicId]);
        $stmt = $pdo->prepare('DELETE FROM user_sessions WHERE public_id = :public_id');
        $stmt->execute(['public_id' => $fixtureSessionPublicId]);
        $stmt = $pdo->prepare('DELETE FROM users WHERE public_id = :public_id');
        $stmt->execute(['public_id' => $fixtureUserPublicId]);
    } catch (Throwable $e) {
        fwrite(STDERR, '[WARN] CI smoke fixture cleanup failed: ' . $e->getMessage() . "\\n");
    } finally {
        if ($createdInstallLock && $installLock !== '' && is_file($installLock) && !unlink($installLock)) {
            fwrite(STDERR, '[WARN] CI smoke install-lock cleanup failed\\n');
        }
    }
});

$pdo->prepare('INSERT INTO users (public_id, login, email, password_hash, auth_token_hash, full_name, locale, is_active, is_root, created_at, updated_at) VALUES (:public_id, :login, :email, :password_hash, :auth_token_hash, :full_name, :locale, 1, 1, :created_at, :updated_at)')->execute([
    'public_id' => $fixtureUserPublicId,
    'login' => $fixtureLogin,
    'email' => 'ci-' . $fixtureSuffix . '@example.invalid',
    'password_hash' => 'not-a-login-secret',
    'auth_token_hash' => '',
    'full_name' => 'CI Smoke User',
    'locale' => 'en-gb',
    'created_at' => $now,
    'updated_at' => $now,
]);
$ciUserId = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO user_sessions (public_id, user_id, token_hash, ip, user_agent, expires_at, created_at) VALUES (:public_id, :user_id, :token_hash, :ip, :user_agent, :expires_at, :created_at)')->execute([
    'public_id' => $fixtureSessionPublicId,
    'user_id' => $ciUserId,
    'token_hash' => hash('sha256', $apiToken),
    'ip' => '127.0.0.1',
    'user_agent' => 'TropaTT CI smoke',
    'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
    'created_at' => $now,
]);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/auth/me';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $apiToken;
$_SERVER['HTTP_USER_AGENT'] = 'TropaTT CI smoke';
$_GET = [];
$_POST = [];
$_COOKIE = [];
$appResponse = (new App($basePath))->run();
if ($appResponse->status() !== 200 || ($appResponse->payload()['success'] ?? false) !== true) {
    fwrite(STDERR, "[FAIL] Authenticated API smoke route failed\n");
    exit(1);
}
$appPayload = $appResponse->payload();
if ((string)($appPayload['data']['user']['public_id'] ?? '') !== $fixtureUserPublicId) {
    fwrite(STDERR, "[FAIL] Authenticated API smoke route returned the wrong user\n");
    exit(1);
}

// Exercise one real repository flow as a dependency-free API-domain smoke
// test. The CI database is disposable, and this synthetic actor/data never
// contains production records or credentials.
$knowledge = new KnowledgeRepository($pdo);
$space = $knowledge->createSpace([
    'title' => 'CI Integration Space ' . $fixtureSuffix,
    'slug' => 'ci-integration-space-' . $fixtureSuffix,
    'visibility' => 'public',
    'default_access_level' => 'view',
], null);
$rootActor = ['id' => 0, 'is_root' => true, 'permission_codes' => ['*']];
$fixtureSpacePublicId = (string)($space['public_id'] ?? '');
$fixturePage = $knowledge->createPage([
    'space_public_id' => $fixtureSpacePublicId,
    'title' => 'CI integration page',
    'page_type' => 'article',
    'content_html' => '<p>Migration and API-domain smoke test.</p>',
], null, $rootActor);
$fixturePagePublicId = (string)($fixturePage['public_id'] ?? '');
$link = $knowledge->linkEntity($fixturePagePublicId, 'task', $fixtureEntityPublicId, 'related', null);
$fixtureLinkPublicId = (string)($link['public_id'] ?? '');
$repeatLink = $knowledge->linkEntity($fixturePagePublicId, 'task', $fixtureEntityPublicId, 'related', null);
if ($fixtureLinkPublicId === '' || (string)($link['public_id'] ?? '') !== (string)($repeatLink['public_id'] ?? '')) {
    fwrite(STDERR, "[FAIL] Knowledge entity link idempotency check failed\n");
    exit(1);
}
$linkedPages = $knowledge->entityPages('task', $fixtureEntityPublicId);
if (count($linkedPages) !== 1 || (string)($linkedPages[0]['public_id'] ?? '') !== $fixturePagePublicId) {
    fwrite(STDERR, "[FAIL] Knowledge entity link repository flow failed\n");
    exit(1);
}

// Exercise a second authenticated, domain-level API route to cover the
// router/controller/ACL response envelope beyond authentication alone.
$_SERVER['REQUEST_URI'] = '/api/v1/knowledge/spaces';
$domainResponse = (new App($basePath))->run();
$domainPayload = $domainResponse->payload();
if ($domainResponse->status() !== 200 || ($domainPayload['success'] ?? false) !== true || !is_array($domainPayload['data']['items'] ?? null)) {
    fwrite(STDERR, "[FAIL] Authenticated knowledge API smoke route failed\n");
    exit(1);
}

echo "[OK] MySQL schema and API-domain smoke test passed\n";
echo "Migrations applied on first run: " . count($firstRun) . "\n";
echo "Migrations applied on idempotent second run: " . count($secondRun) . "\n";
echo "Required tables verified: " . count($requiredTables) . "\n";
echo "Authenticated API routes verified: GET /api/v1/auth/me and GET /api/v1/knowledge/spaces\n";
echo "Knowledge repository flow verified: create, link, idempotent repeat, lookup\n";
