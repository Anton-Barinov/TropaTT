<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\EcommerceGateway\Repository\StoreRepository;
use Module\Crm\EcommerceGateway\Service\EcommerceStoreService;
use PDO;

final class StoreAdminController
{
    private PDO $pdo;
    private EcommerceStoreService $service;

    public function __construct(private readonly Container $container)
    {
        $this->pdo = $container->get('db.pdo');
        $this->service = new EcommerceStoreService(
            new StoreRepository($this->pdo),
            $this->moduleConfig()
        );
    }

    public function listStores(): JsonResponse
    {
        if (!$this->canView()) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        return JsonResponse::success('STORES_LIST', 'OK', ['stores' => $this->service->listStores()]);
    }

    public function createStore(): JsonResponse
    {
        if (!$this->canManage() || !$this->hasPermission('module.ecommerce-gateway.secret_manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $result = $this->service->createStore(
            $this->requestBody(),
            $this->actor(),
            $this->clientIp(),
            $this->userAgent()
        );

        if (!$result['ok']) {
            return JsonResponse::error($result['code'], 'Store could not be created', $result['http_status'], $result['errors']);
        }

        return JsonResponse::success($result['code'], 'Store created', [
            'store' => $result['store'],
            'secret' => $result['secret'],
            'secret_notice' => 'Store the secret now: it is not shown again.',
        ], $result['http_status']);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function getStore(array $params): JsonResponse
    {
        if (!$this->canView()) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $store = $this->service->getStore((string)($params['public_id'] ?? ''));
        if ($store === null) {
            return JsonResponse::error('STORE_NOT_FOUND', 'Store not found', 404);
        }

        return JsonResponse::success('STORE', 'OK', ['store' => $store]);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function updateStore(array $params): JsonResponse
    {
        if (!$this->canManage()) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $result = $this->service->updateStore(
            (string)($params['public_id'] ?? ''),
            $this->requestBody(),
            $this->actor(),
            $this->clientIp(),
            $this->userAgent()
        );

        if (!$result['ok']) {
            return JsonResponse::error($result['code'], 'Store could not be updated', $result['http_status'], $result['errors']);
        }

        return JsonResponse::success($result['code'], 'Store updated', ['store' => $result['store']]);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function deleteStore(array $params): JsonResponse
    {
        if (!$this->canManage()) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $result = $this->service->deleteStore(
            (string)($params['public_id'] ?? ''),
            $this->actor(),
            $this->clientIp(),
            $this->userAgent()
        );

        if (!$result['ok']) {
            return JsonResponse::error($result['code'], 'Store could not be deleted', $result['http_status'], $result['errors']);
        }

        return JsonResponse::success($result['code'], 'Store deleted');
    }

    /**
     * @param array<string,mixed> $params
     */
    public function rotateSecret(array $params): JsonResponse
    {
        if (!$this->canManage() || !$this->hasPermission('module.ecommerce-gateway.secret_manage')) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $result = $this->service->rotateSecret(
            (string)($params['public_id'] ?? ''),
            $this->actor(),
            $this->clientIp(),
            $this->userAgent()
        );

        if (!$result['ok']) {
            return JsonResponse::error($result['code'], 'Secret could not be rotated', $result['http_status'], $result['errors']);
        }

        return JsonResponse::success($result['code'], 'Secret rotated', [
            'store' => $result['store'],
            'secret' => $result['secret'],
            'previous_secret_valid_until' => $result['secret_valid_until'],
            'secret_notice' => 'Store the new secret now: it is not shown again.',
        ]);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function getStatusMappings(array $params): JsonResponse
    {
        if (!$this->canView()) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $store = $this->service->getStore((string)($params['public_id'] ?? ''));
        if ($store === null) {
            return JsonResponse::error('STORE_NOT_FOUND', 'Store not found', 404);
        }

        $mappingService = new \Module\Crm\EcommerceGateway\Service\StatusMappingService($this->pdo);
        $scope = (string)($this->container->get('request')->get('scope', 'all'));
        $mappings = $mappingService->getStoreMappings((int)$store['id'], $scope);

        return JsonResponse::success('STATUS_MAPPINGS', 'OK', [
            'store_public_id' => $store['public_id'],
            'mappings' => $mappings,
        ]);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function saveStatusMappings(array $params): JsonResponse
    {
        if (!$this->canManage()) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $store = $this->service->getStore((string)($params['public_id'] ?? ''));
        if ($store === null) {
            return JsonResponse::error('STORE_NOT_FOUND', 'Store not found', 404);
        }

        $body = $this->requestBody();
        $mappings = is_array($body['mappings'] ?? null) ? $body['mappings'] : [];

        $mappingService = new \Module\Crm\EcommerceGateway\Service\StatusMappingService($this->pdo);
        $storeId = (int)$store['id'];

        foreach ($mappings as $map) {
            if (!empty($map['crm_status_code']) && !empty($map['external_status'])) {
                $scope = (string)($map['entity_scope'] ?? 'order');
                $mappingService->setMapping(
                    $storeId,
                    (string)$map['crm_status_code'],
                    (string)$map['external_status'],
                    $scope
                );
            }
        }

        return JsonResponse::success('STATUS_MAPPINGS_SAVED', 'Status mappings saved', [
            'store_public_id' => $store['public_id'],
            'mappings' => $mappingService->getStoreMappings($storeId, 'all'),
        ]);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function getSyncLog(array $params): JsonResponse
    {
        if (!$this->canView()) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $store = $this->service->getStore((string)($params['public_id'] ?? ''));
        if ($store === null) {
            return JsonResponse::error('STORE_NOT_FOUND', 'Store not found', 404);
        }

        $storeId = (int)$store['id'];
        $request = $this->container->get('request');
        $limit = max(1, min(100, (int)($request->get('limit', 50))));
        $direction = (string)$request->get('direction', 'all');

        $items = [];

        // Fetch from ecommerce_order_sync_log
        if ($direction === 'all' || $direction === 'inbound') {
            $stmt = $this->pdo->prepare(
                'SELECT id, public_id, store_id, external_order_id, crm_task_id, intake_item_id,
                        direction, status, http_code, request_payload, response_payload,
                        error_message, ip_address, created_at
                 FROM ecommerce_order_sync_log
                 WHERE store_id = :store_id
                 ORDER BY id DESC LIMIT :limit'
            );
            $stmt->bindValue(':store_id', $storeId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $inboundItems = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($inboundItems as $item) {
                $item['log_type'] = 'inbound_order';
                $items[] = $item;
            }
        }

        // Fetch from ecommerce_outbox_events
        if ($direction === 'all' || $direction === 'outbound') {
            $stmt = $this->pdo->prepare(
                'SELECT id, public_id, store_id, event_type, external_order_id,
                        crm_task_id, crm_task_public_id, old_status, new_status, external_status,
                        payload_json as request_payload, status, attempts, max_attempts,
                        next_attempt_at, last_attempt_at, delivered_at, last_http_code as http_code,
                        last_error as error_message, last_response_body as response_payload,
                        sync_initiator, created_at
                 FROM ecommerce_outbox_events
                 WHERE store_id = :store_id
                 ORDER BY id DESC LIMIT :limit'
            );
            $stmt->bindValue(':store_id', $storeId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $outboundItems = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($outboundItems as $item) {
                $item['direction'] = 'outbound';
                $item['log_type'] = 'outbox_webhook';
                $items[] = $item;
            }
        }

        // Sort combined items by created_at desc
        usort($items, function ($a, $b) {
            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        });
        $items = array_slice($items, 0, $limit);

        return JsonResponse::success('SYNC_LOG', 'OK', [
            'store_public_id' => $store['public_id'],
            'items' => $items,
        ]);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function retrySyncPacket(array $params): JsonResponse
    {
        if (!$this->canManage()) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $store = $this->service->getStore((string)($params['public_id'] ?? ''));
        if ($store === null) {
            return JsonResponse::error('STORE_NOT_FOUND', 'Store not found', 404);
        }

        $logId = (string)($params['log_id'] ?? '');
        $outboxRepo = new \Module\Crm\EcommerceGateway\Repository\OutboxRepository($this->pdo);

        // Find outbox event by public_id or integer ID
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ecommerce_outbox_events
             WHERE store_id = :store_id AND (public_id = :log_id OR id = :int_id) LIMIT 1'
        );
        $stmt->execute([
            'store_id' => (int)$store['id'],
            'log_id' => $logId,
            'int_id' => is_numeric($logId) ? (int)$logId : 0,
        ]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            return JsonResponse::error('LOG_NOT_FOUND', 'Outbox event not found for retry', 404);
        }

        // Reset status to pending and run delivery
        $this->pdo->prepare('UPDATE ecommerce_outbox_events SET status = "pending", next_attempt_at = :now WHERE id = :id')
            ->execute(['now' => gmdate('Y-m-d H:i:s'), 'id' => $event['id']]);

        $job = new \Module\Crm\EcommerceGateway\Job\EcommerceWebhookJob(
            $outboxRepo,
            $this->moduleConfig()
        );
        $result = $job->processEvent((int)$event['id']);

        return JsonResponse::success('RETRY_COMPLETED', 'Retry processed', [
            'result' => $result,
        ]);
    }

    /**
     * @param array<string,mixed> $params
     */
    public function pingTest(array $params): JsonResponse
    {
        if (!$this->canManage()) {
            return JsonResponse::error('FORBIDDEN', 'Insufficient permissions', 403);
        }

        $store = $this->service->getStore((string)($params['public_id'] ?? ''));
        if ($store === null) {
            return JsonResponse::error('STORE_NOT_FOUND', 'Store not found', 404);
        }

        $url = trim((string)($store['webhook_url'] ?? $store['store_url'] ?? ''));
        if ($url === '') {
            return JsonResponse::error('URL_NOT_CONFIGURED', 'Neither webhook_url nor store_url configured', 422);
        }

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $durationMs = (int)round((microtime(true) - $start) * 1000);

        return JsonResponse::success('PING_TEST_RESULT', 'Ping test completed', [
            'url' => $url,
            'http_code' => $httpCode,
            'duration_ms' => $durationMs,
            'success' => $httpCode >= 200 && $httpCode < 400,
            'error' => $error !== '' ? $error : null,
        ]);
    }

    // ── Auth / actor helpers ───────────────────────────────────────────

    private function canView(): bool
    {
        return $this->hasPermission('module.ecommerce-gateway.view')
            || $this->hasPermission('module.ecommerce-gateway.manage');
    }

    private function canManage(): bool
    {
        return $this->hasPermission('module.ecommerce-gateway.manage');
    }

    private function hasPermission(string $code): bool
    {
        $user = $this->actor();
        if (!empty($user['is_root'])) {
            return true;
        }
        $permissions = array_map('strval', (array)($user['permission_codes'] ?? []));

        return in_array('*', $permissions, true) || in_array($code, $permissions, true);
    }

    /**
     * @return array<string,mixed>
     */
    private function actor(): array
    {
        $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : null;

        return is_array($auth) && is_array($auth['user'] ?? null) ? $auth['user'] : [];
    }

    // ── Request / output helpers ───────────────────────────────────────

    /**
     * @return array<string,mixed>
     */
    private function requestBody(): array
    {
        $raw = (string)($this->container->get('request')->rawBody ?? '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function clientIp(): string
    {
        return (string)$this->container->get('request')->clientIp();
    }

    private function userAgent(): string
    {
        return substr((string)$this->container->get('request')->header('User-Agent', ''), 0, 512);
    }

    /**
     * @return array<string,mixed>
     */
    private function moduleConfig(): array
    {
        if (!$this->container->has('module.config')) {
            return [];
        }
        $config = $this->container->get('module.config')->getAll('crm.ecommerce-gateway');

        return is_array($config) ? $config : [];
    }
}
