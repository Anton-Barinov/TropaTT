<?php
declare(strict_types=1);

namespace Module\Crm\SlackIntegration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\SlackIntegration\Repository\SlackRepository;
use Module\Crm\SlackIntegration\Service\EncryptionService;
use Module\Crm\SlackIntegration\Service\SlackClient;
use Module\Crm\SlackIntegration\Service\SlackNotifier;
use PDO;

final class SlackController
{
    private PDO $pdo;
    private SlackRepository $repo;
    /** @var array<int, string> */
    private array $allowedHosts;

    public function __construct(private readonly Container $container)
    {
        $this->pdo = $container->get('db.pdo');
        $this->repo = new SlackRepository($this->pdo);
        $this->allowedHosts = ['hooks.slack.com'];
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
        if ($this->hasPermission('module.slack-integration.manage')) {
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
        unset($connection['webhook_url_encrypted']);
        return $connection;
    }

    // ── Connections ──

    public function listConnections(): JsonResponse
    {
        if (!$this->hasPermission('module.slack-integration.view') && !$this->hasPermission('module.slack-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $isManager = $this->hasPermission('module.slack-integration.manage');
        $userId = $this->actorUserId();
        $connections = array_values(array_filter(
            $this->repo->listConnections(),
            fn(array $c): bool => $isManager || (int)($c['created_by_user_id'] ?? 0) === $userId
        ));
        return JsonResponse::success('CONNECTIONS_LIST', 'OK', ['connections' => $connections]);
    }

    public function createConnection(): JsonResponse
    {
        if (!$this->hasPermission('module.slack-integration.manage') || !$this->hasPermission('module.slack-integration.secret_manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $body = $this->requestBody();
        $name = trim((string)($body['name'] ?? ''));
        $webhookUrl = trim((string)($body['webhook_url'] ?? ''));

        if ($name === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'Name is required', 422);
        }
        if (!SlackClient::validateWebhookUrl($webhookUrl, $this->allowedHosts)) {
            return JsonResponse::error('VALIDATION_ERROR', 'Webhook URL must be an official Slack incoming webhook (https://hooks.slack.com/...)', 422);
        }

        $connection = $this->repo->createConnection([
            'name' => $name,
            'channel' => trim((string)($body['channel'] ?? '')) ?: null,
            'webhook_url_encrypted' => EncryptionService::encrypt($webhookUrl),
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
        if (!$this->hasPermission('module.slack-integration.manage')) {
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
        if (array_key_exists('channel', $body)) {
            $update['channel'] = trim((string)$body['channel']) ?: null;
        }
        if (array_key_exists('webhook_url', $body) && (string)$body['webhook_url'] !== '') {
            if (!$this->hasPermission('module.slack-integration.secret_manage')) {
                return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
            }
            if (!SlackClient::validateWebhookUrl((string)$body['webhook_url'], $this->allowedHosts)) {
                return JsonResponse::error('VALIDATION_ERROR', 'Webhook URL must be an official Slack incoming webhook', 422);
            }
            $update['webhook_url_encrypted'] = EncryptionService::encrypt((string)$body['webhook_url']);
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
        if (!$this->hasPermission('module.slack-integration.manage')) {
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
        if (!$this->hasPermission('module.slack-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $connection = $this->repo->getConnection((string)$params['public_id']);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $webhookUrl = EncryptionService::decrypt((string)($connection['webhook_url_encrypted'] ?? ''));
        if ($webhookUrl === null) {
            return JsonResponse::error('SLACK_DECRYPT_FAILED', 'Failed to decrypt webhook URL', 500);
        }

        $client = new SlackClient(10);
        $result = $client->send($webhookUrl, [
            'text' => '✅ Test message from TropaTT CRM',
        ]);
        $this->repo->updateConnectionLastCheck((string)$params['public_id'], $result['success'] ? 'success' : 'failed', $result['message']);

        if (!$result['success']) {
            return JsonResponse::error('SLACK_SEND_FAILED', $result['message'], 502);
        }
        return JsonResponse::success('SLACK_TEST_OK', 'Test message sent', ['response_code' => $result['response_code']]);
    }

    // ── Rules ──

    public function listRules(): JsonResponse
    {
        if (!$this->hasPermission('module.slack-integration.view') && !$this->hasPermission('module.slack-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        return JsonResponse::success('RULES_LIST', 'OK', ['rules' => $this->repo->listRules()]);
    }

    public function createRule(): JsonResponse
    {
        if (!$this->hasPermission('module.slack-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $body = $this->requestBody();
        $connectionPublicId = trim((string)($body['connection_public_id'] ?? ''));
        $eventCode = trim((string)($body['event_code'] ?? ''));
        $textTemplate = (string)($body['text_template'] ?? '');

        if ($connectionPublicId === '' || $eventCode === '' || trim($textTemplate) === '') {
            return JsonResponse::error('VALIDATION_ERROR', 'connection_public_id, event_code and text_template are required', 422);
        }
        $connection = $this->repo->getConnection($connectionPublicId);
        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }
        if (!$this->canAccessConnection($connection)) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $rule = $this->repo->createRule([
            'connection_id' => (int)$connection['id'],
            'event_code' => substr($eventCode, 0, 64),
            'text_template' => mb_substr($textTemplate, 0, 4000),
            'is_enabled' => (int)($body['is_enabled'] ?? 1),
            'created_by_user_id' => $this->actorUserId(),
        ]);

        return JsonResponse::success('RULE_CREATED', 'Rule created', ['rule' => $rule], 201);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function deleteRule(array $params): JsonResponse
    {
        if (!$this->hasPermission('module.slack-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $rule = $this->repo->getRule((string)$params['public_id']);
        if (!$rule) {
            return JsonResponse::error('NOT_FOUND', 'Rule not found', 404);
        }
        $this->repo->deleteRule((string)$params['public_id']);
        return JsonResponse::success('RULE_DELETED', 'Rule deleted');
    }

    // ── Notify (public entrypoint for workflow call_webhook) ──

    public function notify(): JsonResponse
    {
        $query = $this->query();
        $body = $this->requestBody();

        $connectionPublicId = trim((string)($query['connection_public_id'] ?? $body['connection_public_id'] ?? ''));
        $rulePublicId = trim((string)($query['rule_public_id'] ?? $body['rule_public_id'] ?? ''));
        $eventCode = trim((string)($query['event_code'] ?? $body['event'] ?? ''));
        $directText = (string)($body['text'] ?? '');

        $connection = null;
        $rule = null;

        if ($connectionPublicId !== '') {
            $connection = $this->repo->getConnection($connectionPublicId);
        } elseif ($rulePublicId !== '') {
            $rule = $this->repo->getRule($rulePublicId);
            if ($rule && (int)($rule['is_enabled'] ?? 1) === 1) {
                $connection = $this->repo->getConnectionById((int)$rule['connection_id']);
                $eventCode = $eventCode !== '' ? $eventCode : (string)$rule['event_code'];
            }
        }

        if (!$connection) {
            return JsonResponse::error('NOT_FOUND', 'Connection not found', 404);
        }

        $notifier = new SlackNotifier($this->pdo);

        if ($directText !== '') {
            $text = $directText;
        } elseif ($rule && trim((string)$rule['text_template']) !== '') {
            $text = $notifier->interpolate((string)$rule['text_template'], $body, $eventCode);
        } else {
            $text = $notifier->defaultText($body, $eventCode);
        }

        $delivery = $this->repo->enqueueDelivery([
            'connection_id' => (int)$connection['id'],
            'rule_id' => $rule ? (int)$rule['id'] : null,
            'event_code' => $eventCode !== '' ? $eventCode : null,
            'payload_json' => ['text' => mb_substr($text, 0, 4000)],
        ]);

        return JsonResponse::success('SLACK_QUEUED', 'Notification queued', ['delivery' => ['public_id' => $delivery['public_id'], 'status' => $delivery['status']]], 202);
    }

    // ── Deliveries ──

    public function listDeliveries(): JsonResponse
    {
        if (!$this->hasPermission('module.slack-integration.view') && !$this->hasPermission('module.slack-integration.manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }
        $query = $this->query();
        return JsonResponse::success('DELIVERIES_LIST', 'OK', [
            'deliveries' => $this->repo->listDeliveries((int)($query['limit'] ?? 50)),
        ]);
    }

}
