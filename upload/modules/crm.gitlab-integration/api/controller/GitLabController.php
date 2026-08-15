<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\GitlabIntegration\Repository\GitLabRepository;
use Module\Crm\GitlabIntegration\Service\EncryptionService;
use Module\Crm\GitlabIntegration\Service\GitLabClient;
use Module\Crm\GitlabIntegration\Service\GitLabSyncService;
use PDO;

final class GitLabController
{
    private PDO $pdo;
    private GitLabRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->pdo = $container->get('db.pdo');
        $this->repo = new GitLabRepository($this->pdo);
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

    private function rawBody(): string
    {
        $req = $this->container->get('request');
        return (string)($req->rawBody ?? '');
    }

    private function query(): array
    {
        $req = $this->container->get('request');
        return $req->query ?? [];
    }

    private function header(string $name): string
    {
        $req = $this->container->get('request');
        return trim((string)($req->header($name, '') ?? ''));
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
        if ($this->hasPermission('module.gitlab-integration.manage')) {
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
        unset($connection['token_encrypted']);
        return $connection;
    }

    private function decryptedToken(array $connection): ?string
    {
        $token = EncryptionService::decrypt((string)($connection['token_encrypted'] ?? ''));
        if ($token === null || $token === '') {
            return null;
        }
        return $token;
    }

    private function buildWebhookUrl(string $linkPublicId): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https')
            ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/api/index.php');
        if ($script === '' || $script === '/') {
            $script = '/api/index.php';
        }
        $route = '_module/crm.gitlab-integration/webhook/' . rawurlencode($linkPublicId);
        return $scheme . '://' . $host . $script . '?route=' . rawurlencode($route);
    }

    // ── Connections ──

    public function listConnections(): JsonResponse
    {
        if (!$this->hasPermission('module.gitlab-integration.view') && !$this->hasPermission('module.gitlab-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $isManager = $this->hasPermission('module.gitlab-integration.manage');
        $userId = $this->actorUserId();
        $connections = array_values(array_filter(
            $this->repo->listConnections(),
            fn(array $c): bool => $isManager || (int)($c['created_by_user_id'] ?? 0) === $userId
        ));
        $connections = array_map(fn(array $c): array => $this->sanitizeConnection($c), $connections);
        return JsonResponse::success('CONNECTIONS_LIST', 'OK', ['connections' => $connections]);
    }

    public function createConnection(): JsonResponse
    {
        if (!$this->hasPermission('module.gitlab-integration.manage') || !$this->hasPermission('module.gitlab-integration.secret_manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $body = $this->requestBody();
        $name = trim((string)($body['name'] ?? ''));
        $token = trim((string)($body['token'] ?? ''));
        $baseUrl = trim((string)($body['base_url'] ?? 'https://gitlab.com/api/v4'));
        if ($name === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'Name is required', 422);
        }
        if ($token === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'Token is required', 422);
        }
        $baseUrl = $this->normalizeBaseUrl($baseUrl);
        if ($baseUrl === null) {
            return JsonResponse::error('VALIDATION_ERROR', 'base_url must be an https GitLab API URL', 422);
        }

        $connection = $this->repo->createConnection([
            'name' => $name,
            'base_url' => $baseUrl,
            'token_encrypted' => EncryptionService::encrypt($token),
            'created_by_user_id' => $this->actorUserId(),
        ]);
        return JsonResponse::success('CONNECTION_CREATED', 'Connection created', ['connection' => $this->sanitizeConnection($connection)], 201);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function updateConnection(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.gitlab-integration.manage')) {
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
        if (array_key_exists('base_url', $body)) {
            $baseUrl = $this->normalizeBaseUrl(trim((string)$body['base_url']));
            if ($baseUrl === null) {
                return JsonResponse::error('VALIDATION_ERROR', 'base_url must be an https GitLab API URL', 422);
            }
            $update['base_url'] = $baseUrl;
        }
        if (array_key_exists('token', $body) && (string)$body['token'] !== '') {
            if (!$this->hasPermission('module.gitlab-integration.secret_manage')) {
                return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
            }
            $update['token_encrypted'] = EncryptionService::encrypt((string)$body['token']);
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
        if (!$this->hasPermission('module.gitlab-integration.manage')) {
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
        if (!$this->hasPermission('module.gitlab-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $connection = $this->repo->getConnection((string)$params['public_id']);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $token = $this->decryptedToken($connection);
        if ($token === null) {
            return JsonResponse::error('GITLAB_DECRYPT_FAILED', 'Failed to decrypt token', 500);
        }
        $client = new GitLabClient();
        $result = $client->testConnection($token, (string)$connection['base_url']);
        $this->repo->updateConnectionLastCheck((string)$params['public_id'], $result['success'] ? 'success' : 'failed', $result['message']);
        if (!$result['success']) {
            return JsonResponse::error('GITLAB_AUTH_FAILED', $result['message'], 400);
        }
        return JsonResponse::success('CONNECTION_TEST_OK', 'Connection successful', ['username' => $result['username']]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function discover(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.gitlab-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $connection = $this->repo->getConnection((string)$params['public_id']);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $token = $this->decryptedToken($connection);
        if ($token === null) {
            return JsonResponse::error('GITLAB_DECRYPT_FAILED', 'Failed to decrypt token', 500);
        }
        $client = new GitLabClient();
        try {
            $projects = array_map(
                fn(array $p): array => [
                    'path_with_namespace' => (string)($p['path_with_namespace'] ?? ''),
                    'name' => (string)($p['name'] ?? ''),
                    'web_url' => (string)($p['web_url'] ?? ''),
                ],
                $client->listProjects($token, (string)$connection['base_url'])
            );
        } catch (\Throwable $e) {
            error_log('[GitLabController::discover] ' . $e->getMessage());
            return JsonResponse::error('DISCOVERY_FAILED', 'Discovery failed. Check server logs for details.', 502);
        }
        return JsonResponse::success('DISCOVERY_COMPLETED', 'OK', ['projects' => $projects]);
    }

    // ── Project links ──

    public function listLinks(): JsonResponse
    {
        if (!$this->hasPermission('module.gitlab-integration.view') && !$this->hasPermission('module.gitlab-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $query = $this->query();
        $connectionPublicId = trim((string)($query['connection_public_id'] ?? ''));
        $connectionId = null;
        if ($connectionPublicId !== '') {
            $connection = $this->repo->getConnection($connectionPublicId);
            $connectionId = $connection ? (int)$connection['id'] : null;
        }
        $links = array_map(fn(array $l): array => $this->sanitizeLink($l), $this->repo->listLinks($connectionId));
        return JsonResponse::success('LINKS_LIST', 'OK', ['links' => $links]);
    }

    public function createLink(): JsonResponse
    {
        if (!$this->hasPermission('module.gitlab-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $body = $this->requestBody();
        $connectionPublicId = trim((string)($body['connection_public_id'] ?? ''));
        $projectPath = trim((string)($body['project_path'] ?? ''));
        $projectPublicId = trim((string)($body['project_public_id'] ?? ''));
        if ($connectionPublicId === '' || $projectPath === '' || $projectPublicId === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'connection_public_id, project_path and project_public_id are required', 422);
        }
        $connection = $this->repo->getConnection($connectionPublicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        if ($this->repo->findLinkByProject((int)$connection['id'], $projectPath)) {
            return JsonResponse::error('LINK_EXISTS', 'A link for this project already exists', 409);
        }

        $secret = trim((string)($body['webhook_secret'] ?? ''));
        if ($secret === '') {
            $secret = bin2hex(random_bytes(24));
        }

        $link = $this->repo->createLink([
            'connection_id' => (int)$connection['id'],
            'project_path' => $projectPath,
            'project_public_id' => $projectPublicId,
            'webhook_secret_encrypted' => EncryptionService::encrypt($secret),
            'is_active' => 1,
            'created_by_user_id' => $this->actorUserId(),
        ]);

        $publicId = (string)($link['public_id'] ?? '');
        return JsonResponse::success('LINK_CREATED', 'Link created', [
            'link' => $this->sanitizeLink($link),
            'webhook_url' => $publicId !== '' ? $this->buildWebhookUrl($publicId) : '',
            'webhook_secret' => $secret,
            'webhook_events' => ['Merge Request events', 'Note events'],
        ], 201);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function deleteLink(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.gitlab-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $link = $this->repo->getLink((string)$params['public_id']);
        if (!$link) {
            return JsonResponse::error('NOT_FOUND', 'Link not found', 404);
        }
        $this->repo->deleteLink((string)$params['public_id']);
        return JsonResponse::success('LINK_DELETED', 'Link deleted');
    }

    /**
     * @param array<string, mixed> $params
     */
    public function syncNow(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.gitlab-integration.run')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $link = $this->repo->getLink((string)$params['public_id']);
        if (!$link) {
            return JsonResponse::error('NOT_FOUND', 'Link not found', 404);
        }
        if ((int)($link['is_active'] ?? 0) !== 1) {
            return JsonResponse::error('LINK_INACTIVE', 'Link is inactive', 409);
        }
        $connection = $this->repo->getConnectionById((int)$link['connection_id']);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        $token = $this->decryptedToken($connection);
        if ($token === null) {
            return JsonResponse::error('GITLAB_DECRYPT_FAILED', 'Failed to decrypt token', 500);
        }

        $settings = $this->repo->getSettings();
        $batch = max(1, (int)($settings['batch_size'] ?? 100));
        $sync = new GitLabSyncService($this->container, $this->repo, new GitLabClient());
        try {
            $counts = $sync->syncLink($link, $token, $this->actor(), $batch, (bool)($settings['sync_comments'] ?? true));
            $this->repo->markSynced((string)$params['public_id']);
            $this->repo->addLog((int)$link['id'], 'info', 'Manual sync completed: ' . json_encode($counts, JSON_UNESCAPED_UNICODE));
            return JsonResponse::success('SYNC_COMPLETED', 'Sync completed', ['counts' => $counts]);
        } catch (\Throwable $e) {
            error_log('[GitLabController::syncNow] ' . $e->getMessage());
            $this->repo->addLog((int)$link['id'], 'error', 'Manual sync failed. Check server logs.');
            return JsonResponse::error('SYNC_FAILED', 'Sync failed. Check server logs for details.', 500);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function listLinkLogs(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.gitlab-integration.view') && !$this->hasPermission('module.gitlab-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $link = $this->repo->getLink((string)$params['public_id']);
        if (!$link) {
            return JsonResponse::error('NOT_FOUND', 'Link not found', 404);
        }
        $query = $this->query();
        return JsonResponse::success('LINK_LOGS', 'OK', ['logs' => $this->repo->listLogs((int)$link['id'], (int)($query['limit'] ?? 50))]);
    }

    // ── Incoming webhook ──

    /**
     * @param array<string, mixed> $params
     */
    public function webhook(array $params): JsonResponse
    {
        $publicId = (string)$params['public_id'];
        $link = $this->repo->getLink($publicId);
        if (!$link) {
            return JsonResponse::error('NOT_FOUND', 'Link not found', 404);
        }
        if ((int)($link['is_active'] ?? 0) !== 1) {
            return JsonResponse::error('LINK_INACTIVE', 'Link is inactive', 404);
        }

        $secret = EncryptionService::decrypt((string)($link['webhook_secret_encrypted'] ?? ''));
        if ($secret === null || $secret === '') {
            return JsonResponse::error('WEBHOOK_NOT_CONFIGURED', 'Webhook secret not configured', 500);
        }

        $providedToken = $this->header('X-Gitlab-Token');
        if ($providedToken === '' || !hash_equals($secret, $providedToken)) {
            return JsonResponse::error('INVALID_SIGNATURE', 'Invalid webhook token', 401);
        }

        $event = $this->header('X-Gitlab-Event');
        if (!in_array($event, ['Merge Request Hook', 'Note Hook', 'Issue Hook'], true)) {
            return JsonResponse::success('WEBHOOK_IGNORED', 'Event ignored', ['event' => $event]);
        }

        $this->repo->markDirty($publicId);
        $this->repo->addLog((int)$link['id'], 'info', 'Webhook event received: ' . $event);

        return JsonResponse::success('WEBHOOK_ACCEPTED', 'Webhook accepted', ['event' => $event], 202);
    }

    // ── Helpers ──

    private function normalizeBaseUrl(string $baseUrl): ?string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return null;
        }
        $host = (string)parse_url($baseUrl, PHP_URL_HOST);
        if ($host === '' || $host === false) {
            return null;
        }
        return $baseUrl;
    }

    /**
     * @param array<string, mixed> $link
     * @return array<string, mixed>
     */
    private function sanitizeLink(array $link): array
    {
        unset($link['webhook_secret_encrypted']);
        return $link;
    }
}
