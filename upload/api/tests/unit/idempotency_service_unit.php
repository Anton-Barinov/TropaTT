<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/common/IdempotencyRepository.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/http/Request.php';
require_once __DIR__ . '/../../system/library/http/JsonResponse.php';
require_once __DIR__ . '/../../system/library/service/IdempotencyService.php';

use Api\Model\Common\IdempotencyRepository;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Http\Request;
use Api\System\Library\Service\IdempotencyService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function makeRequest(string $route, string $key, string $requestId = 'rid-1'): Request
{
    return new Request(
        method: 'POST',
        uri: '/api/index.php?route=' . $route,
        path: '/api/index.php',
        query: ['route' => $route],
        post: [],
        cookies: [],
        files: [],
        server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'unit'],
        headers: ['X-Idempotency-Key' => $key],
        rawBody: '{}',
        requestId: $requestId,
        correlationId: $requestId,
        locale: 'ru-ru'
    );
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE idempotency_keys (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT, key_hash TEXT, route TEXT, response_payload TEXT, created_at TEXT)');

    $repo = new IdempotencyRepository($pdo);
    $service = new IdempotencyService($repo);

    $longKey = str_repeat('x', 300);
    $request = makeRequest('api/v1/projects', $longKey);
    $extracted = $service->extractKey($request);
    unitAssert($extracted !== null && strlen($extracted) === 255, 'Idempotency key must be truncated to 255 chars');

    $noReplay = $service->replay($request, ['public_id' => 'usr_unit']);
    unitAssert($noReplay === null, 'Replay must be null when cache is empty');

    $successResponse = JsonResponse::success(
        code: 'PROJECT_CREATED',
        message: 'ok',
        data: ['project' => ['public_id' => 'prj_1']],
        status: 201,
        requestId: 'rid-a',
        correlationId: 'rid-a'
    );

    $service->remember($request, ['public_id' => 'usr_unit'], $successResponse);
    $service->remember($request, ['public_id' => 'usr_unit'], $successResponse);

    $count = (int)$pdo->query('SELECT COUNT(*) FROM idempotency_keys')->fetchColumn();
    unitAssert($count === 1, 'Idempotency remember must not duplicate saved response for same hash');

    $replayed = $service->replay($request, ['public_id' => 'usr_unit']);
    unitAssert($replayed instanceof JsonResponse, 'Replay must return JsonResponse for stored payload');
    $payload = $replayed->payload();
    unitAssert(($payload['success'] ?? false) === true, 'Replayed success response must remain successful');
    unitAssert((string)($payload['code'] ?? '') === 'PROJECT_CREATED', 'Replayed code mismatch');
    unitAssert(($payload['meta']['idempotency_replayed'] ?? false) === true, 'Replay meta flag must be set');

    $requestErr = makeRequest('api/v1/tasks', 'err-key-1', 'rid-2');
    $errorResponse = JsonResponse::error(
        code: 'VALIDATION_ERROR',
        message: 'bad',
        status: 422,
        errors: ['title' => ['required']],
        requestId: 'rid-e',
        correlationId: 'rid-e'
    );
    $service->remember($requestErr, ['public_id' => 'usr_unit'], $errorResponse);

    $replayedErr = $service->replay($requestErr, ['public_id' => 'usr_unit']);
    unitAssert($replayedErr instanceof JsonResponse, 'Error replay must return JsonResponse');
    $payloadErr = $replayedErr->payload();
    unitAssert(($payloadErr['success'] ?? true) === false, 'Replayed error response must stay failed');
    unitAssert((string)($payloadErr['code'] ?? '') === 'VALIDATION_ERROR', 'Replayed error code mismatch');
    unitAssert(($payloadErr['meta']['idempotency_replayed'] ?? false) === true, 'Error replay meta flag must be set');

    echo "[OK] idempotency_service_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] idempotency_service_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
