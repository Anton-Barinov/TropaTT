<?php
declare(strict_types=1);

namespace Module\Crm\AsanaMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\AsanaMigration\Repository\AsanaMigrationRepository;
use Module\Crm\AsanaMigration\Service\AsanaClient;
use Module\Crm\AsanaMigration\Service\AsanaCrawler;
use Module\Crm\AsanaMigration\Service\AsanaImportService;
use Module\Crm\AsanaMigration\Service\AsanaTargetWriter;
use Module\Crm\AsanaMigration\Service\EncryptionService;
use PDO;

final class AsanaMigrationController
{
    private AsanaMigrationRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->repo = new AsanaMigrationRepository($container->get('db.pdo'));
    }

    private function body(): array
    {
        $decoded = json_decode((string)($this->container->get('request')->rawBody ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function actor(): array
    {
        $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : [];
        return is_array($auth) && is_array($auth['user'] ?? null) ? $auth['user'] : [];
    }

    private function actorId(): int { return (int)($this->actor()['id'] ?? 0); }

    private function can(string $permission): bool
    {
        $actor = $this->actor();
        return !empty($actor['is_root']) || in_array('*', (array)($actor['permission_codes'] ?? []), true) || in_array($permission, (array)($actor['permission_codes'] ?? []), true);
    }

    private function connection(string $id): array|JsonResponse
    {
        $connection = $this->repo->getConnection($id);
        if (!$connection) return JsonResponse::error('NOT_FOUND', 'Asana connection not found', 404);
        if (!$this->can('module.asana-migration.manage') && (int)$connection['created_by_user_id'] !== $this->actorId()) return JsonResponse::error('FORBIDDEN', 'Connection access denied', 403);
        return $connection;
    }

    private function job(string $id): array|JsonResponse
    {
        $job = $this->repo->getJob($id);
        if (!$job) return JsonResponse::error('NOT_FOUND', 'Asana migration job not found', 404);
        if (!$this->can('module.asana-migration.manage') && (int)$job['created_by_user_id'] !== $this->actorId()) return JsonResponse::error('FORBIDDEN', 'Migration job access denied', 403);
        return $job;
    }

    public function listConnections(): JsonResponse
    {
        $items = $this->repo->listConnections($this->actorId(), $this->can('module.asana-migration.manage'));
        return JsonResponse::success('ASANA_CONNECTIONS_LIST', 'OK', ['connections' => array_map([$this, 'publicConnection'], $items)]);
    }

    public function createConnection(): JsonResponse
    {
        return $this->withIdempotency(fn(): JsonResponse => $this->createConnectionInternal());
    }

    private function createConnectionInternal(): JsonResponse
    {
        $input = $this->body();
        $name = trim((string)($input['name'] ?? ''));
        $token = trim((string)($input['access_token'] ?? $input['token'] ?? ''));
        if ($name === '' || $token === '') return JsonResponse::error('VALIDATION_ERROR', 'name and access_token are required', 422);
        $connection = null;
        try {
            $connection = $this->repo->createConnection(['name' => mb_substr($name, 0, 255), 'auth_type' => 'pat', 'access_token_encrypted' => EncryptionService::encrypt($token), 'created_by_user_id' => $this->actorId()]);
            $me = $this->client($connection)->me($token);
            $this->repo->updateConnectionCheck((string)$connection['public_id'], true);
            return JsonResponse::success('ASANA_CONNECTION_CREATED', 'Connection created and verified', ['connection' => $this->publicConnection($this->repo->getConnection((string)$connection['public_id']) ?? $connection), 'user' => $me], 201);
        } catch (\Throwable $e) {
            if (is_array($connection) && !empty($connection['public_id'])) $this->repo->updateConnectionCheck((string)$connection['public_id'], false, 'Asana connection test failed');
            return JsonResponse::error($e->getMessage() === 'ASANA_AUTH_FAILED' ? 'ASANA_AUTH_FAILED' : 'ASANA_CONNECTION_TEST_FAILED', 'Asana credentials could not be verified', 422);
        }
    }

    public function getConnection(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? ''));
        return $result instanceof JsonResponse ? $result : JsonResponse::success('ASANA_CONNECTION', 'OK', ['connection' => $this->publicConnection($result)]);
    }

    public function updateConnection(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        $input = $this->body(); $update = [];
        if (array_key_exists('name', $input)) $update['name'] = mb_substr(trim((string)$input['name']), 0, 255);
        $newToken = (string)($input['access_token'] ?? $input['token'] ?? '');
        if ($newToken !== '') $update['access_token_encrypted'] = EncryptionService::encrypt($newToken);
        if (array_key_exists('workspace_gid', $input)) $update['workspace_gid'] = trim((string)$input['workspace_gid']) ?: null;
        $this->repo->updateConnection((string)$params['public_id'], $update);
        return JsonResponse::success('ASANA_CONNECTION_UPDATED', 'Connection updated', ['connection' => $this->publicConnection($this->repo->getConnection((string)$params['public_id']) ?? [])]);
    }

    public function deleteConnection(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        if ($this->repo->hasRunningJobs((int)$result['id'])) return JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS', 'Cancel running jobs before deleting the connection', 409);
        $this->repo->deleteConnection((int)$result['id']);
        return JsonResponse::success('ASANA_CONNECTION_DELETED', 'Connection deleted');
    }

    public function testConnection(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        $token = EncryptionService::decrypt((string)$result['access_token_encrypted']);
        if ($token === null) return JsonResponse::error('ASANA_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try { $me = $this->client($result)->me($token); $this->repo->updateConnectionCheck((string)$result['public_id'], true); return JsonResponse::success('ASANA_CONNECTION_TEST_OK', 'Connection successful', ['user' => $me]); }
        catch (\Throwable) { $this->repo->updateConnectionCheck((string)$result['public_id'], false, 'Asana connection test failed'); return JsonResponse::error('ASANA_CONNECTION_TEST_FAILED', 'Asana connection test failed', 400); }
    }

    public function listWorkspaces(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? '')); if ($result instanceof JsonResponse) return $result;
        $token = EncryptionService::decrypt((string)$result['access_token_encrypted']); if ($token === null) return JsonResponse::error('ASANA_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try { return JsonResponse::success('ASANA_WORKSPACES_LIST', 'OK', ['workspaces' => $this->client($result)->workspaces($token)]); } catch (\Throwable) { return JsonResponse::error('ASANA_WORKSPACES_FAILED', 'Could not load Asana workspaces', 400); }
    }

    public function discover(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? '')); if ($result instanceof JsonResponse) return $result;
        $input = $this->body(); $workspace = trim((string)($input['workspace_gid'] ?? $result['workspace_gid'] ?? ''));
        if ($workspace === '') return JsonResponse::error('ASANA_WORKSPACE_REQUIRED', 'workspace_gid is required', 422);
        $token = EncryptionService::decrypt((string)$result['access_token_encrypted']); if ($token === null) return JsonResponse::error('ASANA_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try { $client = $this->client($result); $projects = $client->projects($token, $workspace, !empty($input['include_archived'])); foreach ($client->users($token, $workspace) as $user) $this->repo->upsertUserMapping((int)$result['id'], $user); return JsonResponse::success('ASANA_DISCOVERY_COMPLETE', 'Asana projects discovered', ['workspace_gid' => $workspace, 'projects' => $projects, 'user_mappings' => $this->repo->listUserMappings((int)$result['id'])]); }
        catch (\Throwable) { return JsonResponse::error('ASANA_DISCOVERY_FAILED', 'Asana discovery failed', 400); }
    }

    public function listUserMappings(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? '')); return $result instanceof JsonResponse ? $result : JsonResponse::success('ASANA_USER_MAPPINGS', 'OK', ['items' => $this->repo->listUserMappings((int)$result['id'])]);
    }

    public function updateUserMapping(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? '')); if ($result instanceof JsonResponse) return $result;
        $input = $this->body(); $crmId = !empty($input['crm_user_public_id']) ? (string)$input['crm_user_public_id'] : null;
        if ($crmId !== null && $this->repo->activeUserPublicId($crmId) === null) return JsonResponse::error('USER_NOT_FOUND', 'Active CRM user not found', 404);
        if (!$this->repo->updateUserMapping((int)$result['id'], (int)($params['mapping_id'] ?? 0), $crmId)) return JsonResponse::error('MAPPING_NOT_FOUND', 'Asana user mapping not found', 404);
        return JsonResponse::success('ASANA_USER_MAPPING_UPDATED', 'Mapping updated');
    }

    public function listJobs(): JsonResponse { return JsonResponse::success('ASANA_JOBS_LIST', 'OK', ['items' => $this->repo->listJobs($this->actorId(), $this->can('module.asana-migration.manage'))]); }

    public function createJob(): JsonResponse
    {
        return $this->withIdempotency(fn(): JsonResponse => $this->createJobInternal());
    }

    private function createJobInternal(): JsonResponse
    {
        $input = $this->body(); $connection = $this->connection((string)($input['connection_public_id'] ?? '')); if ($connection instanceof JsonResponse) return $connection;
        $workspace = trim((string)($input['workspace_gid'] ?? $connection['workspace_gid'] ?? '')); if ($workspace === '') return JsonResponse::error('ASANA_WORKSPACE_REQUIRED', 'workspace_gid is required', 422);
        $token = EncryptionService::decrypt((string)($connection['access_token_encrypted'] ?? ''));
        if ($token === null) return JsonResponse::error('ASANA_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try {
            $workspaceFound = false;
            foreach ($this->client($connection)->workspaces($token) as $sourceWorkspace) {
                if ((string)($sourceWorkspace['gid'] ?? '') === $workspace) { $workspaceFound = true; break; }
            }
            if (!$workspaceFound) return JsonResponse::error('ASANA_WORKSPACE_NOT_ACCESSIBLE', 'The selected workspace is not accessible with this connection', 422);
        } catch (\Throwable) {
            return JsonResponse::error('ASANA_WORKSPACE_VALIDATION_FAILED', 'Could not validate the selected Asana workspace', 422);
        }
        $mode = (string)($input['mode'] ?? 'import'); if (!in_array($mode, ['import', 'sync', 'dry_run'], true)) return JsonResponse::error('VALIDATION_ERROR', 'mode must be import, sync or dry_run', 422);
        $scope = (array)($input['source_scope'] ?? []); $scope['project_gids'] = array_values(array_filter(array_map('strval', (array)($input['project_gids'] ?? $scope['project_gids'] ?? [])))); $scope['max_tasks'] = max(0, (int)($input['max_tasks'] ?? $scope['max_tasks'] ?? 0));
        $job = $this->repo->createJob(['connection_id' => (int)$connection['id'], 'workspace_gid' => $workspace, 'mode' => $mode, 'source_scope' => $scope, 'target_options' => (array)($input['target_options'] ?? $input['options'] ?? []), 'created_by_user_id' => $this->actorId()]);
        return JsonResponse::success('ASANA_JOB_CREATED', 'Job created', ['job' => $job], 201);
    }

    public function getJob(array $params): JsonResponse { $result = $this->job((string)($params['public_id'] ?? '')); return $result instanceof JsonResponse ? $result : JsonResponse::success('ASANA_JOB', 'OK', ['job' => $result]); }
    public function startJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'queued', 'ASANA_JOB_QUEUED'); }
    public function pauseJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'pausing', 'ASANA_JOB_PAUSING'); }
    public function resumeJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'queued', 'ASANA_JOB_RESUMED'); }
    public function cancelJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'cancelling', 'ASANA_JOB_CANCELLING'); }

    public function retryFailed(array $params): JsonResponse { $result = $this->job((string)$params['public_id']); if ($result instanceof JsonResponse) return $result; if (!in_array((string)($result['status'] ?? ''), ['completed_with_warnings', 'failed', 'cancelled'], true)) return JsonResponse::error('INVALID_JOB_STATUS', 'Only a finished job can be retried', 409); $count = $this->repo->retryJob((string)$params['public_id']); if ($count === null) return JsonResponse::error('INVALID_JOB_STATUS', 'Job changed concurrently; retry it again', 409); return JsonResponse::success('ASANA_JOB_RETRY_QUEUED', 'Failed items queued for retry', ['reset_items' => $count]); }

    public function rollbackJob(array $params): JsonResponse
    {
        $result = $this->job((string)$params['public_id']); if ($result instanceof JsonResponse) return $result;
        try { $this->buildImportService()->rollback((string)$params['public_id'], $this->actor()); return JsonResponse::success('ASANA_JOB_ROLLED_BACK', 'Job targets rolled back'); }
        catch (\Throwable) { return JsonResponse::error('ASANA_ROLLBACK_FAILED', 'Rollback failed; inspect the migration log', 409); }
    }

    public function listJobItems(array $params): JsonResponse { $result = $this->job((string)$params['public_id']); if ($result instanceof JsonResponse) return $result; $input = $this->container->get('request')->allInput(); return JsonResponse::success('ASANA_JOB_ITEMS', 'OK', ['items' => $this->repo->items((int)$result['id'], !empty($input['status']) ? (string)$input['status'] : null, max(1, min(1000, (int)($input['limit'] ?? 200))))]); }
    public function listJobLogs(array $params): JsonResponse { $result = $this->job((string)$params['public_id']); return $result instanceof JsonResponse ? $result : JsonResponse::success('ASANA_JOB_LOGS', 'OK', ['items' => $this->repo->logs((int)$result['id'])]); }
    public function getReport(array $params): JsonResponse { $result = $this->job((string)$params['public_id']); return $result instanceof JsonResponse ? $result : JsonResponse::success('ASANA_JOB_REPORT', 'OK', ['report' => $this->repo->report((string)$params['public_id'])]); }

    private function changeJob(string $id, string $status, string $code): JsonResponse
    {
        $result = $this->job($id); if ($result instanceof JsonResponse) return $result; $current = (string)($result['status'] ?? '');
        $allowed = match ($status) { 'queued' => in_array($current, ['draft', 'paused', 'failed', 'cancelled'], true), 'pausing' => in_array($current, ['queued', 'running'], true), 'cancelling' => in_array($current, ['draft', 'queued', 'running', 'paused', 'pausing'], true), default => false };
        if (!$allowed) return JsonResponse::error('INVALID_JOB_STATUS', 'Job cannot be changed from status: ' . $current, 409);
        if (!$this->repo->requestStatus($id, $status)) return JsonResponse::error('INVALID_JOB_STATUS', 'Job changed concurrently; retry the action', 409);
        return JsonResponse::success($code, 'Job state updated');
    }

    private function client(array $connection): AsanaClient { $client = new AsanaClient($this->repo); $client->setConnectionId((int)$connection['id']); return $client; }

    private function buildImportService(): AsanaImportService
    {
        $client = new AsanaClient($this->repo); $crawler = new AsanaCrawler($client, $this->repo); $writer = new AsanaTargetWriter($this->container, $this->repo, $client); return new AsanaImportService($this->repo, $client, $crawler, $writer);
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

    private function publicConnection(array $connection): array { unset($connection['access_token_encrypted'], $connection['refresh_token_encrypted'], $connection['client_id_encrypted'], $connection['client_secret_encrypted']); return $connection; }
}
