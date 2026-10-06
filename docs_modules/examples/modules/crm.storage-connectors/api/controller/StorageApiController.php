<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Api\Controller;

use Api\Controller\BaseController;
use Module\Crm\StorageConnectors\Service\Migration\FileMigrationService;
use Module\Crm\StorageConnectors\Service\StorageManagerService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class StorageApiController extends BaseController
{
    public function preflight(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.view');
        $db = DatabaseConnectionPool::getConnection();
        $service = new StorageManagerService($db);
        return $this->success($service->preflight());
    }

    public function getConfig(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.view');
        $orgId = (int)$this->currentOrganizationId();
        $db = DatabaseConnectionPool::getConnection();
        $service = new StorageManagerService($db);
        return $this->success(['config' => $service->getConfig($orgId)]);
    }

    public function saveConfig(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.edit');
        $input = $this->request()->allInput();
        $providerType = (string)($input['provider_type'] ?? '');
        $endpointUrl = (string)($input['endpoint_url'] ?? '');
        $bucketOrPath = (string)($input['bucket_or_path'] ?? '');
        $credentials = (array)($input['credentials'] ?? []);
        $region = (string)($input['region'] ?? 'us-east-1');
        $policy = (string)($input['sync_policy'] ?? 'new_files');

        if (!in_array($providerType, ['s3', 'nextcloud_webdav'], true) || $endpointUrl === '' || $bucketOrPath === '') {
            return $this->error('VALIDATION_ERROR', 'Invalid storage parameters provided.', 422);
        }

        $orgId = (int)$this->currentOrganizationId();
        $db = DatabaseConnectionPool::getConnection();
        $service = new StorageManagerService($db);
        $pubId = $service->saveConfig($orgId, $providerType, $endpointUrl, $bucketOrPath, $credentials, $region, $policy);

        return $this->success(['public_id' => $pubId]);
    }

    public function testConnection(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.edit');
        $orgId = (int)$this->currentOrganizationId();
        $db = DatabaseConnectionPool::getConnection();
        $service = new StorageManagerService($db);
        $adapter = $service->getAdapter($orgId);

        if ($adapter === null) {
            return $this->error('NOT_CONFIGURED', 'No active storage configuration found for workspace.', 404);
        }

        $result = $adapter->testConnection();
        return $result['ok'] ? $this->success($result) : $this->error('CONNECTION_FAILED', $result['message'], 400, $result);
    }

    public function migrateBatch(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.edit');
        $orgId = (int)$this->currentOrganizationId();
        $db = DatabaseConnectionPool::getConnection();
        $manager = new StorageManagerService($db);
        $migrator = new FileMigrationService($db, $manager);

        $result = $migrator->migrateBatch($orgId, 15);
        return $this->success($result);
    }
}
