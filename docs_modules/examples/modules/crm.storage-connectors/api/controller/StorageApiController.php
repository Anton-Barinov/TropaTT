<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Api\Controller;

use Api\Controller\Common\BaseController;
use Module\Crm\StorageConnectors\Service\Migration\FileMigrationService;
use Module\Crm\StorageConnectors\Service\StorageManagerService;

final class StorageApiController extends BaseController
{
    public function preflight(): \Api\System\Library\Http\JsonResponse
    {
        $db = $this->container->get('db.pdo');
        $service = new StorageManagerService($db);
        return $this->success('STORAGE_PREFLIGHT_OK', 'Проверка окружения завершена.', $service->preflight());
    }

    public function getConfig(): \Api\System\Library\Http\JsonResponse
    {
        $orgId = (int)($this->user()['user']['organization_id'] ?? 0);
        if ($orgId <= 0) {
            return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Не выбрано рабочее пространство.', 409);
        }
        $db = $this->container->get('db.pdo');
        $service = new StorageManagerService($db);
        return $this->success('STORAGE_CONFIG_OK', 'Настройки загружены.', ['config' => $service->getConfig($orgId)]);
    }

    public function saveConfig(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $providerType = (string)($input['provider_type'] ?? '');
        $endpointUrl = (string)($input['endpoint_url'] ?? '');
        $bucketOrPath = (string)($input['bucket_or_path'] ?? '');
        $credentials = (array)($input['credentials'] ?? []);
        $region = (string)($input['region'] ?? 'us-east-1');
        $policy = (string)($input['sync_policy'] ?? 'new_files');

        if (!in_array($providerType, ['s3', 'nextcloud_webdav'], true) || $endpointUrl === '' || $bucketOrPath === '') {
            return $this->error('VALIDATION_ERROR', 'Проверьте параметры подключения.', 422);
        }

        $orgId = (int)($this->user()['user']['organization_id'] ?? 0);
        if ($orgId <= 0) {
            return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Не выбрано рабочее пространство.', 409);
        }
        $db = $this->container->get('db.pdo');
        $service = new StorageManagerService($db);
        try {
            $pubId = $service->saveConfig($orgId, $providerType, $endpointUrl, $bucketOrPath, $credentials, $region, $policy);
        } catch (\RuntimeException) {
            return $this->error('STORAGE_CONFIG_REJECTED', 'Параметры подключения отклонены. Проверьте HTTPS-адрес, провайдера и поля авторизации.', 422);
        }

        return $this->success('STORAGE_CONFIG_SAVED', 'Настройки зашифрованы и сохранены.', ['public_id' => $pubId]);
    }

    public function testConnection(): \Api\System\Library\Http\JsonResponse
    {
        $orgId = (int)($this->user()['user']['organization_id'] ?? 0);
        if ($orgId <= 0) {
            return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Не выбрано рабочее пространство.', 409);
        }
        $db = $this->container->get('db.pdo');
        $service = new StorageManagerService($db);
        $adapter = $service->getAdapter($orgId);

        if ($adapter === null) {
            return $this->error('STORAGE_NOT_CONFIGURED', 'Для рабочего пространства не настроено хранилище.', 404);
        }

        try {
            $result = $adapter->testConnection();
        } catch (\Throwable $e) {
            return $this->error('STORAGE_CONNECTION_FAILED', 'Не удалось проверить подключение к внешнему хранилищу.', 400);
        }
        return $result['ok']
            ? $this->success('STORAGE_CONNECTION_OK', $result['message'], $result)
            : $this->error('STORAGE_CONNECTION_FAILED', $result['message'], 400, $result);
    }

    public function migrateBatch(): \Api\System\Library\Http\JsonResponse
    {
        $orgId = (int)($this->user()['user']['organization_id'] ?? 0);
        if ($orgId <= 0) {
            return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Не выбрано рабочее пространство.', 409);
        }
        $db = $this->container->get('db.pdo');
        $manager = new StorageManagerService($db);
        $migrator = new FileMigrationService($db, $manager);

        $result = $migrator->migrateBatch($orgId, 15);
        return $this->success('STORAGE_MIGRATION_BATCH', 'Обработка партии завершена.', $result);
    }
}
