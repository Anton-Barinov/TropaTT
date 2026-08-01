<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runtimeSqlitePath946(): string
{
    $base = trim((string)getenv('CRM_STORAGE_BASE'));
    if ($base === '') {
        $base = dirname(__DIR__, 3) . '/../storage_api';
    }

    return rtrim($base, '/\\') . '/temp/crm.sqlite';
}

/** @var PDO|null $pdo */
$pdo = null;
$suggestionPublicId = '';

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $sqlitePath = runtimeSqlitePath946();
    assertTrue(is_file($sqlitePath), 'Runtime sqlite file must exist: ' . $sqlitePath);

    $pdo = new PDO('sqlite:' . $sqlitePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $userStmt = $pdo->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
    $userStmt->execute(['public_id' => (string)$root['user_public_id']]);
    $rootUserId = (int)$userStmt->fetchColumn();
    assertTrue($rootUserId > 0, 'Root user id is required for smoke setup');

    $suggestionPublicId = 'aisug_' . strtoupper(bin2hex(random_bytes(8)));
    $now = gmdate('Y-m-d H:i:s');
    $payload = [
        'summary' => 'Estimated minutes clamp test',
        'plan' => [
            ['title' => 'Task A', 'estimated_minutes' => -10],
            ['title' => 'Task B', 'estimated_minutes' => 9999],
            ['title' => 'Task C', 'estimated_minutes' => 'not-a-number'],
            ['title' => 'Task D', 'estimated_minutes' => '45'],
        ],
    ];

    $insert = $pdo->prepare('
        INSERT INTO ai_suggestions (
            public_id,
            intent_code,
            entity_type,
            entity_public_id,
            summary,
            suggestion_json,
            status,
            created_by_user_id,
            confirmed_by_user_id,
            created_at,
            updated_at,
            expires_at
        ) VALUES (
            :public_id,
            :intent_code,
            :entity_type,
            :entity_public_id,
            :summary,
            :suggestion_json,
            :status,
            :created_by_user_id,
            NULL,
            :created_at,
            :updated_at,
            NULL
        )
    ');
    $insert->execute([
        'public_id' => $suggestionPublicId,
        'intent_code' => 'my_day_plan',
        'entity_type' => 'user',
        'entity_public_id' => (string)$root['user_public_id'],
        'summary' => 'Estimated minutes clamp test',
        'suggestion_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'status' => 'draft',
        'created_by_user_id' => $rootUserId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $detail = request('GET', '/api/v1/ai/suggestions/' . $suggestionPublicId, [], $headers);
    assertTrue($detail['status'] === 200, 'Suggestion detail status must be 200');

    $plan = $detail['payload']['data']['suggestion']['payload']['plan'] ?? null;
    assertTrue(is_array($plan), 'Suggestion payload.plan must be an array');

    assertTrue((int)($plan[0]['estimated_minutes'] ?? 0) === 5, 'Negative estimated_minutes must be clamped to lower bound 5');
    assertTrue((int)($plan[1]['estimated_minutes'] ?? 0) === 480, 'Huge estimated_minutes must be clamped to upper bound 480');
    assertTrue((int)($plan[2]['estimated_minutes'] ?? 0) === 30, 'Non-numeric estimated_minutes must fallback to 30');
    assertTrue((int)($plan[3]['estimated_minutes'] ?? 0) === 45, 'In-range numeric estimated_minutes must remain unchanged');

    fwrite(STDOUT, "[OK] ai_estimated_minutes_range_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_estimated_minutes_range_smoke: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    if ($pdo instanceof PDO && $suggestionPublicId !== '') {
        try {
            $cleanup = $pdo->prepare('DELETE FROM ai_suggestions WHERE public_id = :public_id');
            $cleanup->execute(['public_id' => $suggestionPublicId]);
        } catch (Throwable $e) {
            error_log('[AiEstimatedMinutesSmoke] ' . $e->getMessage());
            // Best-effort cleanup for smoke fixture row.
        }
    }
}
