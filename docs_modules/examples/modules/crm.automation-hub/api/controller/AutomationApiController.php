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
        return new AutomationHubService($this->container->get('db.pdo'), null, $this->container->get('service.webhook'));
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
        $events = is_array($input['events'] ?? null) ? $input['events'] : [];
        $auth = $this->user();

        if ($recipeKey === '' || $title === '' || $url === '' || $events === []) {
            return $this->error('VALIDATION_ERROR', 'recipe_key, title, webhook_endpoint_url, and at least one event are required', 422);
        }

        try {
            $publicId = $this->service()->saveRecipe(
                (int)$this->organizationId(),
                $platform,
                $recipeKey,
                $title,
                $url,
                $secret,
                $mappings,
                $events,
                (array)($auth['user'] ?? [])
            );
        } catch (\Throwable $e) {
            return $this->error('AUTOMATION_CONFIG_SAVE_FAILED', 'Could not save the connection. Verify the HTTPS endpoint, signing secret, and application encryption key.', 422);
        }

        return $this->success('AUTOMATION_RECIPE_SAVED', 'OK', ['public_id' => $publicId]);
    }

    public function setRecipeActive(): JsonResponse
    {
        if (($denied = $this->organizationRequired()) !== null) {
            return $denied;
        }
        $input = $this->request()->allInput();
        $recipePublicId = is_string($input['recipe_public_id'] ?? null) ? trim($input['recipe_public_id']) : '';
        $active = $input['active'] ?? null;
        if ($recipePublicId === '' || !is_bool($active)) {
            return $this->error('VALIDATION_ERROR', 'recipe_public_id and boolean active are required', 422);
        }
        try {
            $this->service()->setRecipeActive((int)$this->organizationId(), $recipePublicId, $active, (array)(($this->user()['user'] ?? [])));
            return $this->success('AUTOMATION_RECIPE_STATE_SAVED', 'Connection state updated.', [
                'public_id' => $recipePublicId,
                'is_active' => $active,
            ]);
        } catch (\RuntimeException) {
            return $this->error('AUTOMATION_RECIPE_STATE_FAILED', 'Connection was not found in the active workspace.', 404);
        }
    }

    public function testWebhook(): JsonResponse
    {
        if (($denied = $this->organizationRequired()) !== null) {
            return $denied;
        }
        $input = $this->request()->allInput();
        $recipePublicId = (string)($input['recipe_public_id'] ?? '');
        $correlationId = is_string($input['correlation_id'] ?? null) ? trim($input['correlation_id']) : null;
        if ($recipePublicId === '') {
            return $this->error('VALIDATION_ERROR', 'recipe_public_id is required', 422);
        }

        try {
            $result = $this->service()->dispatch((int)$this->organizationId(), $recipePublicId, 'test.ping', [
                'ping' => 'pong',
                'timestamp' => time(),
                'sender' => 'TropaTT Automation Hub',
            ], $correlationId);
        } catch (\Throwable $e) {
            return $this->error('DISPATCH_ERROR', 'The test webhook could not be completed. Verify the active workspace, endpoint, and signing key, then retry.', 400);
        }

        return $this->success('AUTOMATION_WEBHOOK_DISPATCHED', 'OK', $result);
    }
}
