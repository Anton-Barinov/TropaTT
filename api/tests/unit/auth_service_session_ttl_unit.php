<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/auth/AuthRepository.php';
require_once __DIR__ . '/../../model/common/UserRepository.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/logger/JsonLogger.php';
require_once __DIR__ . '/../../system/library/security/TokenManager.php';
require_once __DIR__ . '/../../system/library/security/PasswordHasher.php';
require_once __DIR__ . '/../../system/library/service/AuthService.php';

use Api\Model\Auth\AuthRepository;
use Api\Model\Common\UserRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Security\PasswordHasher;
use Api\System\Library\Service\AuthService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE user_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT,
        user_id INTEGER,
        token_hash TEXT,
        ip TEXT,
        user_agent TEXT,
        device_fingerprint TEXT,
        device_name TEXT,
        expires_at TEXT,
        created_at TEXT,
        is_active INTEGER DEFAULT 1,
        revoked_at TEXT NULL
    )');

    $pdo->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT,
        login TEXT,
        email TEXT,
        full_name TEXT,
        locale TEXT,
        is_active INTEGER DEFAULT 1,
        is_root INTEGER DEFAULT 0,
        created_by_user_id INTEGER NULL,
        password_hash TEXT,
        auth_token_hash TEXT DEFAULT "",
        deleted_at TEXT NULL
    )');

    // RBAC tables required by normalizeUser() → roleCodesByUserId() / permissionCodesByUserId()
    $pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT, code TEXT)');
    $pdo->exec('CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT)');
    $pdo->exec('CREATE TABLE user_roles (user_id INTEGER, role_id INTEGER)');
    $pdo->exec('CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER)');

    $pdo->exec("INSERT INTO users (id, public_id, login, full_name, locale, is_active, is_root, created_by_user_id, password_hash, deleted_at)
        VALUES (1, 'usr_test1', 'testuser', 'Test User', 'en-gb', 1, 0, NULL, '', NULL)");

    $tokenManager = new TokenManager();
    $passwordHasher = new PasswordHasher();
    $authRepo = new AuthRepository($pdo);
    $userRepo = new UserRepository($pdo);
    $logger = new JsonLogger([]);

    $tokenTtl = 259200;          // 3 days sliding window
    $maxSessionLifetime = 2592000; // 30 days absolute limit

    $service = new AuthService(
        $userRepo,
        $authRepo,
        $passwordHasher,
        $tokenManager,
        $logger,
        $tokenTtl,
        $maxSessionLifetime
    );

    // ── Test 1: Session within absolute lifetime → extends and returns user ──
    $token1 = 'tok_within_lifetime';
    $hash1 = $tokenManager->hash($token1);
    $now = gmdate('Y-m-d H:i:s');
    $futureExpiry = gmdate('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare('INSERT INTO user_sessions (public_id, user_id, token_hash, ip, user_agent, created_at, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute(['ses_within', 1, $hash1, '127.0.0.1', 'PHPUnit', $now, $futureExpiry]);

    $result1 = $service->me($token1);
    unitAssert($result1 !== null, 'Session within absolute lifetime must return user data');
    unitAssert(($result1['user']['login'] ?? '') === 'testuser', 'Returned user login must match');
    unitAssert(($result1['user']['is_root'] ?? true) === false, 'Non-root user must not be root');
    unitAssert(($result1['session_public_id'] ?? '') === 'ses_within', 'Session public_id must match');
    unitAssert(($result1['expires_in'] ?? 0) === $tokenTtl, 'expires_in must equal configured tokenTtl');

    // Verify sliding extension actually updated expires_at in DB
    $extendedCheck = $pdo->prepare('SELECT expires_at FROM user_sessions WHERE token_hash = ?');
    $extendedCheck->execute([$hash1]);
    $extendedRow = $extendedCheck->fetch(PDO::FETCH_ASSOC);
    unitAssert(
        $extendedRow !== false && ($extendedRow['expires_at'] ?? '') > $futureExpiry,
        'Session must be extended past its previous expiry'
    );

    // ── Test 2: Session exceeding absolute lifetime → revokes and returns null ──
    $token2 = 'tok_exceeded_lifetime';
    $hash2 = $tokenManager->hash($token2);
    $oldCreatedAt = gmdate('Y-m-d H:i:s', time() - $maxSessionLifetime - 86400); // 31 days ago
    $stillActiveExpiry = gmdate('Y-m-d H:i:s', time() + 3600); // sliding window would allow

    $pdo->prepare('INSERT INTO user_sessions (public_id, user_id, token_hash, ip, user_agent, created_at, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute(['ses_exceeded', 1, $hash2, '127.0.0.1', 'PHPUnit', $oldCreatedAt, $stillActiveExpiry]);

    $result2 = $service->me($token2);
    unitAssert($result2 === null, 'Session exceeding absolute lifetime must return null');

    // Verify revocation
    $revokedCheck = $pdo->prepare('SELECT revoked_at FROM user_sessions WHERE token_hash = ?');
    $revokedCheck->execute([$hash2]);
    $revokedRow = $revokedCheck->fetch(PDO::FETCH_ASSOC);
    unitAssert(
        $revokedRow !== false && ($revokedRow['revoked_at'] ?? null) !== null,
        'Session exceeding absolute lifetime must be revoked'
    );

    // ── Test 3: Session exactly at boundary (under limit) → still active ──
    $token3 = 'tok_at_boundary';
    $hash3 = $tokenManager->hash($token3);
    $boundaryCreatedAt = gmdate('Y-m-d H:i:s', time() - $maxSessionLifetime + 120); // 30 days - 2 minutes
    $boundaryExpiry = gmdate('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare('INSERT INTO user_sessions (public_id, user_id, token_hash, ip, user_agent, created_at, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute(['ses_boundary', 1, $hash3, '127.0.0.1', 'PHPUnit', $boundaryCreatedAt, $boundaryExpiry]);

    $result3 = $service->me($token3);
    unitAssert($result3 !== null, 'Session at absolute lifetime boundary (under limit) must return user data');

    // ── Test 4: Already revoked session → returns null ──
    $token4 = 'tok_already_revoked';
    $hash4 = $tokenManager->hash($token4);
    $recentCreatedAt = gmdate('Y-m-d H:i:s', time() - 60);
    $recentExpiry = gmdate('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare('INSERT INTO user_sessions (public_id, user_id, token_hash, ip, user_agent, created_at, expires_at, revoked_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute(['ses_revoked', 1, $hash4, '127.0.0.1', 'PHPUnit', $recentCreatedAt, $recentExpiry, $now]);

    $result4 = $service->me($token4);
    unitAssert($result4 === null, 'Already revoked session must return null');

    // ── Test 5: Session with created_at missing/null → does NOT revoke aggressively ──
    $token5 = 'tok_null_created';
    $hash5 = $tokenManager->hash($token5);
    $recentExpiry5 = gmdate('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare('INSERT INTO user_sessions (public_id, user_id, token_hash, ip, user_agent, created_at, expires_at)
        VALUES (?, ?, ?, ?, ?, NULL, ?)')
        ->execute(['ses_null_created', 1, $hash5, '127.0.0.1', 'PHPUnit', $recentExpiry5]);

    $result5 = $service->me($token5);
    unitAssert($result5 !== null, 'Session with missing created_at must still work (graceful degradation)');

    // ── Test 6: Bogus token → returns null ──
    $result6 = $service->me('totally_nonexistent_token_xyz');
    unitAssert($result6 === null, 'Non-existent token must return null');

    echo "[OK] auth_service_session_ttl_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] auth_service_session_ttl_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
