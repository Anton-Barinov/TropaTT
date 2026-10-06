<?php
declare(strict_types=1);

namespace Module\Crm\AutomationHub\Api\Controller;

use Api\Controller\BaseController;
use Module\Crm\AutomationHub\Service\AutomationHubService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class AutomationApiController extends BaseController
{
    public function listRecipes(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.view');
        $orgId = (int)$this->currentOrganizationId();
        $db = DatabaseConnectionPool::getConnection();
        $service = new AutomationHubService($db);
        return $this->success(['recipes' => $service->listRecipes($orgId)]);
    }

    public function saveRecipe(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.edit');
        $input = $this->request()->allInput();
        $platform = (string)($input['platform'] ?? 'n8n');
        $recipeKey = (string)($input['recipe_key'] ?? '');
        $title = (string)($input['title'] ?? '');
        $url = (string)($input['webhook_endpoint_url'] ?? '');
        $secret = (string)($input['secret_token'] ?? '');
        $mappings = (array)($input['field_mappings'] ?? []);

        if ($recipeKey === '' || $title === '' || $url === '') {
            return $this->error('VALIDATION_ERROR', 'recipe_key, title, and webhook_endpoint_url are required', 422);
        }

        $orgId = (int)$this->currentOrganizationId();
        $db = DatabaseConnectionPool::getConnection();
        $service = new AutomationHubService($db);
        $pubId = $service->saveRecipe($orgId, $platform, $recipeKey, $title, $url, $secret, $mappings);

        return $this->success(['public_id' => $pubId]);
    }

    public function testWebhook(): \Api\System\Library\Http\JsonResponse
    {
        $this->requirePermission('settings.edit');
        $input = $this->request()->allInput();
        $recipePubId = (string)($input['recipe_public_id'] ?? '');
        if ($recipePubId === '') {
            return $this->error('VALIDATION_ERROR', 'recipe_public_id is required', 422);
        }

        $db = DatabaseConnectionPool::getConnection();
        $service = new AutomationHubService($db);
        try {
            $res = $service->dispatch($recipePubId, 'test.ping', [
                'ping' => 'pong',
                'timestamp' => time(),
                'sender' => 'TropaTT Automation Hub',
            ]);
            return $this->success($res);
        } catch (\Throwable $e) {
            return $this->error('DISPATCH_ERROR', $e->getMessage(), 400);
        }
    }
}
