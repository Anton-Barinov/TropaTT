<?php
declare(strict_types=1);

namespace Module\Crm\NotionMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\NotionMigration\Repository\NotionMigrationRepository;
use Module\Crm\NotionMigration\Service\EncryptionService;
use Module\Crm\NotionMigration\Service\NotionClient;
use PDO;

final class NotionMigrationController
{
    private PDO $pdo;
    private NotionMigrationRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->pdo = $container->get('db.pdo');
        $this->repo = new NotionMigrationRepository($this->pdo);
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

    private function actorPublicId(): string
    {
        $auth = $this->container->get('auth_user');
        $user = $auth['user'] ?? [];
        return (string)($user['public_id'] ?? '');
    }

    private function actorUserId(): int
    {
        $auth = $this->container->get('auth_user');
        $user = $auth['user'] ?? [];
        $id = (int)($user['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $publicId = (string)($user['public_id'] ?? '');
        if ($publicId === '') {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function actorHasPermission(string $code): bool
    {
        $auth = $this->container->get('auth_user');
        $user = $auth['user'] ?? [];
        if (!empty($user['is_root'])) {
            return true;
        }
        $perms = array_map('strval', (array)($user['permission_codes'] ?? []));
        return in_array('*', $perms, true) || in_array($code, $perms, true);
    }

    private function requireConnectionAccess(array $connection): void
    {
        $userId = $this->actorUserId();
        $isManager = $this->actorHasPermission('module.notion-migration.manage');
        if (!$isManager && (int)($connection['created_by_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('FORBIDDEN');
        }
    }

    private function decryptToken(array $connection): ?string
    {
        return EncryptionService::decrypt((string)($connection['token_encrypted'] ?? ''));
    }

    private function sanitizeConnection(array $connection): array
    {
        unset($connection['token_encrypted']);
        return $connection;
    }

    // ── Connections ──

    public function listConnections(): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.view')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $connections = $this->repo->listConnections();
        $userId = $this->actorUserId();
        $isManager = $this->actorHasPermission('module.notion-migration.manage');
        $connections = array_values(array_filter($connections, function ($c) use ($userId, $isManager) {
            return $isManager || (int)($c['created_by_user_id'] ?? 0) === $userId;
        }));
        return JsonResponse::success('CONNECTIONS_LIST', 'OK', ['connections' => $connections]);
    }

    public function createConnection(): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $name = trim((string)($body['name'] ?? ''));
        $token = (string)($body['token'] ?? '');

        if ($name === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'Name is required', 422);
        }
        if (!preg_match('#^secret_[A-Za-z0-9]+$#', $token)) {
            return JsonResponse::error('VALIDATION_ERROR', 'Token must be a Notion integration token (secret_...)', 422);
        }

        $encrypted = EncryptionService::encrypt($token);

        $connection = $this->repo->createConnection([
            'name' => $name,
            'workspace_name' => trim((string)($body['workspace_name'] ?? '')) ?: null,
            'token_encrypted' => $encrypted,
            'created_by_user_id' => $this->actorUserId(),
        ]);

        return JsonResponse::success('CONNECTION_CREATED', 'Connection created', ['connection' => $this->sanitizeConnection($connection)], 201);
    }

    public function getConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.view')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $connection = $this->repo->getConnection($publicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        try { $this->requireConnectionAccess($connection); } catch (\RuntimeException) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        return JsonResponse::success('CONNECTION', 'OK', ['connection' => $this->sanitizeConnection($connection)]);
    }

    public function updateConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $connection = $this->repo->getConnection($publicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        try { $this->requireConnectionAccess($connection); } catch (\RuntimeException) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $update = [];
        if (isset($body['name'])) {
            $update['name'] = trim((string)$body['name']);
        }
        if (isset($body['workspace_name'])) {
            $update['workspace_name'] = trim((string)$body['workspace_name']) ?: null;
        }
        if (isset($body['token']) && $body['token'] !== '') {
            if (!preg_match('#^secret_[A-Za-z0-9]+$#', (string)$body['token'])) {
                return JsonResponse::error('VALIDATION_ERROR', 'Token must be a Notion integration token (secret_...)', 422);
            }
            $update['token_encrypted'] = EncryptionService::encrypt((string)$body['token']);
        }

        if ($update !== []) {
            $this->repo->updateConnection($publicId, $update);
        }

        return JsonResponse::success('CONNECTION_UPDATED', 'Connection updated', ['connection' => $this->sanitizeConnection($this->repo->getConnection($publicId))]);
    }

    public function deleteConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.delete')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $connection = $this->repo->getConnection($publicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        try { $this->requireConnectionAccess($connection); } catch (\RuntimeException) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        if ($this->repo->findRunningJobsByConnection((int)$connection['id'])) {
            return JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS', 'Cannot delete connection with running jobs. Cancel or wait for completion.', 409);
        }

        $this->repo->deleteConnection($publicId);
        return JsonResponse::success('CONNECTION_DELETED', 'Connection deleted');
    }

    public function testConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.view')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $connection = $this->repo->getConnection($publicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        try { $this->requireConnectionAccess($connection); } catch (\RuntimeException) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $token = $this->decryptToken($connection);
        if ($token === null) {
            return JsonResponse::error('NOTION_AUTH_FAILED', 'Failed to decrypt token', 500);
        }

        $client = new NotionClient();
        $result = $client->testConnection($token);
        $this->repo->updateConnectionLastCheck($publicId, $result['success'] ? 'success' : 'failed', $result['message']);

        if (!$result['success']) {
            return JsonResponse::error('NOTION_AUTH_FAILED', $result['message'], 400, [
                'hint' => 'Check the integration token and that pages are shared with the integration (Connections).',
            ]);
        }

        return JsonResponse::success('CONNECTION_TEST_OK', 'Connection successful', [
            'user' => $result['user'] ?? null,
        ]);
    }

    // ── Discovery ──

    public function discoverObjects(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $connection = $this->repo->getConnection($publicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        try { $this->requireConnectionAccess($connection); } catch (\RuntimeException) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $token = $this->decryptToken($connection);
        if ($token === null) {
            return JsonResponse::error('NOTION_AUTH_FAILED', 'Failed to decrypt token', 500);
        }

        $client = new NotionClient();
        $pages = [];
        $databases = [];
        try {
            foreach ($client->searchObjects($token, 'page') as $item) {
                $pages[] = [
                    'id' => (string)$item['id'],
                    'title' => $this->extractPageTitle($item),
                    'url' => (string)($item['url'] ?? ''),
                    'parent_type' => (string)($item['parent']['type'] ?? 'workspace'),
                ];
            }
            foreach ($client->searchObjects($token, 'database') as $item) {
                $databases[] = [
                    'id' => (string)$item['id'],
                    'title' => $this->extractDatabaseTitle($item),
                    'url' => (string)($item['url'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            error_log('[NotionMigrationController::discoverObjects] ' . $e->getMessage());
            return JsonResponse::error('DISCOVERY_FAILED', 'Discovery failed. Check server logs for details.', 502);
        }

        return JsonResponse::success('DISCOVERY_COMPLETED', 'Objects discovered', [
            'pages' => $pages,
            'databases' => $databases,
        ]);
    }

    // ── Jobs ──

    public function listJobs(): JsonResponse
    {
        $isAdmin = $this->actorHasPermission('module.notion-migration.manage');
        return JsonResponse::success('JOBS_LIST', 'OK', [
            'jobs' => $this->repo->listJobs($isAdmin ? null : $this->actorUserId()),
        ]);
    }

    public function createJob(): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $connPublicId = trim((string)($body['connection_public_id'] ?? ''));
        $mode = (string)($body['mode'] ?? 'dry_run');
        $objectIds = (array)($body['source_object_ids'] ?? []);
        $targetSpacePublicId = trim((string)($body['target_root_space_public_id'] ?? ''));
        $options = (array)($body['options'] ?? []);

        if ($connPublicId === '' || $objectIds === []) {
            return JsonResponse::error('VALIDATION_ERROR', 'connection_public_id and source_object_ids are required', 422);
        }
        if (!in_array($mode, ['dry_run', 'import'], true)) {
            return JsonResponse::error('VALIDATION_ERROR', 'mode must be dry_run or import', 422);
        }

        $connection = $this->repo->getConnection($connPublicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        try { $this->requireConnectionAccess($connection); } catch (\RuntimeException) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $job = $this->repo->createJob([
            'connection_id' => (int)$connection['id'],
            'mode' => $mode,
            'source_object_ids_json' => array_values(array_unique(array_map('strval', $objectIds))),
            'target_root_space_public_id' => $targetSpacePublicId ?: null,
            'options_json' => $options,
            'created_by_user_id' => $this->actorUserId(),
        ]);

        return JsonResponse::success('NOTION_JOB_CREATED', 'Migration job created', ['job' => $job], 201);
    }

    public function getJob(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        return JsonResponse::success('JOB', 'OK', ['job' => $job]);
    }

    public function startJob(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        if (!in_array($job['status'], ['draft', 'failed', 'paused', 'cancelled'], true)) {
            return JsonResponse::error('INVALID_JOB_STATUS', 'Job cannot be started from status: ' . $job['status'], 409);
        }

        $this->repo->updateJobStatus($publicId, 'queued');
        $this->repo->addJobLog($publicId, 'info', 'queued', 'Job queued for execution');
        return JsonResponse::success('NOTION_JOB_STARTED', 'Migration job started', ['job' => $this->repo->getJob($publicId)]);
    }

    public function pauseJob(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        if ($job['status'] !== 'running') {
            return JsonResponse::error('INVALID_JOB_STATUS', 'Only running jobs can be paused', 409);
        }
        $this->repo->updateJobStatus($publicId, 'paused');
        $this->repo->addJobLog($publicId, 'info', 'paused', 'Job paused by user');
        return JsonResponse::success('JOB_PAUSED', 'Job paused', ['job' => $this->repo->getJob($publicId)]);
    }

    public function resumeJob(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        if (!in_array($job['status'], ['paused', 'failed'], true)) {
            return JsonResponse::error('INVALID_JOB_STATUS', 'Only paused or failed jobs can be resumed', 409);
        }
        $this->repo->updateJobStatus($publicId, 'queued');
        $this->repo->addJobLog($publicId, 'info', 'resumed', 'Job resumed by user');
        return JsonResponse::success('JOB_RESUMED', 'Job resumed', ['job' => $this->repo->getJob($publicId)]);
    }

    public function cancelJob(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        if (in_array($job['status'], ['completed', 'cancelled'], true)) {
            return JsonResponse::error('INVALID_JOB_STATUS', 'Job is already in final state', 409);
        }
        $this->repo->updateJobStatus($publicId, 'cancelling');
        $this->repo->addJobLog($publicId, 'info', 'cancelling', 'Job cancel requested');
        return JsonResponse::success('JOB_CANCELLING', 'Job cancel requested', ['job' => $this->repo->getJob($publicId)]);
    }

    public function retryFailed(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        if (!in_array($job['status'], ['completed', 'failed', 'cancelled'], true)) {
            return JsonResponse::error('INVALID_JOB_STATUS', 'Job cannot be retried from status: ' . $job['status'], 409);
        }
        $count = $this->repo->resetFailedItems($publicId);
        $this->repo->updateJobStatus($publicId, 'queued');
        $this->repo->addJobLog($publicId, 'info', 'retry', 'Retrying ' . $count . ' failed items');
        return JsonResponse::success('JOB_RETRY', 'Retrying ' . $count . ' failed items', ['job' => $this->repo->getJob($publicId)]);
    }

    public function listJobItems(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        $req = $this->container->get('request');
        $query = $req->query ?? [];
        return JsonResponse::success('JOB_ITEMS', 'OK', [
            'items' => $this->repo->listJobItems(
                $publicId,
                (string)($query['source_type'] ?? ''),
                (string)($query['status'] ?? ''),
                (int)($query['limit'] ?? 50),
                (int)($query['page'] ?? 1),
            ),
        ]);
    }

    public function listJobLogs(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        $req = $this->container->get('request');
        $query = $req->query ?? [];
        return JsonResponse::success('JOB_LOGS', 'OK', [
            'logs' => $this->repo->listJobLogs(
                $publicId,
                (string)($query['level'] ?? ''),
                (string)($query['step'] ?? ''),
                (int)($query['limit'] ?? 50),
                (int)($query['page'] ?? 1),
            ),
        ]);
    }

    public function getReport(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        return JsonResponse::success('JOB_REPORT', 'OK', ['report' => $this->repo->getJobReport($publicId)]);
    }

    public function downloadReport(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        $req = $this->container->get('request');
        $query = $req->query ?? [];
        $format = (string)($query['format'] ?? 'json');
        if ($format === 'markdown') {
            return JsonResponse::success('REPORT_MARKDOWN', 'OK', ['markdown' => $this->repo->getJobReportMarkdown($publicId)]);
        }
        return JsonResponse::success('REPORT_JSON', 'OK', ['report' => $this->repo->getJobReport($publicId)]);
    }

    // ── User mappings ──

    public function listUserMappings(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.view')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $connPublicId = (string)($params['public_id'] ?? '');
        $connection = $this->repo->getConnection($connPublicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        try { $this->requireConnectionAccess($connection); } catch (\RuntimeException) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        return JsonResponse::success('USER_MAPPINGS', 'OK', [
            'mappings' => $this->repo->listUserMappings((int)$connection['id']),
        ]);
    }

    public function updateUserMapping(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $mappingId = (int)($params['mapping_id'] ?? 0);
        if ($mappingId <= 0) {
            return JsonResponse::error('VALIDATION_ERROR', 'mapping_id is required', 422);
        }
        $body = $this->requestBody();
        $crmUserPublicId = trim((string)($body['crm_user_public_id'] ?? ''));
        $mappingStatus = (string)($body['mapping_status'] ?? 'manual');
        if (!in_array($mappingStatus, ['auto', 'manual', 'unmapped', 'ignored'], true)) {
            return JsonResponse::error('VALIDATION_ERROR', 'Invalid mapping_status', 422);
        }
        $this->repo->updateUserMapping($mappingId, $crmUserPublicId ?: null, $mappingStatus);
        return JsonResponse::success('USER_MAPPING_UPDATED', 'User mapping updated');
    }

    // ── Settings ──

    public function getSettings(): JsonResponse
    {
        return JsonResponse::success('SETTINGS', 'OK', ['settings' => $this->repo->getModuleSettings()]);
    }

    public function updateSettings(): JsonResponse
    {
        if (!$this->actorHasPermission('module.notion-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $body = $this->requestBody();
        $allowed = ['request_timeout_seconds', 'max_retries', 'default_batch_size', 'max_pages_per_job', 'max_depth', 'include_comments_by_default', 'publish_by_default'];
        foreach ($body as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $this->repo->setModuleSetting($key, $value);
            }
        }
        return JsonResponse::success('SETTINGS_UPDATED', 'Settings updated', ['settings' => $this->repo->getModuleSettings()]);
    }

    // ── Helpers ──

    private function extractPageTitle(array $page): string
    {
        foreach (($page['properties'] ?? []) as $prop) {
            if (($prop['type'] ?? '') === 'title') {
                $parts = [];
                foreach (($prop['title'] ?? []) as $rt) {
                    if (($rt['plain_text'] ?? '') !== '') {
                        $parts[] = $rt['plain_text'];
                    }
                }
                return trim(implode('', $parts));
            }
        }
        return '';
    }

    private function extractDatabaseTitle(array $database): string
    {
        $parts = [];
        foreach (($database['title'] ?? []) as $rt) {
            if (($rt['plain_text'] ?? '') !== '') {
                $parts[] = $rt['plain_text'];
            }
        }
        return trim(implode('', $parts));
    }
}
