<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Api\Controller;

use Api\Controller\BaseController;
use Module\Crm\StarterKits\Service\StarterKitService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class StarterKitsApiController extends BaseController
{
    public function listKits(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.view');
        $db = DatabaseConnectionPool::getConnection();
        $service = new StarterKitService($db);
        $locale = (string)($this->request()->get['locale'] ?? $this->currentLocale());
        return $this->success(['kits' => $service->listKits($locale)]);
    }

    public function preview(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.view');
        $kitId = (string)($this->request()->get['kit_id'] ?? '');
        if ($kitId === '') {
            return $this->error('VALIDATION_ERROR', 'kit_id is required', 422);
        }

        $orgId = (int)$this->currentOrganizationId();
        $locale = (string)($this->request()->get['locale'] ?? $this->currentLocale());

        $db = DatabaseConnectionPool::getConnection();
        $service = new StarterKitService($db);
        try {
            $preview = $service->preview($kitId, $orgId, $locale);
            return $this->success(['preview' => $preview]);
        } catch (\Throwable $e) {
            return $this->error('NOT_FOUND', $e->getMessage(), 404);
        }
    }

    public function apply(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.edit');
        $input = $this->request()->allInput();
        $kitId = (string)($input['kit_id'] ?? '');
        if ($kitId === '') {
            return $this->error('VALIDATION_ERROR', 'kit_id is required', 422);
        }

        $components = isset($input['selected_components']) ? (array)$input['selected_components'] : null;
        $orgId = (int)$this->currentOrganizationId();
        $userId = (int)$this->currentUserId();
        $locale = (string)($input['locale'] ?? $this->currentLocale());

        $db = DatabaseConnectionPool::getConnection();
        $service = new StarterKitService($db);
        try {
            $result = $service->apply($kitId, $orgId, $userId, $components, $locale);
            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error('APPLY_ERROR', $e->getMessage(), 400);
        }
    }
}
