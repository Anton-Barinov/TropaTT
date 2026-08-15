<?php
declare(strict_types=1);

namespace Module\Crm\LinearMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\LinearMigration\Repository\LinearRepository;
use Module\Crm\LinearMigration\Service\EncryptionService;
use Module\Crm\LinearMigration\Service\LinearClient;
use Module\Crm\LinearMigration\Service\LinearImportService;
use PDO;

final class LinearController
{
    private PDO $pdo;
    private LinearRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->pdo = $container->get('db.pdo');
        $this->repo = new LinearRepository($this->pdo);
    }

    private function requestBody(): array
    {
        $req = $this->container->get('request');
        $raw = $req->rawBody ?? '';
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function query(): array
    {
        $req = $this->container->get('request');
        return $req->query ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function actor(): array
    {
        $auth = $this->container->get('auth_user');
        return is_array($auth['user'] ?? null) ? $auth['user'] : [];
    }

    private function actorUserId(): int
    {
        $id = (int)($this->actor()['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $publicId = (string)($this->actor()['public_id'] ?? '');
        if ($publicId === '') {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function hasPermission(string $code): bool
    {
        $user = $this->actor();
        if (!empty($user['is_root'])) {
            return true;
        }
        $perms = array_map('strval', (array)($user['permission_codes'] ?? []));
        return in_array('*', $perms, true) || in_array($code, $perms, true);
    }

    /**
     * @param array<string, mixed> $connection
     */
    private function canAccessConnection(array $connection): bool
    {
        if ($this->hasPermission('module.linear-migration.manage')) {
            return true;
        }
        return (int)($connection['created_by_user_id'] ?? 0) === $this->actorUserId();
    }

    /**
     * @param array<string, mixed> $connection
     * @return array<string, mixed>
     */
    private function sanitizeConnection(array $connection): array
    {
        unset($connection['api_key_encrypted']);
        return $connection;
    }

    // ── Connections ──

    public function listConnections(): JsonResponse
    {
        if (!$this->hasPermission('module.linear-migration.view') && !$this->hasPermission('module.linear-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $isManager = $this->hasPermission('module.linear-migration.manage');
        $userId = $this->actorUserId();
        $connections = array_values(array_filter(
            $this->repo->listConnections(),
            fn(array $c): bool => $isManager || (int)($c['created_by_user_id'] ?? 0) === $userId
        ));
        return JsonResponse::success('CONNECTIONS_LIST', 'OK', ['connections' => $connections]);
    }

    public function createConnection(): JsonResponse
    {
        if (!$this->hasPermission('module.linear-migration.manage') || !$this->hasPermission('module.linear-migration.secret_manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $body = $this->requestBody();
        $name = trim((string)($body['name'] ?? ''));
        $apiKey = trim((string)($body['api_key'] ?? ''));
        if ($name === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'Name is required', 422);
        }
        if (!preg_match('#^lin_api_[A-Za-z0-9]+$#', $apiKey)) {
            return JsonResponse::error('VALIDATION_ERROR', 'API key must be a Linear personal API key (lin_api_...)', 422);
        }
        $connection = $this->repo->createConnection([
            'name' => $name,
            'workspace_name' => trim((string)($body['workspace_name'] ?? '')) ?: null,
            'api_key_encrypted' => EncryptionService::encrypt($apiKey),
            'created_by_user_id' => $this->actorUserId(),
        ]);
        return JsonResponse::success('CONNECTION_CREATED', 'Connection created', ['connection' => $this->sanitizeConnection($connection)], 201);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getConnection(array $params): JsonResponse
    {
        $connection = $this->repo->getConnection((string)$params['public_id']);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        return JsonResponse::success('CONNECTION', 'OK', ['connection' => $this->sanitizeConnection($connection)]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function updateConnection(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.linear-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $connection = $this->repo->getConnection((string)$params['public_id']);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $body = $this->requestBody();
        $update = [];
        if (array_key_exists('name', $body)) {
            $name = trim((string)$body['name']);
            if ($name === '') {
                return JsonResponse::error('VALIDATION_ERROR', 'Name is required', 422);
            }
            $update['name'] = $name;
        }
        if (array_key_exists('workspace_name', $body)) {
            $update['workspace_name'] = trim((string)$body['workspace_name']) ?: null;
        }
        if (array_key_exists('api_key', $body) && (string)$body['api_key'] !== '') {
            if (!$this->hasPermission('module.linear-migration.secret_manage')) {
                return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
            }
            if (!preg_match('#^lin_api_[A-Za-z0-9]+$#', (string)$body['api_key'])) {
                return JsonResponse::error('VALIDATION_ERROR', 'API key must be a Linear personal API key (lin_api_...)', 422);
            }
            $update['api_key_encrypted'] = EncryptionService::encrypt((string)$body['api_key']);
        }
        if ($update !== []) {
            $this->repo->updateConnection((string)$params['public_id'], $update);
        }
        return JsonResponse::success('CONNECTION_UPDATED', 'Connection updated', ['connection' => $this->sanitizeConnection($this->repo->getConnection((string)$params['public_id']))]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function deleteConnection(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.linear-migration.delete')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $connection = $this->repo->getConnection((string)$params['public_id']);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $this->repo->deleteConnection((string)$params['public_id']);
        return JsonResponse::success('CONNECTION_DELETED', 'Connection deleted');
    }

    /**
     * @param array<string, mixed> $params
     */
    public function testConnection(array $params): JsonResponse
    {
        $connection = $this->repo->getConnection((string)$params['public_id']);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $apiKey = EncryptionService::decrypt((string)($connection['api_key_encrypted'] ?? ''));
        if ($apiKey === null) {
            return JsonResponse::error('LINEAR_DECRYPT_FAILED', 'Failed to decrypt API key', 500);
        }
        $client = new LinearClient();
        $result = $client->testConnection($apiKey);
        $this->repo->updateConnectionLastCheck((string)$params['public_id'], $result['success'] ? 'success' : 'failed', $result['message']);
        if (!$result['success']) {
            return JsonResponse::error('LINEAR_AUTH_FAILED', $result['message'], 400);
        }
        return JsonResponse::success('CONNECTION_TEST_OK', 'Connection successful', ['user' => $result['user']]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function discover(array $params): JsonResponse
    {
        $connection = $this->repo->getConnection((string)$params['public_id']);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $apiKey = EncryptionService::decrypt((string)($connection['api_key_encrypted'] ?? ''));
        if ($apiKey === null) {
            return JsonResponse::error('LINEAR_DECRYPT_FAILED', 'Failed to decrypt API key', 500);
        }
        $client = new LinearClient();
        try {
            $teams = array_map(fn(array $t): array => ['id' => (string)$t['id'], 'name' => (string)($t['name'] ?? ''), 'key' => (string)($t['key'] ?? '')], $client->listTeams($apiKey));
            $projects = array_map(fn(array $p): array => ['id' => (string)$p['id'], 'name' => (string)($p['name'] ?? '')], $client->listProjects($apiKey));
        } catch (\Throwable $e) {
            error_log('[LinearController::discover] ' . $e->getMessage());
            return JsonResponse::error('DISCOVERY_FAILED', 'Discovery failed. Check server logs for details.', 502);
        }
        return JsonResponse::success('DISCOVERY_COMPLETED', 'OK', ['teams' => $teams, 'projects' => $projects]);
    }

    // ── Jobs ──

    public function listJobs(): JsonResponse
    {
        $isAdmin = $this->hasPermission('module.linear-migration.manage');
        return JsonResponse::success('JOBS_LIST', 'OK', ['jobs' => $this->repo->listJobs($isAdmin ? null : $this->actorUserId())]);
    }

    public function createJob(): JsonResponse
    {
        if (!$this->hasPermission('module.linear-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $body = $this->requestBody();
        $connPublicId = trim((string)($body['connection_public_id'] ?? ''));
        $mode = (string)($body['mode'] ?? 'dry_run');
        $teamIds = (array)($body['source_team_ids'] ?? []);
        $options = (array)($body['options'] ?? []);

        if ($connPublicId === '' || $teamIds === []) {
            return JsonResponse::error('VALIDATION_ERROR', 'connection_public_id and source_team_ids are required', 422);
        }
        if (!in_array($mode, ['dry_run', 'import'], true)) {
            return JsonResponse::error('VALIDATION_ERROR', 'mode must be dry_run or import', 422);
        }
        $connection = $this->repo->getConnection($connPublicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $job = $this->repo->createJob([
            'connection_id' => (int)$connection['id'],
            'mode' => $mode,
            'source_team_ids_json' => array_values(array_unique(array_map('strval', $teamIds))),
            'target_project_public_id' => trim((string)($body['target_project_public_id'] ?? '')) ?: null,
            'options_json' => $options,
            'created_by_user_id' => $this->actorUserId(),
        ]);

        return JsonResponse::success('JOB_CREATED', 'Job created', ['job' => $job], 201);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getJob(array $params): JsonResponse
    {
        $job = $this->repo->getJob((string)$params['public_id']);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        unset($job['api_key_encrypted']);
        return JsonResponse::success('JOB', 'OK', ['job' => $job]);
    }

    /**
     * Process the crawl (once) and one bounded import chunk.
     *
     * @param array<string, mixed> $params
     */
    public function runJob(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.linear-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)$params['public_id'];
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        if (in_array($job['status'], ['completed', 'cancelled'], true)) {
            return JsonResponse::error('INVALID_JOB_STATUS', 'Job is already in final state', 409);
        }

        $connection = $this->repo->getConnectionById((int)$job['connection_id']);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        $apiKey = EncryptionService::decrypt((string)($connection['api_key_encrypted'] ?? ''));
        if ($apiKey === null) {
            return JsonResponse::error('LINEAR_DECRYPT_FAILED', 'Failed to decrypt API key', 500);
        }

        $actor = $this->actor();
        $settings = $this->repo->getSettings();
        $batchSize = max(1, (int)($settings['batch_size'] ?? 50));

        $this->repo->updateJobStatus($publicId, 'running');

        $stats = json_decode((string)($job['stats_json'] ?? '{}'), true) ?: [];
        $import = new LinearImportService($this->container, $this->repo, new LinearClient());

        try {
            if (!($stats['crawled'] ?? false)) {
                $this->repo->updateJobProgress($publicId, 'crawl', 5, []);
                $counts = $import->crawl($job, $apiKey);
                $this->repo->addLog($publicId, 'info', 'crawl', 'Linear source graph loaded: ' . json_encode($counts, JSON_UNESCAPED_UNICODE));
                $this->repo->updateJobProgress($publicId, 'import', 10, ['crawled' => true, 'crawl_counts' => $counts]);
            }

            $result = $import->importChunk($job, $actor, $batchSize);
            $counts = $result['counts'];
            $total = (int)($counts['pending'] ?? 0) + (int)($counts['imported'] ?? 0) + (int)($counts['failed'] ?? 0) + (int)($counts['skipped'] ?? 0);
            $doneCount = (int)($counts['imported'] ?? 0) + (int)($counts['skipped'] ?? 0) + (int)($counts['failed'] ?? 0);
            $percent = $total > 0 ? min(99, ($doneCount / $total) * 100) : 100;

            if ($result['done']) {
                $this->repo->updateJobProgress($publicId, 'completed', 100, ['crawled' => true, 'counts' => $counts]);
                $this->repo->updateJobStatus($publicId, 'completed');
                $this->repo->addLog($publicId, 'info', 'completed', 'Migration completed');
            } else {
                $this->repo->updateJobProgress($publicId, 'import', $percent, ['crawled' => true, 'counts' => $counts]);
            }

            return JsonResponse::success('JOB_PROGRESS', 'OK', [
                'job' => $this->repo->getJob($publicId),
                'done' => $result['done'],
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            error_log('[LinearController::runJob] ' . $e->getMessage());
            $this->repo->updateJobStatus($publicId, 'failed');
            $this->repo->addLog($publicId, 'error', 'run', 'Job failed. Check server logs for details.');
            return JsonResponse::error('JOB_FAILED', 'Job failed. Check server logs for details.', 500);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function listJobItems(array $params): JsonResponse
    {
        $job = $this->repo->getJob((string)$params['public_id']);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        $query = $this->query();
        return JsonResponse::success('JOB_ITEMS', 'OK', [
            'items' => $this->repo->listJobItems(
                (string)$params['public_id'],
                (string)($query['status'] ?? ''),
                (string)($query['source_type'] ?? ''),
                (int)($query['limit'] ?? 100),
                (int)($query['offset'] ?? 0),
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function listJobLogs(array $params): JsonResponse
    {
        $job = $this->repo->getJob((string)$params['public_id']);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        $query = $this->query();
        return JsonResponse::success('JOB_LOGS', 'OK', ['logs' => $this->repo->listLogs((string)$params['public_id'], (int)($query['limit'] ?? 50))]);
    }
}
