<?php
declare(strict_types=1);

/**
 * TROPATTCRM-635: /api/v1/activity/feed total must stay correct without the
 * slow SELECT COUNT(*) FROM (full UNION …) shape.
 *
 * Why this exists: counting every row of audit+security+request logs through
 * one UNION (with details/payload projected, org scope as OR/EXISTS per row)
 * took 7-13s on the demo stand and tripped the release-gate HTTP timeout.
 * The repository now issues one COUNT(*) per channel — the negative control
 * below fails on any statement that wraps a SELECT in COUNT(*), which is
 * exactly what the old code prepared.
 *
 * Run: php -d auto_prepend_file= api/tests/unit/activity_feed_count_unit.php
 */

require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/database/builder/SqlExecutor.php';
require_once __DIR__ . '/../../model/activity/ActivityRepository.php';

use Api\Model\Activity\ActivityRepository;

final class ActivityFeedRecordingPdo extends PDO
{
    /** @var array<int,string> */
    public array $prepared = [];

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->prepared[] = $query;
        return parent::prepare($query, $options);
    }
}

$passed = 0;
$failed = 0;
$errors = [];

function assertTrue(bool $condition, string $label): void
{
    global $passed, $failed, $errors;
    if ($condition) {
        $passed++;
        echo "  PASS: $label\n";
    } else {
        $failed++;
        $errors[] = "FAIL: $label";
        echo "  FAIL: $label\n";
    }
}

function assertEquals(mixed $expected, mixed $actual, string $label): void
{
    assertTrue($expected === $actual, $label . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}

try {
    $pdo = new ActivityFeedRecordingPdo('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, public_id TEXT, deleted_at TEXT)');
    $pdo->exec('CREATE TABLE organization_memberships (
        id INTEGER PRIMARY KEY,
        organization_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        role_code TEXT
    )');
    $pdo->exec('CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        actor_public_id TEXT,
        entity_type TEXT,
        entity_public_id TEXT,
        action TEXT,
        details TEXT,
        created_at TEXT
    )');
    $pdo->exec('CREATE TABLE security_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        actor_public_id TEXT,
        event_type TEXT,
        ip TEXT,
        user_agent TEXT,
        details TEXT,
        created_at TEXT
    )');
    $pdo->exec('CREATE TABLE request_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        request_id TEXT,
        correlation_id TEXT,
        user_public_id TEXT,
        route TEXT,
        method TEXT,
        status_code INTEGER,
        result_code TEXT,
        duration_ms INTEGER,
        payload TEXT,
        created_at TEXT
    )');

    // Org 1: admin (root actor) + member. Org 2: outsider. Plus a deleted member.
    $pdo->exec("INSERT INTO users (id, public_id, deleted_at) VALUES
        (1, 'usr_admin', NULL),
        (2, 'usr_member', NULL),
        (3, 'usr_outsider', NULL),
        (4, 'usr_gone', '2026-01-01 00:00:00')");
    $pdo->exec("INSERT INTO organization_memberships (organization_id, user_id, role_code) VALUES
        (1, 1, 'owner'),
        (1, 2, 'member'),
        (2, 3, 'owner'),
        (1, 4, 'member')");
    $pdo->exec("INSERT INTO audit_logs (public_id, actor_public_id, entity_type, entity_public_id, action, details, created_at) VALUES
        ('aud_member', 'usr_member', 'task', 'tsk_1', 'update', '{\"k\":1}', '2026-09-01 10:00:00'),
        ('aud_outsider', 'usr_outsider', 'task', 'tsk_2', 'update', '{\"k\":2}', '2026-09-01 11:00:00'),
        ('aud_gone', 'usr_gone', 'task', 'tsk_3', 'update', '{\"k\":3}', '2026-09-01 12:00:00'),
        ('aud_admin', 'usr_admin', 'task', 'tsk_4', 'update', '{\"k\":4}', '2026-09-01 13:00:00')");
    $pdo->exec("INSERT INTO security_logs (public_id, actor_public_id, event_type, details, created_at) VALUES
        ('sec_member', 'usr_member', 'login', '{\"ok\":true}', '2026-09-02 10:00:00'),
        ('sec_outsider', 'usr_outsider', 'login', '{\"ok\":true}', '2026-09-02 11:00:00'),
        ('sec_admin', 'usr_admin', 'login', '{\"ok\":true}', '2026-09-02 12:00:00')");
    $pdo->exec("INSERT INTO request_logs (public_id, user_public_id, route, method, status_code, result_code, payload, created_at) VALUES
        ('req_member', 'usr_member', '/api/v1/tasks', 'GET', 200, 'OK', '[]', '2026-09-03 10:00:00'),
        ('req_outsider', 'usr_outsider', '/api/v1/tasks', 'GET', 200, 'OK', '[]', '2026-09-03 11:00:00'),
        ('req_admin', 'usr_admin', '/api/v1/tasks', 'GET', 200, 'OK', '[]', '2026-09-03 12:00:00'),
        ('req_anon', NULL, '/api/v1/health', 'GET', 200, 'OK', '[]', '2026-09-03 13:00:00')");

    $repo = new ActivityRepository($pdo);

    // --- org-scoped root: admin's own rows + org-1 member; outsider and
    // deleted member excluded; NULL actor excluded by org filter for root.
    echo "=== Root with organization scope ===\n";
    [$items, $total, $page, $limit, $hasMore] = $repo->feed(
        ['include_total' => '1', 'limit' => '50'],
        'usr_admin',
        true,
        1
    );
    // admin: 1 audit + 1 security + 1 request; member: 1+1+1 = 6
    assertEquals(6, $total, 'Org-scoped root totals admin+member rows across all channels');
    assertEquals(6, count($items), 'Org-scoped root returns the same number of items as total on page 1');
    assertEquals(1, $page, 'Page echoes back');
    assertEquals(50, $limit, 'Limit echoes back');
    assertTrue($hasMore === false, 'Page 1 of a 6-row feed has no extra row');

    $channels = array_column($items, 'channel');
    sort($channels);
    assertEquals(
        ['audit', 'audit', 'request', 'request', 'security', 'security'],
        $channels,
        'Items keep their channel labels'
    );
    foreach ($items as $item) {
        assertTrue(
            in_array($item['actor_public_id'], ['usr_admin', 'usr_member'], true),
            'Visible actor ' . $item['actor_public_id'] . ' belongs to org 1'
        );
    }

    // --- negative control: no statement may wrap a SELECT in COUNT(*)
    // (the old UNION count). Per-channel COUNT(*) FROM <table> is allowed.
    $unionCounts = array_values(array_filter(
        $pdo->prepared,
        static fn (string $sql): bool => (bool)preg_match('/COUNT\(\*\)\s+FROM\s*\(/i', $sql)
    ));
    assertEquals([], $unionCounts, 'Negative control: no COUNT(*) FROM (SELECT …) statements were prepared');

    $channelCounts = array_values(array_filter(
        $pdo->prepared,
        static fn (string $sql): bool => (bool)preg_match('/COUNT\(\*\)\s+FROM\s+\w+/i', $sql)
    ));
    assertTrue(count($channelCounts) >= 3, 'Each enabled channel issues its own COUNT(*) query');

    // --- single channel narrows both items and total
    echo "=== Channel filter ===\n";
    $pdo->prepared = [];
    [, $secTotal] = $repo->feed(
        ['include_total' => '1', 'channel' => 'security', 'limit' => '10'],
        'usr_admin',
        true,
        1
    );
    assertEquals(2, $secTotal, 'security channel counts only admin+member security rows');
    assertEquals(2, count(array_filter(
        $pdo->prepared,
        static fn (string $sql): bool => (bool)preg_match('/FROM\s+security_logs/i', $sql)
    )), 'channel=security prepares exactly the security COUNT and the security window');
    assertEquals([], array_values(array_filter(
        $pdo->prepared,
        static fn (string $sql): bool => (bool)preg_match('/FROM\s+(audit_logs|request_logs)/i', $sql)
    )), 'channel=security never touches the audit or request tables');

    // --- include_total=0 must not prepare any COUNT and must return null total
    echo "=== include_total=0 ===\n";
    $pdo->prepared = [];
    [, $fastTotal, , , ] = $repo->feed(
        ['include_total' => '0', 'limit' => '50'],
        'usr_admin',
        true,
        1
    );
    assertEquals(null, $fastTotal, 'include_total=0 answers a null total');
    $fastCounts = array_values(array_filter(
        $pdo->prepared,
        static fn (string $sql): bool => (bool)preg_match('/COUNT\(/i', $sql)
    ));
    assertEquals([], $fastCounts, 'include_total=0 prepares no COUNT statement');

    // --- non-root actor: only own rows, even inside the same org
    echo "=== Non-root actor ===\n";
    [, $memberTotal] = $repo->feed(
        ['include_total' => '1', 'limit' => '50'],
        'usr_member',
        false,
        1
    );
    assertEquals(3, $memberTotal, 'Non-root member only counts their own audit+security+request rows');

    [, $outsiderTotal] = $repo->feed(
        ['include_total' => '1', 'limit' => '50'],
        'usr_outsider',
        false,
        2
    );
    assertEquals(3, $outsiderTotal, 'Outsider root-in-org-2 still sees only own rows when not root');

    // --- legacy install: no organization id → organizationFilter returns null
    // and the root path applies no actor WHERE at all, so NULL-actor rows
    // (req_anon) are visible too: 4 audit + 3 security + 4 request = 11.
    echo "=== Legacy root without organization scope ===\n";
    [, $legacyTotal] = $repo->feed(
        ['include_total' => '1', 'limit' => '50'],
        'usr_admin',
        true,
        null
    );
    assertEquals(11, $legacyTotal, 'Unscoped root totals every row including NULL actors (11)');

    // --- unknown channel falls back to all three (same as channel=all)
    echo "=== Unknown channel value ===\n";
    [, $fallbackTotal] = $repo->feed(
        ['include_total' => '1', 'channel' => 'nope', 'limit' => '50'],
        'usr_admin',
        true,
        1
    );
    assertEquals(6, $fallbackTotal, 'An unknown channel still answers the full org-scoped total');

    echo "\n========== RESULTS ==========\n";
    echo 'Passed: ' . $passed . "\n";
    echo 'Failed: ' . $failed . "\n";
    if ($failed > 0) {
        echo "Failures:\n";
        foreach ($errors as $error) {
            echo '  - ' . $error . "\n";
        }
        exit(1);
    }
    echo "All tests passed!\n";
    exit(0);
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
