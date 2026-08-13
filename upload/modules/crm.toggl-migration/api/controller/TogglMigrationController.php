<?php
declare(strict_types=1);

namespace Module\Crm\TogglMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\TogglMigration\Repository\TogglMigrationRepository;
use Module\Crm\TogglMigration\Service\TogglClient;
use Module\Crm\TogglMigration\Service\TogglCrawler;
use Module\Crm\TogglMigration\Service\TogglImportService;
use Module\Crm\TogglMigration\Service\TogglTargetWriter;
use Module\Crm\TogglMigration\Service\EncryptionService;
use PDO;

final class TogglMigrationController
{
    private TogglMigrationRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->repo = new TogglMigrationRepository($container->get('db.pdo'));
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
        if (!$connection) return JsonResponse::error('NOT_FOUND', 'Toggl connection not found', 404);
        if (!$this->can('module.toggl-migration.manage') && (int)$connection['created_by_user_id'] !== $this->actorId()) return JsonResponse::error('FORBIDDEN', 'Connection access denied', 403);
        return $connection;
    }

    private function job(string $id): array|JsonResponse
    {
        $job = $this->repo->getJob($id);
        if (!$job) return JsonResponse::error('NOT_FOUND', 'Toggl migration job not found', 404);
        if (!$this->can('module.toggl-migration.manage') && (int)$job['created_by_user_id'] !== $this->actorId()) return JsonResponse::error('FORBIDDEN', 'Migration job access denied', 403);
        return $job;
    }

    public function listConnections(): JsonResponse
    {
        $items = $this->repo->listConnections($this->actorId(), $this->can('module.toggl-migration.manage'));
        return JsonResponse::success('TOGGL_CONNECTIONS_LIST', 'OK', ['connections' => array_map([$this, 'publicConnection'], $items)]);
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
            return JsonResponse::success('TOGGL_CONNECTION_CREATED', 'Connection created and verified', ['connection' => $this->publicConnection($this->repo->getConnection((string)$connection['public_id']) ?? $connection), 'user' => $this->safeSourceUser($me)], 201);
        } catch (\Throwable $e) {
            if (is_array($connection) && !empty($connection['public_id'])) $this->repo->updateConnectionCheck((string)$connection['public_id'], false, 'Toggl connection test failed');
            return JsonResponse::error($e->getMessage() === 'TOGGL_AUTH_FAILED' ? 'TOGGL_AUTH_FAILED' : 'TOGGL_CONNECTION_TEST_FAILED', 'Toggl credentials could not be verified', 422);
        }
    }

    public function getConnection(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? ''));
        return $result instanceof JsonResponse ? $result : JsonResponse::success('TOGGL_CONNECTION', 'OK', ['connection' => $this->publicConnection($result)]);
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
        if ($newToken !== '') $this->repo->updateConnectionCheck((string)$params['public_id'], false, 'Credentials changed; connection test required');
        return JsonResponse::success('TOGGL_CONNECTION_UPDATED', 'Connection updated', ['connection' => $this->publicConnection($this->repo->getConnection((string)$params['public_id']) ?? [])]);
    }

    public function deleteConnection(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        try {
            $this->repo->deleteConnection((int)$result['id']);
            return JsonResponse::success('TOGGL_CONNECTION_DELETED', 'Connection deleted');
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'TOGGL_CONNECTION_HAS_RUNNING_JOBS' => JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS', 'Cancel running jobs before deleting the connection', 409),
                'TOGGL_CONNECTION_HAS_IMPORTED_TARGETS' => JsonResponse::error('CONNECTION_HAS_IMPORTED_TARGETS', 'Rollback imported targets before deleting the connection', 409),
                'TOGGL_CONNECTION_NOT_FOUND' => JsonResponse::error('NOT_FOUND', 'Toggl connection not found', 404),
                default => JsonResponse::error('TOGGL_CONNECTION_DELETE_FAILED', 'Connection could not be deleted', 409),
            };
        }
    }

    public function testConnection(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? ''));
        if ($result instanceof JsonResponse) return $result;
        $token = EncryptionService::decrypt((string)$result['access_token_encrypted']);
        if ($token === null) return JsonResponse::error('TOGGL_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try { $me = $this->client($result)->me($token); $this->repo->updateConnectionCheck((string)$result['public_id'], true); return JsonResponse::success('TOGGL_CONNECTION_TEST_OK', 'Connection successful', ['user' => $this->safeSourceUser($me)]); }
        catch (\Throwable) { $this->repo->updateConnectionCheck((string)$result['public_id'], false, 'Toggl connection test failed'); return JsonResponse::error('TOGGL_CONNECTION_TEST_FAILED', 'Toggl connection test failed', 400); }
    }

    public function listWorkspaces(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? '')); if ($result instanceof JsonResponse) return $result;
        $token = EncryptionService::decrypt((string)$result['access_token_encrypted']); if ($token === null) return JsonResponse::error('TOGGL_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try { return JsonResponse::success('TOGGL_WORKSPACES_LIST', 'OK', ['workspaces' => $this->client($result)->workspaces($token)]); } catch (\Throwable) { return JsonResponse::error('TOGGL_WORKSPACES_FAILED', 'Could not load Toggl workspaces', 400); }
    }

    public function discover(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? '')); if ($result instanceof JsonResponse) return $result;
        $input = $this->body(); $workspace = trim((string)($input['workspace_gid'] ?? $result['workspace_gid'] ?? ''));
        if ($workspace === '') return JsonResponse::error('TOGGL_WORKSPACE_REQUIRED', 'workspace_gid is required', 422);
        $token = EncryptionService::decrypt((string)$result['access_token_encrypted']); if ($token === null) return JsonResponse::error('TOGGL_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try {
            $client = $this->client($result);
            $workspaces = $client->workspaces($token);
            $sourceWorkspace = null;
            foreach ($workspaces as $candidate) if ((string)($candidate['id'] ?? $candidate['gid'] ?? '') === $workspace) { $sourceWorkspace = $candidate; break; }
            if ($sourceWorkspace === null) return JsonResponse::error('TOGGL_WORKSPACE_NOT_ACCESSIBLE', 'The selected workspace is not accessible with this connection', 422);
            $organization = (string)($sourceWorkspace['organization_id'] ?? $sourceWorkspace['organization_gid'] ?? '');
            foreach ($client->users($token, $workspace, $organization !== '' ? $organization : null) as $user) $this->repo->upsertUserMapping((int)$result['id'], $user);
            $projects = $client->projects($token, $workspace, !empty($input['include_archived']));
            $clients = $client->clients($token, $workspace, !empty($input['include_archived']));
            $tags = $client->tags($token, $workspace);
            return JsonResponse::success('TOGGL_DISCOVERY_COMPLETE', 'Toggl workspace discovered', ['workspace_gid'=>$workspace,'projects'=>$projects,'clients'=>$clients,'tags'=>$tags,'user_mappings'=>$this->repo->listUserMappings((int)$result['id']),'crm_users'=>$this->repo->listCrmUsers()]);
        } catch (\Throwable) { return JsonResponse::error('TOGGL_DISCOVERY_FAILED', 'Toggl discovery failed', 400); }
    }

    public function listUserMappings(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? '')); return $result instanceof JsonResponse ? $result : JsonResponse::success('TOGGL_USER_MAPPINGS', 'OK', ['items' => $this->repo->listUserMappings((int)$result['id'])]);
    }

    public function updateUserMapping(array $params): JsonResponse
    {
        $result = $this->connection((string)($params['public_id'] ?? '')); if ($result instanceof JsonResponse) return $result;
        $input = $this->body(); $crmId = !empty($input['crm_user_public_id']) ? (string)$input['crm_user_public_id'] : null;
        if ($crmId !== null && $this->repo->activeUserPublicId($crmId) === null) return JsonResponse::error('USER_NOT_FOUND', 'Active CRM user not found', 404);
        if (!$this->repo->updateUserMapping((int)$result['id'], (int)($params['mapping_id'] ?? 0), $crmId)) return JsonResponse::error('MAPPING_NOT_FOUND', 'Toggl user mapping not found', 404);
        return JsonResponse::success('TOGGL_USER_MAPPING_UPDATED', 'Mapping updated');
    }

    public function listJobs(): JsonResponse { return JsonResponse::success('TOGGL_JOBS_LIST', 'OK', ['items' => $this->repo->listJobs($this->actorId(), $this->can('module.toggl-migration.manage'))]); }

    public function createJob(): JsonResponse
    {
        return $this->withIdempotency(fn(): JsonResponse => $this->createJobInternal());
    }

    private function createJobInternal(): JsonResponse
    {
        $input = $this->body(); $connection = $this->connection((string)($input['connection_public_id'] ?? '')); if ($connection instanceof JsonResponse) return $connection;
        $workspace = trim((string)($input['workspace_gid'] ?? $connection['workspace_gid'] ?? '')); if ($workspace === '') return JsonResponse::error('TOGGL_WORKSPACE_REQUIRED', 'workspace_gid is required', 422);
        $token = EncryptionService::decrypt((string)($connection['access_token_encrypted'] ?? ''));
        if ($token === null) return JsonResponse::error('TOGGL_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try {
            $workspaceFound = false;
            foreach ($this->client($connection)->workspaces($token) as $sourceWorkspace) {
                if ((string)($sourceWorkspace['id'] ?? $sourceWorkspace['gid'] ?? '') === $workspace) { $workspaceFound = true; break; }
            }
            if (!$workspaceFound) return JsonResponse::error('TOGGL_WORKSPACE_NOT_ACCESSIBLE', 'The selected workspace is not accessible with this connection', 422);
        } catch (\Throwable) {
            return JsonResponse::error('TOGGL_WORKSPACE_VALIDATION_FAILED', 'Could not validate the selected Toggl workspace', 422);
        }
        $mode = (string)($input['mode'] ?? 'import'); if (!in_array($mode, ['import', 'sync', 'dry_run'], true)) return JsonResponse::error('VALIDATION_ERROR', 'mode must be import, sync or dry_run', 422);
        // WorklogService intentionally allows non-root actors to write only
        // their own entries. Since this job always crawls time entries, reject
        // real imports up front instead of completing with a misleadingly
        // partial result. Dry-run remains available to non-root operators.
        if ($mode !== 'dry_run' && empty($this->actor()['is_root'])) return JsonResponse::error('TOGGL_ROOT_REQUIRED', 'Full Toggl imports require a root user because they include time entries for mapped users', 403);
        $scope = (array)($input['source_scope'] ?? []);
        $scope['project_gids'] = array_values(array_filter(array_map('strval', (array)($input['project_gids'] ?? $scope['project_gids'] ?? []))));
        $scope['max_tasks'] = max(0, (int)($input['max_tasks'] ?? $scope['max_tasks'] ?? 0));
        $fromTimestamp = strtotime(trim((string)($input['time_entries_from'] ?? $scope['time_entries_from'] ?? '')));
        $toTimestamp = strtotime(trim((string)($input['time_entries_to'] ?? $scope['time_entries_to'] ?? '')));
        if ($fromTimestamp === false || $toTimestamp === false || gmdate('Y-m-d', $fromTimestamp) > gmdate('Y-m-d', $toTimestamp)) return JsonResponse::error('TOGGL_TIME_ENTRY_RANGE_REQUIRED', 'time_entries_from and time_entries_to must be a valid ordered date range', 422);
        $scope['time_entries_from'] = gmdate('Y-m-d', $fromTimestamp);
        $scope['time_entries_to'] = gmdate('Y-m-d', $toTimestamp);
        try {
            $job = $this->repo->createJob(['connection_id' => (int)$connection['id'], 'workspace_gid' => $workspace, 'mode' => $mode, 'source_scope' => $scope, 'target_options' => (array)($input['target_options'] ?? $input['options'] ?? []), 'created_by_user_id' => $this->actorId()]);
        } catch (\RuntimeException $e) {
            if (in_array($e->getMessage(), ['TOGGL_CONNECTION_DELETE_IN_PROGRESS', 'TOGGL_CONNECTION_NOT_FOUND'], true)) return JsonResponse::error('TOGGL_CONNECTION_CHANGED', 'The Toggl connection changed while creating the job', 409);
            throw $e;
        }
        return JsonResponse::success('TOGGL_JOB_CREATED', 'Job created', ['job' => $job], 201);
    }

    public function getJob(array $params): JsonResponse { $result = $this->job((string)($params['public_id'] ?? '')); return $result instanceof JsonResponse ? $result : JsonResponse::success('TOGGL_JOB', 'OK', ['job' => $result]); }
    public function startJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'queued', 'TOGGL_JOB_QUEUED'); }
    public function pauseJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'pausing', 'TOGGL_JOB_PAUSING'); }
    public function resumeJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'queued', 'TOGGL_JOB_RESUMED'); }
    public function cancelJob(array $params): JsonResponse { return $this->changeJob((string)$params['public_id'], 'cancelling', 'TOGGL_JOB_CANCELLING'); }

    public function retryFailed(array $params): JsonResponse { $result = $this->job((string)$params['public_id']); if ($result instanceof JsonResponse) return $result; if (!in_array((string)($result['status'] ?? ''), ['completed_with_warnings', 'failed', 'cancelled'], true)) return JsonResponse::error('INVALID_JOB_STATUS', 'Only a finished job can be retried', 409); if (($result['mode'] ?? 'import') !== 'dry_run' && empty($this->actor()['is_root'])) return JsonResponse::error('TOGGL_ROOT_REQUIRED', 'Full Toggl imports require a root user', 403); $count = $this->repo->retryJob((string)$params['public_id']); if ($count === null) return JsonResponse::error('INVALID_JOB_STATUS', 'Job changed concurrently; retry it again', 409); return JsonResponse::success('TOGGL_JOB_RETRY_QUEUED', 'Failed items queued for retry', ['reset_items' => $count]); }

    public function rollbackJob(array $params): JsonResponse
    {
        $result = $this->job((string)$params['public_id']); if ($result instanceof JsonResponse) return $result;
        try { $this->buildImportService()->rollback((string)$params['public_id'], $this->actor()); return JsonResponse::success('TOGGL_JOB_ROLLED_BACK', 'Job targets rolled back'); }
        catch (\Throwable) { return JsonResponse::error('TOGGL_ROLLBACK_FAILED', 'Rollback failed; inspect the migration log', 409); }
    }

    public function listJobItems(array $params): JsonResponse { $result = $this->job((string)$params['public_id']); if ($result instanceof JsonResponse) return $result; $input = $this->container->get('request')->allInput(); return JsonResponse::success('TOGGL_JOB_ITEMS', 'OK', ['items' => $this->repo->items((int)$result['id'], !empty($input['status']) ? (string)$input['status'] : null, max(1, min(1000, (int)($input['limit'] ?? 200))))]); }
    public function listJobLogs(array $params): JsonResponse { $result = $this->job((string)$params['public_id']); return $result instanceof JsonResponse ? $result : JsonResponse::success('TOGGL_JOB_LOGS', 'OK', ['items' => $this->repo->logs((int)$result['id'])]); }
    public function getReport(array $params): JsonResponse { $result = $this->job((string)$params['public_id']); return $result instanceof JsonResponse ? $result : JsonResponse::success('TOGGL_JOB_REPORT', 'OK', ['report' => $this->repo->report((string)$params['public_id'])]); }

    private function changeJob(string $id, string $status, string $code): JsonResponse
    {
        $result = $this->job($id); if ($result instanceof JsonResponse) return $result; $current = (string)($result['status'] ?? '');
        $allowed = match ($status) { 'queued' => in_array($current, ['draft', 'paused', 'failed', 'cancelled'], true), 'pausing' => in_array($current, ['queued', 'running'], true), 'cancelling' => in_array($current, ['draft', 'queued', 'running', 'paused', 'pausing'], true), default => false };
        if (!$allowed) return JsonResponse::error('INVALID_JOB_STATUS', 'Job cannot be changed from status: ' . $current, 409);
        if ($status === 'queued' && ($result['mode'] ?? 'import') !== 'dry_run' && empty($this->actor()['is_root'])) return JsonResponse::error('TOGGL_ROOT_REQUIRED', 'Full Toggl imports require a root user', 403);
        // A queued job is not yet claimed by the worker, so pausing/cancelling
        // it as an in-flight transitional state would leave it permanently
        // invisible to claimNextJob(). Resolve those requests immediately.
        $requestedStatus = $status;
        if ($current === 'queued' && $status === 'pausing') $requestedStatus = 'paused';
        if (in_array($current, ['draft', 'queued', 'paused', 'pausing'], true) && $status === 'cancelling') $requestedStatus = 'cancelled';
        if (!$this->repo->requestStatus($id, $requestedStatus)) return JsonResponse::error('INVALID_JOB_STATUS', 'Job changed concurrently; retry the action', 409);
        return JsonResponse::success($code, 'Job state updated');
    }

    private function client(array $connection): TogglClient { $client = new TogglClient($this->repo); $client->setConnectionId((int)$connection['id']); return $client; }

    private function buildImportService(): TogglImportService
    {
        $client = new TogglClient($this->repo); $crawler = new TogglCrawler($client, $this->repo); $writer = new TogglTargetWriter($this->container, $this->repo, $client); return new TogglImportService($this->repo, $client, $crawler, $writer);
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

    /** Keep source identity responses free of API tokens and account secrets. */
    private function safeSourceUser(array $user): array
    {
        return array_intersect_key($user, array_flip(['id','name','fullname','email','timezone','default_workspace_id']));
    }

    private function publicConnection(array $connection): array { unset($connection['access_token_encrypted'], $connection['refresh_token_encrypted'], $connection['client_id_encrypted'], $connection['client_secret_encrypted']); return $connection; }
}
