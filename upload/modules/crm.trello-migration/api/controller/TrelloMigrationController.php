<?php
declare(strict_types=1);

namespace Module\Crm\TrelloMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\TrelloMigration\Repository\TrelloMigrationRepository;
use Module\Crm\TrelloMigration\Service\EncryptionService;
use Module\Crm\TrelloMigration\Service\TrelloClient;
use Module\Crm\TrelloMigration\Service\TrelloCrawler;
use Module\Crm\TrelloMigration\Service\TrelloImportService;
use Module\Crm\TrelloMigration\Service\TrelloTargetWriter;
use PDO;
use RuntimeException;

final class TrelloMigrationController
{
    private PDO $pdo;
    private TrelloMigrationRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->pdo = $container->get('db.pdo');
        $this->repo = new TrelloMigrationRepository($this->pdo);
    }

    private function body(): array
    {
        $request = $this->container->get('request');
        $raw = (string)($request->rawBody ?? '');
        $decoded = $raw !== '' ? json_decode($raw, true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    private function actor(): array
    {
        return (array)(($this->container->get('auth_user'))['user'] ?? []);
    }

    private function actorId(): int
    {
        return (int)($this->actor()['id'] ?? 0);
    }

    private function can(string $permission): bool
    {
        $actor = $this->actor();
        return !empty($actor['is_root']) || in_array('*', (array)($actor['permission_codes'] ?? []), true) || in_array($permission, (array)($actor['permission_codes'] ?? []), true);
    }

    private function connection(string $publicId): ?array
    {
        return $this->repo->getConnection($publicId);
    }

    private function requireConnection(string $publicId): array|JsonResponse
    {
        $connection = $this->connection($publicId);
        if (!$connection) return JsonResponse::error('NOT_FOUND', 'Trello connection not found', 404);
        if (!$this->can('module.trello-migration.manage') && (int)$connection['created_by_user_id'] !== $this->actorId()) return JsonResponse::error('FORBIDDEN', 'Connection access denied', 403);
        return $connection;
    }

    private function requireJob(string $publicId): array|JsonResponse
    {
        $job = $this->repo->getJob($publicId);
        if (!$job) return JsonResponse::error('NOT_FOUND', 'Trello migration job not found', 404);
        if (!$this->can('module.trello-migration.manage') && (int)$job['created_by_user_id'] !== $this->actorId()) return JsonResponse::error('FORBIDDEN', 'Migration job access denied', 403);
        return $job;
    }

    public function listConnections(): JsonResponse
    {
        return JsonResponse::success('TRELLO_CONNECTIONS_LIST', 'OK', ['connections' => array_map([$this, 'publicConnection'], $this->repo->listConnections($this->actorId(), $this->can('module.trello-migration.manage')))]);
    }

    public function createConnection(): JsonResponse
    {
        return $this->withIdempotency(fn(): JsonResponse => $this->createConnectionInternal());
    }

    private function createConnectionInternal(): JsonResponse
    {
        $input = $this->body();
        $name = trim((string)($input['name'] ?? ''));
        $apiKey = trim((string)($input['api_key'] ?? ''));
        $token = trim((string)($input['token'] ?? $input['user_token'] ?? ''));
        if ($name === '' || $apiKey === '' || $token === '') return JsonResponse::error('VALIDATION_ERROR', 'name, api_key and token are required', 422);
        $connection = $this->repo->createConnection(['name' => mb_substr($name, 0, 255), 'api_key_encrypted' => EncryptionService::encrypt($apiKey), 'token_encrypted' => EncryptionService::encrypt($token), 'api_secret_encrypted' => !empty($input['api_secret']) ? EncryptionService::encrypt((string)$input['api_secret']) : null, 'created_by_user_id' => $this->actorId()]);
        // Validate credentials immediately so a saved connection cannot remain
        // in an apparently usable draft state with an invalid key/token.
        $connectionOk = false;
        try {
            $client = new TrelloClient($this->repo);
            $client->setConnectionId((int)$connection['id']);
            $client->test($apiKey, $token);
            $connectionOk = true;
            $this->repo->updateConnectionCheck((string)$connection['public_id'], true);
        } catch (\Throwable) {
            $this->repo->updateConnectionCheck((string)$connection['public_id'], false, 'Trello connection test failed');
        }
        $connection = $this->connection((string)$connection['public_id']) ?? $connection;
        if (!$connectionOk) {
            return JsonResponse::error('TRELLO_CONNECTION_TEST_FAILED', 'Connection was saved but Trello credentials could not be verified', 422, ['connection' => $this->publicConnection($connection)]);
        }
        return JsonResponse::success('TRELLO_CONNECTION_CREATED', 'Connection created', ['connection' => $this->publicConnection($connection)], 201);
    }

    public function getConnection(array $params): JsonResponse
    {
        $result = $this->requireConnection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        return JsonResponse::success('TRELLO_CONNECTION', 'OK', ['connection' => $this->publicConnection($result)]);
    }

    public function updateConnection(array $params): JsonResponse
    {
        $result = $this->requireConnection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        $input = $this->body();
        $update = [];
        if (array_key_exists('name', $input)) $update['name'] = mb_substr(trim((string)$input['name']), 0, 255);
        if (!empty($input['api_key'])) $update['api_key_encrypted'] = EncryptionService::encrypt((string)$input['api_key']);
        if (!empty($input['token'])) $update['token_encrypted'] = EncryptionService::encrypt((string)$input['token']);
        if (array_key_exists('api_secret', $input)) $update['api_secret_encrypted'] = $input['api_secret'] === '' ? null : EncryptionService::encrypt((string)$input['api_secret']);
        $this->repo->updateConnection((string)$params['public_id'], $update);
        return JsonResponse::success('TRELLO_CONNECTION_UPDATED', 'Connection updated', ['connection' => $this->publicConnection($this->connection((string)$params['public_id']) ?? [])]);
    }

    public function deleteConnection(array $params): JsonResponse
    {
        $result = $this->requireConnection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        if ($this->repo->hasRunningJobs((int)$result['id'])) return JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS', 'Cancel running jobs before deleting the connection', 409);
        $this->repo->deleteConnection((int)$result['id']);
        return JsonResponse::success('TRELLO_CONNECTION_DELETED', 'Connection deleted');
    }

    public function testConnection(array $params): JsonResponse
    {
        $result = $this->requireConnection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        $key = EncryptionService::decrypt((string)$result['api_key_encrypted']);
        $token = EncryptionService::decrypt((string)$result['token_encrypted']);
        if ($key === null || $token === null) return JsonResponse::error('TRELLO_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try {
            $client = new TrelloClient($this->repo);
            $client->setConnectionId((int)$result['id']);
            $me = $client->test($key, $token);
            $this->repo->updateConnectionCheck((string)$params['public_id'], true);
            return JsonResponse::success('TRELLO_CONNECTION_TEST_OK', 'Connection successful', ['user' => $me]);
        } catch (\Throwable $e) {
            $this->repo->updateConnectionCheck((string)$params['public_id'], false, 'Trello connection test failed');
            return JsonResponse::error('TRELLO_CONNECTION_TEST_FAILED', 'Trello connection test failed', 400);
        }
    }

    public function discover(array $params): JsonResponse
    {
        $result = $this->requireConnection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        $key = EncryptionService::decrypt((string)$result['api_key_encrypted']);
        $token = EncryptionService::decrypt((string)$result['token_encrypted']);
        if ($key === null || $token === null) return JsonResponse::error('TRELLO_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try {
            $client = new TrelloClient($this->repo);
            $client->setConnectionId((int)$result['id']);
            $boards = $client->boards($key, $token);
            foreach ($boards as $board) {
                foreach ($client->members($key, $token, (string)$board['id']) as $member) $this->repo->upsertUserMapping((int)$result['id'], $member);
            }
            return JsonResponse::success('TRELLO_DISCOVERY_COMPLETE', 'Boards discovered', ['boards' => $boards, 'board_configs' => $this->repo->listBoardConfigs((int)$result['id']), 'user_mappings' => $this->repo->listUserMappings((int)$result['id'])]);
        } catch (\Throwable) {
            return JsonResponse::error('TRELLO_DISCOVERY_FAILED', 'Trello discovery failed', 400);
        }
    }

    public function listUserMappings(array $params): JsonResponse
    {
        $result = $this->requireConnection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        return JsonResponse::success('TRELLO_USER_MAPPINGS', 'OK', ['items' => $this->repo->listUserMappings((int)$result['id'])]);
    }

    public function updateUserMapping(array $params): JsonResponse
    {
        $result = $this->requireConnection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        $input = $this->body();
        $crmUserPublicId = !empty($input['crm_user_public_id']) ? (string)$input['crm_user_public_id'] : null;
        if ($crmUserPublicId !== null && $this->repo->activeUserPublicId($crmUserPublicId) === null) {
            return JsonResponse::error('USER_NOT_FOUND', 'Active CRM user not found', 404);
        }
        if (!$this->repo->updateUserMapping((int)$result['id'], (int)($params['mapping_id'] ?? 0), $crmUserPublicId)) {
            return JsonResponse::error('MAPPING_NOT_FOUND', 'Trello user mapping not found', 404);
        }
        return JsonResponse::success('TRELLO_USER_MAPPING_UPDATED', 'Mapping updated');
    }

    public function listBoardConfigs(array $params): JsonResponse
    {
        $result = $this->requireConnection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        return JsonResponse::success('TRELLO_BOARD_CONFIGS', 'OK', ['items' => $this->repo->listBoardConfigs((int)$result['id'])]);
    }

    public function saveBoardConfig(array $params): JsonResponse
    {
        $result = $this->requireConnection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        $input = $this->body();
        $config = $this->repo->saveBoardConfig((int)$result['id'], (string)$params['board_id'], ['board_name' => $input['board_name'] ?? null, 'target_project_public_id' => $input['target_project_public_id'] ?? null, 'list_mapping' => (array)($input['list_mapping'] ?? []), 'options' => (array)($input['options'] ?? [])]);
        return JsonResponse::success('TRELLO_BOARD_CONFIG_SAVED', 'Board configuration saved', ['config' => $config]);
    }

    public function listJobs(): JsonResponse
    {
        return JsonResponse::success('TRELLO_JOBS_LIST', 'OK', ['items' => $this->repo->listJobs($this->actorId(), $this->can('module.trello-migration.manage'))]);
    }

    public function createJob(): JsonResponse
    {
        return $this->withIdempotency(fn(): JsonResponse => $this->createJobInternal());
    }

    private function createJobInternal(): JsonResponse
    {
        $input = $this->body();
        $connectionId = trim((string)($input['connection_public_id'] ?? ''));
        $connection = $this->requireConnection($connectionId);
        if ($connection instanceof JsonResponse) return $connection;
        $mode = (string)($input['mode'] ?? 'import');
        if (!in_array($mode, ['import', 'sync', 'dry_run'], true)) return JsonResponse::error('VALIDATION_ERROR', 'mode must be import, sync or dry_run', 422);
        $job = $this->repo->createJob(['connection_id' => (int)$connection['id'], 'mode' => $mode, 'source_scope' => ['board_ids' => array_values(array_filter(array_map('strval', (array)($input['board_ids'] ?? [])))), 'max_cards' => max(0, (int)($input['max_cards'] ?? 0))], 'options' => (array)($input['options'] ?? []), 'created_by_user_id' => $this->actorId()]);
        return JsonResponse::success('TRELLO_JOB_CREATED', 'Job created', ['job' => $job], 201);
    }

    public function getJob(array $params): JsonResponse
    {
        $result = $this->requireJob((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        return JsonResponse::success('TRELLO_JOB', 'OK', ['job' => $result]);
    }

    public function startJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'queued', 'TRELLO_JOB_QUEUED'); }
    public function pauseJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'pausing', 'TRELLO_JOB_PAUSING'); }
    public function resumeJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'queued', 'TRELLO_JOB_RESUMED'); }
    public function cancelJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'cancelling', 'TRELLO_JOB_CANCELLING'); }

    public function retryFailed(array $params): JsonResponse
    {
        $result = $this->requireJob((string)$params['public_id']);
        if ($result instanceof JsonResponse) return $result;
        $count = $this->repo->resetFailedItems((string)$params['public_id']);
        $this->repo->requestStatus((string)$params['public_id'], 'queued');
        return JsonResponse::success('TRELLO_JOB_RETRY_QUEUED', 'Failed items queued for retry', ['reset_items' => $count]);
    }

    public function rollbackJob(array $params): JsonResponse
    {
        $result = $this->requireJob((string)$params['public_id']);
        if ($result instanceof JsonResponse) return $result;
        try {
            $this->buildImportService()->rollback((string)$params['public_id'], $this->actor());
            return JsonResponse::success('TRELLO_JOB_ROLLED_BACK', 'Job targets rolled back using soft-delete policy');
        } catch (\Throwable) {
            return JsonResponse::error('TRELLO_ROLLBACK_FAILED', 'Rollback failed; inspect the migration log', 409);
        }
    }

    public function listJobItems(array $params): JsonResponse
    {
        $result = $this->requireJob((string)$params['public_id']);
        if ($result instanceof JsonResponse) return $result;
        $input = $this->container->get('request')->allInput();
        return JsonResponse::success('TRELLO_JOB_ITEMS', 'OK', ['items' => $this->repo->items((int)$result['id'], !empty($input['status']) ? (string)$input['status'] : null, max(1, min(1000, (int)($input['limit'] ?? 200))))]);
    }

    public function listJobLogs(array $params): JsonResponse
    {
        $result = $this->requireJob((string)$params['public_id']);
        if ($result instanceof JsonResponse) return $result;
        return JsonResponse::success('TRELLO_JOB_LOGS', 'OK', ['items' => $this->repo->logs((int)$result['id'])]);
    }

    public function getReport(array $params): JsonResponse
    {
        $result = $this->requireJob((string)$params['public_id']);
        if ($result instanceof JsonResponse) return $result;
        return JsonResponse::success('TRELLO_JOB_REPORT', 'OK', ['report' => $this->repo->report((string)$params['public_id'])]);
    }

    public function createWebhook(array $params): JsonResponse
    {
        return $this->withIdempotency(fn(): JsonResponse => $this->createWebhookInternal($params));
    }

    private function createWebhookInternal(array $params): JsonResponse
    {
        $connection = $this->requireConnection((string)($params['public_id'] ?? ''));
        if ($connection instanceof JsonResponse) return $connection;
        $secret = EncryptionService::decrypt((string)($connection['api_secret_encrypted'] ?? ''));
        if ($secret === null || $secret === '') {
            return JsonResponse::error('TRELLO_WEBHOOK_SECRET_REQUIRED', 'Configure the Trello app secret before creating a webhook', 422);
        }
        $input = $this->body();
        $modelId = trim((string)($input['model_id'] ?? ''));
        $callbackUrl = trim((string)($input['callback_url'] ?? ''));
        if ($modelId === '' || $callbackUrl === '') return JsonResponse::error('VALIDATION_ERROR', 'model_id and callback_url are required', 422);
        $parsed = parse_url($callbackUrl);
        if (!is_array($parsed) || strtolower((string)($parsed['scheme'] ?? '')) !== 'https' || empty($parsed['host']) || isset($parsed['user'], $parsed['pass']) || strlen($callbackUrl) > 2048) {
            return JsonResponse::error('VALIDATION_ERROR', 'callback_url must be an HTTPS URL without credentials', 422);
        }
        $existingWebhook = $this->repo->webhookForModel((int)$connection['id'], $modelId);
        if ($existingWebhook !== null) {
            if ((string)$existingWebhook['callback_url'] !== $callbackUrl) {
                return JsonResponse::error('TRELLO_WEBHOOK_EXISTS', 'An active webhook already exists for this Trello model', 409);
            }
            unset($existingWebhook['api_secret_encrypted']);
            return JsonResponse::success('TRELLO_WEBHOOK_EXISTS', 'Webhook already exists', ['webhook' => $existingWebhook]);
        }
        $key = EncryptionService::decrypt((string)$connection['api_key_encrypted']);
        $token = EncryptionService::decrypt((string)$connection['token_encrypted']);
        if ($key === null || $token === null) return JsonResponse::error('TRELLO_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try {
            $client = new TrelloClient($this->repo);
            $client->setConnectionId((int)$connection['id']);
            $created = $client->webhook($key, $token, $modelId, $callbackUrl, 'TropaTT Trello migration');
            if (empty($created['id'])) throw new RuntimeException('TRELLO_WEBHOOK_ID_MISSING');
            $webhook = $this->repo->createWebhook((int)$connection['id'], ['trello_webhook_id' => (string)($created['id'] ?? ''), 'model_id' => $modelId, 'callback_url' => $callbackUrl]);
            return JsonResponse::success('TRELLO_WEBHOOK_CREATED', 'Webhook created', ['webhook' => $this->publicWebhook($webhook)], 201);
        } catch (\Throwable) {
            return JsonResponse::error('TRELLO_WEBHOOK_CREATE_FAILED', 'Could not create Trello webhook', 400);
        }
    }

    public function deleteWebhook(array $params): JsonResponse
    {
        $webhook = $this->repo->webhook((string)($params['webhook_public_id'] ?? ''));
        if (!$webhook) return JsonResponse::error('NOT_FOUND', 'Webhook not found', 404);
        $connection = $this->repo->getConnectionById((int)$webhook['connection_id']);
        if (!$connection) return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        $access = $this->requireConnection((string)$connection['public_id']);
        if ($access instanceof JsonResponse) return $access;
        try {
            $key = EncryptionService::decrypt((string)$connection['api_key_encrypted']);
            $token = EncryptionService::decrypt((string)$connection['token_encrypted']);
            if ($key !== null && $token !== null && !empty($webhook['trello_webhook_id'])) {
                $client = new TrelloClient($this->repo);
                $client->setConnectionId((int)$connection['id']);
                $client->deleteWebhook($key, $token, (string)$webhook['trello_webhook_id']);
            }
            $this->repo->deleteWebhook((string)$params['webhook_public_id']);
            return JsonResponse::success('TRELLO_WEBHOOK_DELETED', 'Webhook deleted');
        } catch (\Throwable) {
            return JsonResponse::error('TRELLO_WEBHOOK_DELETE_FAILED', 'Could not delete Trello webhook', 409);
        }
    }

    public function receiveWebhook(array $params): JsonResponse
    {
        $webhook = $this->repo->webhook((string)($params['webhook_public_id'] ?? ''));
        if (!$webhook || (int)($webhook['active'] ?? 0) !== 1) return JsonResponse::error('NOT_FOUND', 'Webhook not found', 404);
        $raw = (string)($this->container->get('request')->rawBody ?? '');
        $secret = EncryptionService::decrypt((string)($webhook['api_secret_encrypted'] ?? ''));
        $signature = trim((string)($_SERVER['HTTP_X_TRELLO_WEBHOOK'] ?? ''));
        if ($secret === null || $secret === '' || $signature === '') {
            return JsonResponse::error('FORBIDDEN', 'Invalid Trello webhook signature', 403);
        }
        $expected = base64_encode(hash_hmac('sha1', $raw . (string)$webhook['callback_url'], $secret, true));
        if (!hash_equals($expected, $signature)) return JsonResponse::error('FORBIDDEN', 'Invalid Trello webhook signature', 403);
        $payload = json_decode($raw, true);
        if (!is_array($payload)) return JsonResponse::error('VALIDATION_ERROR', 'Invalid webhook payload', 422);
        $payloadModelId = (string)($payload['model']['id'] ?? '');
        if ($payloadModelId !== '' && !hash_equals((string)$webhook['model_id'], $payloadModelId)) {
            return JsonResponse::error('FORBIDDEN', 'Webhook model mismatch', 403);
        }
        $eventId = (string)($payload['action']['id'] ?? '');
        if ($eventId !== '' && !$this->repo->markWebhookEvent((string)$params['webhook_public_id'], $eventId)) {
            return JsonResponse::success('TRELLO_WEBHOOK_DUPLICATE', 'Already accepted');
        }
        // Webhooks are intentionally lightweight: enqueue a bounded board sync
        // and let the normal worker perform the authenticated full snapshot.
        $connection = $this->repo->getConnectionById((int)$webhook['connection_id']);
        $modelId = (string)($webhook['model_id'] ?? '');
        if ($connection && !$this->repo->hasRunningJobs((int)$connection['id'])) {
            $job = $this->repo->createJob([
                'connection_id' => (int)$connection['id'],
                'mode' => 'sync',
                'source_scope' => ['board_ids' => $modelId !== '' ? [$modelId] : [], 'max_cards' => 0],
                'options' => ['include_archived' => true],
                'created_by_user_id' => (int)$connection['created_by_user_id'],
            ]);
            if (!empty($job['public_id'])) $this->repo->requestStatus((string)$job['public_id'], 'queued');
        }
        return JsonResponse::success('TRELLO_WEBHOOK_ACCEPTED', 'Accepted');
    }

    public function validateWebhook(array $params = []): JsonResponse
    {
        return JsonResponse::success('TRELLO_WEBHOOK_VALID', 'OK');
    }

    private function changeJob(string $publicId, string $status, string $code): JsonResponse
    {
        $result = $this->requireJob($publicId);
        if ($result instanceof JsonResponse) return $result;
        $current = (string)($result['status'] ?? '');
        $allowed = match ($status) {
            'queued' => in_array($current, ['draft', 'paused', 'failed', 'cancelled'], true),
            'pausing' => in_array($current, ['queued', 'running'], true),
            'cancelling' => in_array($current, ['draft', 'queued', 'running', 'paused', 'pausing'], true),
            default => false,
        };
        if (!$allowed) return JsonResponse::error('INVALID_JOB_STATUS', 'Job cannot be changed from status: ' . $current, 409);
        $this->repo->requestStatus($publicId, $status);
        return JsonResponse::success($code, 'Job state updated');
    }

    private function buildImportService(): TrelloImportService
    {
        $client = new TrelloClient($this->repo);
        $crawler = new TrelloCrawler($client, $this->repo);
        $writer = new TrelloTargetWriter($this->container, $this->repo, $client);
        return new TrelloImportService($this->repo, $client, $crawler, $writer);
    }

    /** @param callable():JsonResponse $producer */
    private function withIdempotency(callable $producer): JsonResponse
    {
        if (!$this->container->has('service.idempotency')) return $producer();
        $service = $this->container->get('service.idempotency');
        $request = $this->container->get('request');
        $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : null;
        $actor = is_array($auth) && is_array($auth['user'] ?? null) ? $auth['user'] : null;
        $replayed = $service->replay($request, $actor);
        if ($replayed instanceof JsonResponse) return $replayed;
        $response = $producer();
        $service->remember($request, $actor, $response);
        return $response;
    }

    private function publicWebhook(array $webhook): array
    {
        unset($webhook['api_secret_encrypted']);
        return $webhook;
    }

    private function publicConnection(array $connection): array
    {
        foreach (['api_key_encrypted', 'token_encrypted', 'api_secret_encrypted'] as $secret) unset($connection[$secret]);
        return $connection;
    }
}
