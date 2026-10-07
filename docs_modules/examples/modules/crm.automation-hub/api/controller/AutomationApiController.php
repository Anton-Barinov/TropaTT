<?php
declare(strict_types=1);

namespace Module\Crm\AutomationHub\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\AutomationHub\Service\AutomationHubService;

final class AutomationApiController extends BaseController
{
    private function service(): AutomationHubService
    {
        return new AutomationHubService($this->container->get('db.pdo'));
    }

    private function organizationId(): ?int
    {
        $auth = $this->user();
        $organizationId = (int)($auth['user']['organization_id'] ?? 0);
        return $organizationId > 0 ? $organizationId : null;
    }

    private function organizationRequired(): ?JsonResponse
    {
        if ($this->organizationId() !== null) {
            return null;
        }
        return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Active workspace is required', 409);
    }

    public function listRecipes(): JsonResponse
    {
        if (($denied = $this->organizationRequired()) !== null) {
            return $denied;
        }
        $recipes = $this->service()->listRecipes((int)$this->organizationId());
        return $this->success('AUTOMATION_RECIPES_LIST', 'OK', ['recipes' => $recipes]);
    }

    public function saveRecipe(): JsonResponse
    {
        if (($denied = $this->organizationRequired()) !== null) {
            return $denied;
        }
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

        $publicId = $this->service()->saveRecipe(
            (int)$this->organizationId(),
            $platform,
            $recipeKey,
            $title,
            $url,
            $secret,
            $mappings
        );

        return $this->success('AUTOMATION_RECIPE_SAVED', 'OK', ['public_id' => $publicId]);
    }

    public function testWebhook(): JsonResponse
    {
        if (($denied = $this->organizationRequired()) !== null) {
            return $denied;
        }
        $input = $this->request()->allInput();
        $recipePublicId = (string)($input['recipe_public_id'] ?? '');
        if ($recipePublicId === '') {
            return $this->error('VALIDATION_ERROR', 'recipe_public_id is required', 422);
        }

        try {
            $result = $this->service()->dispatch($recipePublicId, 'test.ping', [
                'ping' => 'pong',
                'timestamp' => time(),
                'sender' => 'TropaTT Automation Hub',
            ]);
        } catch (\Throwable $e) {
            return $this->error('DISPATCH_ERROR', $e->getMessage(), 400);
        }

        return $this->success('AUTOMATION_WEBHOOK_DISPATCHED', 'OK', $result);
    }
}
