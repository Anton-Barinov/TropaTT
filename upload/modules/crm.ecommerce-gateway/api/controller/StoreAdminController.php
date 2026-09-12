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
