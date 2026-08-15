<?php
declare(strict_types=1);

namespace Module\Crm\GithubIntegration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\GithubIntegration\Repository\GitHubRepository;
use Module\Crm\GithubIntegration\Service\EncryptionService;
use Module\Crm\GithubIntegration\Service\GitHubClient;
use Module\Crm\GithubIntegration\Service\GitHubSyncService;
use PDO;

final class GitHubController
{
    private PDO $pdo;
    private GitHubRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->pdo = $container->get('db.pdo');
        $this->repo = new GitHubRepository($this->pdo);
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
        if ($this->hasPermission('module.github-integration.manage')) {
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

    /**
     * Build the public webhook URL from the current request so the module works
     * on any domain / subdomain / sub-path without hard-coded URLs.
     */
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
        $route = '_module/crm.github-integration/webhook/' . rawurlencode($linkPublicId);
        return $scheme . '://' . $host . $script . '?route=' . rawurlencode($route);
    }

    // ── Connections ──

    public function listConnections(): JsonResponse
    {
        if (!$this->hasPermission('module.github-integration.view') && !$this->hasPermission('module.github-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $isManager = $this->hasPermission('module.github-integration.manage');
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
        if (!$this->hasPermission('module.github-integration.manage') || !$this->hasPermission('module.github-integration.secret_manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $body = $this->requestBody();
        $name = trim((string)($body['name'] ?? ''));
        $token = trim((string)($body['token'] ?? ''));
        $baseUrl = trim((string)($body['base_url'] ?? 'https://api.github.com'));
        if ($name === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'Name is required', 422);
        }
        if ($token === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'Token is required', 422);
        }
        $baseUrl = $this->normalizeBaseUrl($baseUrl);
        if ($baseUrl === null) {
            return JsonResponse::error('VALIDATION_ERROR', 'base_url must be an https GitHub API URL', 422);
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
        if (!$this->hasPermission('module.github-integration.manage')) {
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
                return JsonResponse::error('VALIDATION_ERROR', 'base_url must be an https GitHub API URL', 422);
            }
            $update['base_url'] = $baseUrl;
        }
        if (array_key_exists('token', $body) && (string)$body['token'] !== '') {
            if (!$this->hasPermission('module.github-integration.secret_manage')) {
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
        if (!$this->hasPermission('module.github-integration.manage')) {
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
        if (!$this->hasPermission('module.github-integration.manage')) {
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
            return JsonResponse::error('GITHUB_DECRYPT_FAILED', 'Failed to decrypt token', 500);
        }
        $client = new GitHubClient();
        $result = $client->testConnection($token, (string)$connection['base_url']);
        $this->repo->updateConnectionLastCheck((string)$params['public_id'], $result['success'] ? 'success' : 'failed', $result['message']);
        if (!$result['success']) {
            return JsonResponse::error('GITHUB_AUTH_FAILED', $result['message'], 400);
        }
        return JsonResponse::success('CONNECTION_TEST_OK', 'Connection successful', ['login' => $result['login']]);
    }

    /**
     * List repositories accessible with the connection token.
     *
     * @param array<string, mixed> $params
     */
    public function discover(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.github-integration.manage')) {
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
            return JsonResponse::error('GITHUB_DECRYPT_FAILED', 'Failed to decrypt token', 500);
        }
        $client = new GitHubClient();
        try {
            $repos = array_map(
                fn(array $r): array => [
                    'full_name' => (string)($r['full_name'] ?? ''),
                    'name' => (string)($r['name'] ?? ''),
                    'owner' => (string)($r['owner']['login'] ?? ''),
                    'private' => (bool)($r['private'] ?? false),
                ],
                $client->listRepos($token, (string)$connection['base_url'])
            );
        } catch (\Throwable $e) {
            error_log('[GitHubController::discover] ' . $e->getMessage());
            return JsonResponse::error('DISCOVERY_FAILED', 'Discovery failed. Check server logs for details.', 502);
        }
        return JsonResponse::success('DISCOVERY_COMPLETED', 'OK', ['repositories' => $repos]);
    }

    // ── Repo links ──

    public function listLinks(): JsonResponse
    {
        if (!$this->hasPermission('module.github-integration.view') && !$this->hasPermission('module.github-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $query = $this->query();
        $connectionPublicId = trim((string)($query['connection_public_id'] ?? ''));
        $connectionId = null;
        if ($connectionPublicId !== '') {
            $connection = $this->repo->getConnection($connectionPublicId);
            $connectionId = $connection ? (int)$connection['id'] : null;
        }
        $links = array_map(
            fn(array $l): array => $this->sanitizeLink($l),
            $this->repo->listLinks($connectionId)
        );
        return JsonResponse::success('LINKS_LIST', 'OK', ['links' => $links]);
    }

    public function createLink(): JsonResponse
    {
        if (!$this->hasPermission('module.github-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $body = $this->requestBody();
        $connectionPublicId = trim((string)($body['connection_public_id'] ?? ''));
        $owner = trim((string)($body['owner'] ?? ''));
        $repo = trim((string)($body['repo'] ?? ''));
        $projectPublicId = trim((string)($body['project_public_id'] ?? ''));
        if ($connectionPublicId === '' || $owner === '' || $repo === '' || $projectPublicId === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'connection_public_id, owner, repo and project_public_id are required', 422);
        }
        $connection = $this->repo->getConnection($connectionPublicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        if ($this->repo->findLinkByRepo((int)$connection['id'], $owner, $repo)) {
            return JsonResponse::error('LINK_EXISTS', 'A link for this repository already exists', 409);
        }

        $secret = trim((string)($body['webhook_secret'] ?? ''));
        if ($secret === '') {
            $secret = bin2hex(random_bytes(24));
        }

        $link = $this->repo->createLink([
            'connection_id' => (int)$connection['id'],
            'owner' => $owner,
            'repo' => $repo,
            'project_public_id' => $projectPublicId,
            'webhook_secret_encrypted' => EncryptionService::encrypt($secret),
            'is_active' => 1,
            'created_by_user_id' => $this->actorUserId(),
        ]);

        $publicId = (string)($link['public_id'] ?? '');
        return JsonResponse::success('LINK_CREATED', 'Link created', [
            'link' => $this->sanitizeLink($link),
            // Returned once so the user can paste them into GitHub webhook settings.
            'webhook_url' => $publicId !== '' ? $this->buildWebhookUrl($publicId) : '',
            'webhook_secret' => $secret,
            'webhook_events' => ['issues', 'issue_comment', 'pull_request', 'pull_request_review'],
        ], 201);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function deleteLink(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.github-integration.manage')) {
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
     * Trigger a sync of a single link now (bounded). Useful as a manual button
     * and to verify a webhook end-to-end.
     *
     * @param array<string, mixed> $params
     */
    public function syncNow(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.github-integration.run')) {
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
            return JsonResponse::error('GITHUB_DECRYPT_FAILED', 'Failed to decrypt token', 500);
        }

        $settings = $this->repo->getSettings();
        $batch = max(1, (int)($settings['batch_size'] ?? 100));
        $sync = new GitHubSyncService($this->container, $this->repo, new GitHubClient());
        try {
            $counts = $sync->syncLink($link, $token, $this->actor(), $batch, (bool)($settings['sync_comments'] ?? true));
            $this->repo->markSynced((string)$params['public_id']);
            $this->repo->addLog((int)$link['id'], 'info', 'Manual sync completed: ' . json_encode($counts, JSON_UNESCAPED_UNICODE));
            return JsonResponse::success('SYNC_COMPLETED', 'Sync completed', ['counts' => $counts]);
        } catch (\Throwable $e) {
            error_log('[GitHubController::syncNow] ' . $e->getMessage());
            $this->repo->addLog((int)$link['id'], 'error', 'Manual sync failed. Check server logs.');
            return JsonResponse::error('SYNC_FAILED', 'Sync failed. Check server logs for details.', 500);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function listLinkLogs(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.github-integration.view') && !$this->hasPermission('module.github-integration.manage')) {
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
     * Public endpoint receiving GitHub webhooks. Verifies the HMAC
     * X-Hub-Signature-256 using the link's stored secret, then marks the link
     * dirty so the cron worker picks it up (robust on shared hosting).
     *
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

        if (!$this->verifySignature($secret, $this->rawBody())) {
            return JsonResponse::error('INVALID_SIGNATURE', 'Invalid webhook signature', 401);
        }

        $event = $this->header('X-GitHub-Event');
        if (!in_array($event, ['issues', 'issue_comment', 'pull_request', 'pull_request_review'], true)) {
            // Acknowledge but ignore unrelated events (ping, push, ...).
            return JsonResponse::success('WEBHOOK_IGNORED', 'Event ignored', ['event' => $event]);
        }

        $this->repo->markDirty($publicId);
        $this->repo->addLog((int)$link['id'], 'info', 'Webhook event received: ' . $event);

        return JsonResponse::success('WEBHOOK_ACCEPTED', 'Webhook accepted', ['event' => $event], 202);
    }

    private function verifySignature(string $secret, string $rawBody): bool
    {
        $signature = $this->header('X-Hub-Signature-256');
        if ($signature === '' || !str_starts_with($signature, 'sha256=')) {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals('sha256=' . $expected, $signature);
    }

    // ── Helpers ──

    private function normalizeBaseUrl(string $baseUrl): ?string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return null;
        }
        $scheme = strtolower((string)parse_url($baseUrl, PHP_URL_SCHEME));
        $host = (string)parse_url($baseUrl, PHP_URL_HOST);
        if ($host === '' || $host === false) {
            return null;
        }
        if ($scheme !== '' && $scheme !== 'https' && $scheme !== 'http') {
            return null;
        }
        // github.com, api.github.com and GitHub Enterprise Server hosts are all allowed.
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
