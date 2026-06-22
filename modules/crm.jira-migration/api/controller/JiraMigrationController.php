<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Controller;

use Api\System\Library\Http\JsonResponse;
use Module\Crm\JiraMigration\Repository\JiraMigrationRepository;
use Module\Crm\JiraMigration\Service\JiraClient;
use Module\Crm\JiraMigration\Service\JiraMappingService;
use Module\Crm\JiraMigration\Service\EncryptionService;
use PDO;

final class JiraMigrationController
{
    private PDO $pdo;
    private JiraMigrationRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->pdo = $container->get('db.pdo');
        $this->repo = new JiraMigrationRepository($this->pdo);
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
        $isManager = $this->actorHasPermission('module.jira-migration.manage');
        if (!$isManager && (int)($connection['created_by_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('FORBIDDEN');
        }
    }

    // ── Connections ──

    public function listConnections(): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.view')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        return JsonResponse::success('JIRA_CONNECTIONS_LIST', 'OK', [
            'connections' => $this->repo->listConnections($this->actorPublicId()),
        ]);
    }

    public function createConnection(): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $name = trim((string)($body['name'] ?? ''));
        $siteUrl = trim((string)($body['site_url'] ?? ''));
        $authType = (string)($body['auth_type'] ?? 'api_token');
        $email = trim((string)($body['email'] ?? ''));
        $apiToken = (string)($body['api_token'] ?? '');

        if ($name === '' || $siteUrl === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'Name and site_url are required', 422);
        }

        // Validate Jira URL
        if (!preg_match('#^https://[a-zA-Z0-9._-]+\.atlassian\.net(:[0-9]+)?/?$#', $siteUrl)) {
            return JsonResponse::error('SSRF_BLOCKED', 'Only https://*.atlassian.net URLs are allowed by default', 422);
        }

        $siteUrl = rtrim($siteUrl, '/');

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
            'site_url' => $siteUrl,
            'auth_type' => $authType,
            'email' => $email ?: null,
            'token_encrypted' => $encrypted,
            'created_by_user_id' => $this->actorUserId(),
        ]);

        unset($connection['token_encrypted'], $connection['oauth_payload_json']);
        return JsonResponse::success('JIRA_CONNECTION_CREATED', 'Connection created', ['connection' => $connection], 201);
    }

    public function getConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.view')) {
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
        unset($connection['token_encrypted'], $connection['oauth_payload_json']);
        return JsonResponse::success('JIRA_CONNECTION', 'OK', ['connection' => $connection]);
    }

    public function updateConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.manage')) {
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
        if (isset($body['site_url'])) {
            $update['site_url'] = rtrim(trim((string)$body['site_url']), '/');
        }

        if ($update !== []) {
            $this->repo->updateConnection($publicId, $update);
        }

        $connection = $this->repo->getConnection($publicId);
        unset($connection['token_encrypted'], $connection['oauth_payload_json']);
        return JsonResponse::success('JIRA_CONNECTION_UPDATED', 'Connection updated', ['connection' => $connection]);
    }

    public function deleteConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.delete')) {
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

        $running = $this->repo->findRunningJobsByConnection((int)$connection['id']);
        if ($running) {
            return JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS', 'Cannot delete connection with running jobs', 409);
        }

        $this->repo->deleteConnection($publicId);
        return JsonResponse::success('JIRA_CONNECTION_DELETED', 'Connection deleted');
    }

    public function testConnection(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.view')) {
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
            return JsonResponse::error('JIRA_AUTH_FAILED', 'Failed to decrypt token', 500);
        }

        $client = new JiraClient(repo: $this->repo);
        $client->setConnectionId((int)$connection['id']);
        $result = $client->testConnection(
            (string)$connection['site_url'],
            (string)$connection['email'],
            $token
        );

        $this->repo->updateConnectionLastCheck($publicId, $result['success'] ? 'active' : 'failed', $result['message']);

        if (!$result['success']) {
            return JsonResponse::error('JIRA_AUTH_FAILED', $result['message'], 400, [
                'hint' => 'Check site URL, email, and API token.',
            ]);
        }

        return JsonResponse::success('JIRA_CONNECTION_TEST_OK', 'Connection successful', [
            'user' => $result['user'] ?? null,
            'server_info' => $result['server_info'] ?? null,
            'projects_count' => $result['projects_count'] ?? 0,
        ]);
    }

    // ── Discovery ──

    public function discover(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.view')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $connectionPublicId = trim((string)($body['connection_public_id'] ?? ''));

        $connection = $this->repo->getConnection($connectionPublicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        try { $this->requireConnectionAccess($connection); } catch (\RuntimeException) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $token = EncryptionService::decrypt((string)($connection['token_encrypted'] ?? ''));
        if ($token === null) {
            return JsonResponse::error('JIRA_AUTH_FAILED', 'Failed to decrypt token', 500);
        }

        $client = new JiraClient(repo: $this->repo);
        $client->setConnectionId((int)$connection['id']);

        $siteUrl = (string)$connection['site_url'];
        $email = (string)$connection['email'];

        // Get projects
        $projects = $client->getProjects($siteUrl, $email, $token);

        // Get fields
        $fields = $client->getFields($siteUrl, $email, $token);

        // Get statuses (try)
        $statuses = [];
        try {
            $statuses = $client->getStatuses($siteUrl, $email, $token);
        } catch (\Throwable) {
        }

        // Get boards (try Jira Software API)
        $boards = [];
        try {
            $boards = $client->getBoards($siteUrl, $email, $token);
        } catch (\Throwable) {
        }

        return JsonResponse::success('JIRA_DISCOVERY_COMPLETED', 'Discovery completed', [
            'projects' => $projects,
            'fields' => $fields,
            'statuses' => $statuses,
            'boards' => $boards,
        ]);
    }

    // ── Dry Run ──

    public function createDryRun(): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $connPublicId = trim((string)($body['connection_public_id'] ?? ''));
        $projectKeys = (array)($body['project_keys'] ?? []);

        if ($connPublicId === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'connection_public_id is required', 422);
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
            'mode' => 'dry_run',
            'source_scope_json' => ['project_keys' => $projectKeys],
            'target_options_json' => [],
            'created_by_user_id' => $this->actorUserId(),
        ]);

        $token = EncryptionService::decrypt((string)($connection['token_encrypted'] ?? ''));
        if ($token === null) {
            return JsonResponse::error('JIRA_AUTH_FAILED', 'Failed to decrypt token', 500);
        }

        // Run dry-run inline for MVP
        $client = new JiraClient(repo: $this->repo);
        $client->setConnectionId((int)$connection['id']);

        $crawler = new \Module\Crm\JiraMigration\Service\JiraCrawler($client, $this->repo);
        $result = $crawler->crawlProjects($job, (string)$connection['site_url'], (string)$connection['email'], $token);

        $this->repo->updateJobStatus((string)$job['public_id'], 'completed');
        $this->repo->updateJobProgress((string)$job['public_id'], 'dry_run_complete', 100, $result);

        return JsonResponse::success('JIRA_DRY_RUN_COMPLETED', 'Dry run completed', [
            'job' => $this->repo->getJob((string)$job['public_id']),
            'summary' => $result,
        ]);
    }

    // ── Jobs ──

    public function listJobs(): JsonResponse
    {
        $actorPub = $this->actorPublicId();
        $isAdmin = $this->actorHasPermission('module.jira-migration.manage');
        return JsonResponse::success('JIRA_JOBS_LIST', 'OK', [
            'jobs' => $this->repo->listJobs($isAdmin ? null : $actorPub),
        ]);
    }

    public function createJob(): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $connPublicId = trim((string)($body['connection_public_id'] ?? ''));
        $mode = (string)($body['mode'] ?? 'import');
        $projectKeys = (array)($body['project_keys'] ?? []);
        $options = (array)($body['options'] ?? []);

        if ($connPublicId === '' || $projectKeys === []) {
            return JsonResponse::error('VALIDATION_ERROR', 'connection_public_id and project_keys are required', 422);
        }

        if (!in_array($mode, ['import', 'sync'], true)) {
            return JsonResponse::error('VALIDATION_ERROR', 'mode must be import or sync', 422);
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
            'source_scope_json' => ['project_keys' => $projectKeys],
            'target_options_json' => $options,
            'created_by_user_id' => $this->actorUserId(),
        ]);

        return JsonResponse::success('JIRA_JOB_CREATED', 'Migration job created', ['job' => $job], 201);
    }

    public function getJob(array $params = []): JsonResponse
    {
        $publicId = (string)($params['public_id'] ?? '');
        $job = $this->repo->getJob($publicId);
        if (!$job) {
            return JsonResponse::error('NOT_FOUND', 'Job not found', 404);
        }
        return JsonResponse::success('JIRA_JOB', 'OK', ['job' => $job]);
    }

    public function startJob(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.run')) {
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

        return JsonResponse::success('JIRA_JOB_STARTED', 'Migration job started', [
            'job' => $this->repo->getJob($publicId),
        ]);
    }

    public function pauseJob(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.run')) {
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
        return JsonResponse::success('JIRA_JOB_PAUSED', 'Job paused', ['job' => $this->repo->getJob($publicId)]);
    }

    public function cancelJob(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.run')) {
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
        $this->repo->addJobLog($publicId, 'info', 'cancelling', 'Job cancel requested');
        return JsonResponse::success('JIRA_JOB_CANCELLING', 'Job cancel requested', ['job' => $this->repo->getJob($publicId)]);
    }

    public function retryFailed(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.run')) {
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
        return JsonResponse::success('JIRA_JOB_RETRY', 'Retrying ' . $count . ' failed items', ['job' => $this->repo->getJob($publicId)]);
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
        return JsonResponse::success('JIRA_JOB_ITEMS', 'OK', [
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
        return JsonResponse::success('JIRA_JOB_LOGS', 'OK', [
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
        return JsonResponse::success('JIRA_JOB_REPORT', 'OK', [
            'report' => $this->repo->getJobReport($publicId),
        ]);
    }

    // ── Mappings ──

    public function listMappings(): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.view')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $req = $this->container->get('request');
        $query = $req->query ?? [];
        $connectionPublicId = trim((string)($query['connection_public_id'] ?? ''));
        if ($connectionPublicId === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'connection_public_id is required as query parameter', 422);
        }

        $connection = $this->repo->getConnection($connectionPublicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        try { $this->requireConnectionAccess($connection); } catch (\RuntimeException) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        return JsonResponse::success('JIRA_MAPPINGS', 'OK', [
            'mappings' => $this->repo->listMappings((int)$connection['id']),
        ]);
    }

    public function updateMapping(array $params = []): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $publicId = (string)($params['public_id'] ?? '');
        $body = $this->requestBody();
        $crmSubjectType = trim((string)($body['crm_subject_type'] ?? ''));
        $crmSubjectPublicId = trim((string)($body['crm_subject_public_id'] ?? ''));
        $status = (string)($body['status'] ?? 'mapped');

        $this->repo->updateMapping($publicId, $crmSubjectType ?: null, $crmSubjectPublicId ?: null, $status);
        return JsonResponse::success('JIRA_MAPPING_UPDATED', 'Mapping updated');
    }

    // ── Unresolved ──

    public function listUnresolved(): JsonResponse
    {
        if (!$this->actorHasPermission('module.jira-migration.view')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $req = $this->container->get('request');
        $query = $req->query ?? [];
        $jobPublicId = trim((string)($query['job_public_id'] ?? ''));
        if ($jobPublicId === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'job_public_id is required as query parameter', 422);
        }

        return JsonResponse::success('JIRA_UNRESOLVED', 'OK', [
            'items' => $this->repo->listUnresolvedEntities($jobPublicId),
        ]);
    }
}
