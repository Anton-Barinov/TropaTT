<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\ConfluenceMigration\Repository\ConfluenceMigrationRepository;
use Module\Crm\ConfluenceMigration\Service\ConfluenceClient;
use Module\Crm\ConfluenceMigration\Service\ConfluenceCrawler;
use Module\Crm\ConfluenceMigration\Service\ConfluenceImportService;
use Module\Crm\ConfluenceMigration\Service\EncryptionService;
use PDO;

final class ConfluenceMigrationController
{
    private PDO $pdo;
    private ConfluenceMigrationRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->pdo = $container->get('db.pdo');
        $this->repo = new ConfluenceMigrationRepository($this->pdo);
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
        $isManager = $this->actorHasPermission('module.confluence-migration.manage');
        if (!$isManager && (int)($connection['created_by_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('FORBIDDEN');
        }
    }

    // ── Connections ──

    public function listConnections(): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.view')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        return JsonResponse::success('CONNECTIONS_LIST', 'OK', [
            'connections' => $this->repo->listConnections($this->actorPublicId()),
        ]);
    }

    public function createConnection(): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $name = trim((string)($body['name'] ?? ''));
        $baseUrl = trim((string)($body['base_url'] ?? ''));
        $authType = (string)($body['auth_type'] ?? 'api_token');
        $email = trim((string)($body['email'] ?? ''));
        $apiToken = (string)($body['api_token'] ?? '');

        if ($name === '' || $baseUrl === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'Name and base_url are required', 422);
        }

        if (!preg_match('#^https://[a-zA-Z0-9.-]+(:[0-9]+)?/wiki$#', $baseUrl) && !preg_match('#^https://[a-zA-Z0-9.-]+(:[0-9]+)?/wiki/#', $baseUrl)) {
            return JsonResponse::error('SSRF_BLOCKED', 'Only https://*.atlassian.net/wiki URLs are allowed by default', 422);
        }

        $baseUrl = rtrim($baseUrl, '/');
        if (!str_ends_with($baseUrl, '/wiki')) {
            $baseUrl .= '/wiki';
        }

        if ($authType === 'api_token') {
            if ($email === '' || $apiToken === '') {
                return JsonResponse::error('VALIDATION_ERROR', 'Email and api_token are required for api_token auth', 422);
            }
            $encrypted = EncryptionService::encrypt($apiToken);
        } else {
            return JsonResponse::error('VALIDATION_ERROR', 'Only api_token auth is supported in this version', 422);
        }

        $connection = $this->repo->createConnection([
            'name' => $name,
            'base_url' => $baseUrl,
            'auth_type' => $authType,
            'email' => $email ?: null,
            'token_encrypted' => $encrypted,
            'created_by_user_id' => $this->actorUserId(),
        ]);

        unset($connection['token_encrypted'], $connection['oauth_payload_encrypted']);
        return JsonResponse::success('CONNECTION_CREATED', 'Connection created', ['connection' => $connection], 201);
    }

    public function getConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.view')) {
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
        unset($connection['token_encrypted'], $connection['oauth_payload_encrypted']);
        return JsonResponse::success('CONNECTION', 'OK', ['connection' => $connection]);
    }

    public function updateConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.manage')) {
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
        if (isset($body['email'])) {
            $update['email'] = trim((string)$body['email']) ?: null;
        }
        if (isset($body['api_token']) && $body['api_token'] !== '') {
            $update['token_encrypted'] = EncryptionService::encrypt((string)$body['api_token']);
        }
        if (isset($body['base_url'])) {
            $update['base_url'] = rtrim(trim((string)$body['base_url']), '/');
            if (!str_ends_with($update['base_url'], '/wiki')) {
                $update['base_url'] .= '/wiki';
            }
        }

        if ($update !== []) {
            $this->repo->updateConnection($publicId, $update);
        }

        $connection = $this->repo->getConnection($publicId);
        unset($connection['token_encrypted'], $connection['oauth_payload_encrypted']);
        return JsonResponse::success('CONNECTION_UPDATED', 'Connection updated', ['connection' => $connection]);
    }

    public function deleteConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.delete')) {
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

        // Check for running jobs
        $running = $this->repo->findRunningJobsByConnection((int)$connection['id']);
        if ($running) {
            return JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS', 'Cannot delete connection with running jobs. Cancel or wait for completion.', 409);
        }

        $this->repo->deleteConnection($publicId);
        return JsonResponse::success('CONNECTION_DELETED', 'Connection deleted');
    }

    public function testConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.view')) {
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

        $token = EncryptionService::decrypt((string)($connection['token_encrypted'] ?? ''));
        if ($token === null) {
            return JsonResponse::error('CONFLUENCE_AUTH_FAILED', 'Failed to decrypt token', 500);
        }

        $client = new ConfluenceClient(repo: $this->repo);
        $client->setConnectionId((int)$connection['id']);
        $result = $client->testConnection(
            (string)$connection['base_url'],
            (string)$connection['email'],
            $token
        );

        $this->repo->updateConnectionLastCheck($publicId, $result['success'] ? 'success' : 'failed', $result['message']);

        if (!$result['success']) {
            return JsonResponse::error('CONFLUENCE_AUTH_FAILED', $result['message'], 400, [
                'hint' => 'Check email and API token. Ensure the account has access to at least one space.',
            ]);
        }

        return JsonResponse::success('CONNECTION_TEST_OK', 'Connection successful', [
            'user' => $result['user'] ?? null,
            'spaces_count' => $result['spaces_count'] ?? 0,
        ]);
    }

    // ── Discovery ──

    public function discoverSpaces(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.run')) {
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

        $token = EncryptionService::decrypt((string)($connection['token_encrypted'] ?? ''));
        if ($token === null) {
            return JsonResponse::error('CONFLUENCE_AUTH_FAILED', 'Failed to decrypt token', 500);
        }

        $body = $this->requestBody();
        $spaceKeys = (array)($body['space_keys'] ?? []);
        $includeArchived = !empty($body['include_archived']);
        $sampleLimit = (int)($body['sample_limit'] ?? 100);

        $client = new ConfluenceClient(repo: $this->repo);
        $client->setConnectionId((int)$connection['id']);
        $spaces = $client->getSpaces(
            (string)$connection['base_url'],
            (string)$connection['email'],
            $token,
            $spaceKeys !== [] ? $spaceKeys : null,
            $includeArchived,
        );

        // For each space, get page count estimate
        $result = [];
        foreach ($spaces as $space) {
            $pagesCount = 0;
            $attachmentsEstimate = 0;
            $labelsCount = 0;

            try {
                $pages = $client->getPagesForSpace(
                    (string)$connection['base_url'],
                    (string)$connection['email'],
                    $token,
                    (string)$space['id'],
                    0,
                    $sampleLimit
                );
                $pagesCount = $pages['totalCount'] ?? 0;
                $attachmentsEstimate = (int)($pagesCount * 1.5);
            } catch (\Throwable) {
            }

            $result[] = [
                'id' => $space['id'],
                'key' => $space['key'],
                'name' => $space['name'],
                'pages_count' => $pagesCount,
                'attachments_count_estimate' => $attachmentsEstimate,
                'labels_count_estimate' => $labelsCount,
            ];
        }

        return JsonResponse::success('DISCOVERY_COMPLETED', 'Spaces discovered', [
            'spaces' => $result,
            'warnings' => [],
        ]);
    }

    // ── Jobs ──

    public function listJobs(): JsonResponse
    {
        $actorPub = $this->actorPublicId();
        $isAdmin = $this->actorHasPermission('module.confluence-migration.manage');
        return JsonResponse::success('JOBS_LIST', 'OK', [
            'jobs' => $this->repo->listJobs($isAdmin ? null : $actorPub),
        ]);
    }

    public function createJob(): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $connPublicId = trim((string)($body['connection_public_id'] ?? ''));
        $mode = (string)($body['mode'] ?? 'dry_run');
        $spaceKeys = (array)($body['source_space_keys'] ?? []);
        $targetSpacePublicId = trim((string)($body['target_root_space_public_id'] ?? ''));
        $options = (array)($body['options'] ?? []);

        if ($connPublicId === '' || $spaceKeys === []) {
            return JsonResponse::error('VALIDATION_ERROR', 'connection_public_id and source_space_keys are required', 422);
        }

        if (!in_array($mode, ['dry_run', 'import', 'sync'], true)) {
            return JsonResponse::error('VALIDATION_ERROR', 'mode must be dry_run, import, or sync', 422);
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
            'source_space_keys_json' => $spaceKeys,
            'target_root_space_public_id' => $targetSpacePublicId ?: null,
            'options_json' => $options,
            'created_by_user_id' => $this->actorUserId(),
        ]);

        return JsonResponse::success('CONFLUENCE_JOB_CREATED', 'Migration job created', ['job' => $job], 201);
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
        if (!$this->actorHasPermission('module.confluence-migration.run')) {
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

        // In a full implementation, the worker would pick this up.
        // For direct execution (dev mode), we can optionally run inline.
        // For production, run the CLI worker: php modules/crm.confluence-migration/api/scripts/run_worker.php --job=cij_xxx --limit=50

        return JsonResponse::success('CONFLUENCE_JOB_STARTED', 'Migration job started', [
            'job' => $this->repo->getJob($publicId),
        ]);
    }

    public function pauseJob(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.run')) {
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
        if (!$this->actorHasPermission('module.confluence-migration.run')) {
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
        if (!$this->actorHasPermission('module.confluence-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }

        if (in_array($job['status'], ['completed', 'cancelled', 'archived'], true)) {
            return JsonResponse::error('INVALID_JOB_STATUS', 'Job is already in final state', 409);
        }

        $this->repo->updateJobStatus($publicId, 'cancelling');
        $this->repo->addJobLog($publicId, 'info', 'cancelling', 'Job cancel requested — worker will finish current item then stop');
        return JsonResponse::success('JOB_CANCELLING', 'Job cancel requested', ['job' => $this->repo->getJob($publicId)]);
    }

    public function retryFailed(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.run')) {
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

        return JsonResponse::success('JOB_REPORT', 'OK', [
            'report' => $this->repo->getJobReport($publicId),
        ]);
    }

    public function listUnresolvedLinks(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }

        $req = $this->container->get('request');
        $query = $req->query ?? [];

        return JsonResponse::success('UNRESOLVED_LINKS', 'OK', [
            'links' => $this->repo->listUnresolvedLinks(
                $publicId,
                (string)($query['reason'] ?? ''),
                (string)($query['source_page_id'] ?? ''),
                (int)($query['limit'] ?? 50),
                (int)($query['page'] ?? 1),
            ),
        ]);
    }

    public function listUnsupportedMacros(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }

        return JsonResponse::success('UNSUPPORTED_MACROS', 'OK', [
            'macros' => $this->repo->listUnsupportedMacros($publicId),
        ]);
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
            return JsonResponse::success('REPORT_MARKDOWN', 'OK', [
                'markdown' => $this->repo->getJobReportMarkdown($publicId),
            ]);
        }

        return JsonResponse::success('REPORT_JSON', 'OK', [
            'report' => $this->repo->getJobReport($publicId),
        ]);
    }

    // ── User/Group Mappings ──

    public function listUserMappings(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.view')) {
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
        if (!$this->actorHasPermission('module.confluence-migration.manage')) {
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

    public function listGroupMappings(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.view')) {
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

        return JsonResponse::success('GROUP_MAPPINGS', 'OK', [
            'mappings' => $this->repo->listGroupMappings((int)$connection['id']),
        ]);
    }

    public function updateGroupMapping(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $mappingId = (int)($params['mapping_id'] ?? 0);
        if ($mappingId <= 0) {
            return JsonResponse::error('VALIDATION_ERROR', 'mapping_id is required', 422);
        }

        $body = $this->requestBody();
        $subjectType = (string)($body['crm_subject_type'] ?? '');
        $subjectPublicId = trim((string)($body['crm_subject_public_id'] ?? ''));
        $mappingStatus = (string)($body['mapping_status'] ?? 'manual');

        if (!in_array($mappingStatus, ['manual', 'unmapped', 'ignored'], true)) {
            return JsonResponse::error('VALIDATION_ERROR', 'Invalid mapping_status', 422);
        }

        $this->repo->updateGroupMapping($mappingId, $subjectType ?: null, $subjectPublicId ?: null, $mappingStatus);
        return JsonResponse::success('GROUP_MAPPING_UPDATED', 'Group mapping updated');
    }

    // ── Settings ──

    public function getSettings(): JsonResponse
    {
        $settings = $this->repo->getModuleSettings();
        return JsonResponse::success('SETTINGS', 'OK', ['settings' => $settings]);
    }

    public function updateSettings(): JsonResponse
    {
        if (!$this->actorHasPermission('module.confluence-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $allowed = ['max_attachment_size_mb', 'allowed_confluence_hosts', 'custom_domain_allowlist', 'default_batch_size', 'request_timeout_seconds', 'max_retries'];

        foreach ($body as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $this->repo->setModuleSetting($key, $value);
            }
        }

        return JsonResponse::success('SETTINGS_UPDATED', 'Settings updated', [
            'settings' => $this->repo->getModuleSettings(),
        ]);
    }
}
