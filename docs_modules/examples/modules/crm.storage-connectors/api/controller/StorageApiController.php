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
        $actor = (array)($this->user()['user'] ?? []);
        $scope = hash('sha256', $orgId . ':' . (string)($actor['public_id'] ?? ''));
        return $this->success('STORAGE_CONFIG_OK', 'Настройки загружены.', ['config' => $service->getConfig($orgId), 'cursor_scope' => $scope, 'can_migrate' => $this->hasAllPermissions(['settings.edit', 'files.view'])]);
    }

    public function saveConfig(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $providerType = (string)($input['provider_type'] ?? '');
        $endpointUrl = (string)($input['endpoint_url'] ?? '');
        $bucketOrPath = (string)($input['bucket_or_path'] ?? '');
        $credentials = (array)($input['credentials'] ?? []);
        $region = (string)($input['region'] ?? 'us-east-1');
        $policy = (string)($input['sync_policy'] ?? 'manual');

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
        try {
            $service = new StorageManagerService($db);
            $adapter = $service->getAdapter($orgId);
            if ($adapter === null) return $this->error('STORAGE_NOT_CONFIGURED', 'Для рабочего пространства не настроено хранилище.', 404);
            $result = $adapter->testConnection();
        } catch (\Throwable) {
            return $this->error('STORAGE_CONNECTION_FAILED', 'Не удалось проверить подключение к внешнему хранилищу.', 400);
        }
        return $result['ok']
            ? $this->success('STORAGE_CONNECTION_OK', $result['message'], $result)
            : $this->error('STORAGE_CONNECTION_FAILED', $result['message'], 400, $result);
    }

    public function migrateBatch(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->hasAllPermissions(['settings.edit', 'files.view'])) return $this->error('FORBIDDEN', 'Для переноса нужны права настройки хранилища и чтения файлов.', 403);
        $orgId = (int)($this->user()['user']['organization_id'] ?? 0);
        if ($orgId <= 0) {
            return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Не выбрано рабочее пространство.', 409);
        }
        $db = $this->container->get('db.pdo');
        $manager = new StorageManagerService($db);
        $auth = $this->user();
        $actor = is_array($auth['user'] ?? null) ? $auth['user'] : [];
        $fileService = $this->container->get('service.file');
        $storageRoot = (string)$this->container->get('config')->get('default.storage.uploads', dirname(__DIR__, 4) . '/storage_api/uploads');
        $migrator = new FileMigrationService($db, $manager, $storageRoot, $fileService, $actor);

        $input = $this->request()->allInput();
        $rawCursor = $input['after_file_id'] ?? 0;
        if (!(is_int($rawCursor) || (is_string($rawCursor) && ctype_digit($rawCursor)))) return $this->error('INVALID_CURSOR', 'Позиция переноса недействительна.', 422);
        $afterFileId = (int)$rawCursor;
        if ($afterFileId < 0) return $this->error('INVALID_CURSOR', 'Позиция переноса недействительна.', 422);
        $result = $migrator->migrateBatch($orgId, 15, null, $afterFileId);
        $result['cursor_scope'] = hash('sha256', $orgId . ':' . (string)($actor['public_id'] ?? ''));
        return $this->success('STORAGE_MIGRATION_BATCH', 'Обработка партии завершена.', $result);
    }

    public function migrationFailures(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->hasAllPermissions(['settings.view'])) return $this->error('FORBIDDEN', 'Недостаточно прав для просмотра ошибок переноса.', 403);
        $orgId = (int)($this->user()['user']['organization_id'] ?? 0);
        if ($orgId <= 0) return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Не выбрано рабочее пространство.', 409);
        $migrator = $this->fileMigrator($orgId);
        $visibleFailureCount = $migrator->countVisibleMigrationFailures($orgId);
        return $this->success('STORAGE_MIGRATION_FAILURES', 'Ошибки переноса загружены.', [
            'items' => $migrator->listMigrationFailures($orgId),
            'failed_remaining_count' => $visibleFailureCount['count'],
            'visible_failure_count_complete' => $visibleFailureCount['complete'],
        ]);
    }

    public function retryMigrationFailure(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->hasAllPermissions(['settings.edit', 'files.view'])) return $this->error('FORBIDDEN', 'Для повтора нужны права настройки хранилища и чтения файлов.', 403);
        $orgId = (int)($this->user()['user']['organization_id'] ?? 0);
        if ($orgId <= 0) return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Не выбрано рабочее пространство.', 409);
        $filePublicId = trim((string)($this->request()->allInput()['file_public_id'] ?? ''));
        if ($filePublicId === '' || strlen($filePublicId) > 64) return $this->error('VALIDATION_ERROR', 'Проверьте идентификатор файла.', 422);
        $result = $this->fileMigrator($orgId)->retryMigrationFailure($orgId, $filePublicId);
        return $this->success('STORAGE_MIGRATION_RETRY', 'Повторная обработка файла завершена.', $result);
    }

    /** Route metadata is any-of; these mutation flows deliberately require every permission. */
    private function hasAllPermissions(array $permissions): bool
    {
        $auth = $this->user();
        $actor = is_array($auth['user'] ?? null) ? $auth['user'] : [];
        if ($actor === []) return false;
        if (!empty($actor['is_root'])) return true;
        return $this->container->has('service.authz')
            && $this->container->get('service.authz')->hasPermissions($actor, $permissions);
    }

    private function fileMigrator(int $organizationId): FileMigrationService
    {
        $db = $this->container->get('db.pdo');
        $manager = new StorageManagerService($db);
        $auth = $this->user();
        $actor = is_array($auth['user'] ?? null) ? $auth['user'] : [];
        $fileService = $this->container->get('service.file');
        $storageRoot = (string)$this->container->get('config')->get('default.storage.uploads', dirname(__DIR__, 4) . '/storage_api/uploads');
        return new FileMigrationService($db, $manager, $storageRoot, $fileService, $actor);
    }
}
