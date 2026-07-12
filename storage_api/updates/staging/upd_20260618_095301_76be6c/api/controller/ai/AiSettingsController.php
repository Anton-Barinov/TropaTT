<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiSettingsService;

final class AiSettingsController extends BaseController
{
    public function get(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (!$this->canManageSettings($auth['user'])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['ai.admin'],
            ]);
        }

        /** @var AiSettingsService $service */
        $service = $this->container->get('service.ai_settings');
        $data = $service->getSettings();

        return $this->success('AI_SETTINGS_GET', $this->t('ai/messages.provider_get'), $data);
    }

    public function patch(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (!$this->canManageSettings($auth['user'])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['ai.admin'],
            ]);
        }

        return $this->withIdempotency(function () {
            /** @var AiSettingsService $service */
            $service = $this->container->get('service.ai_settings');
            $result = $service->updateSettings($this->request()->allInput());
            if (!(bool)($result['ok'] ?? false)) {
                $code = (string)($result['code'] ?? 'AI_SETTINGS_UPDATE_FAILED');
                $status = $code === 'AI_SETTINGS_NO_CHANGES' ? 422 : 400;
                return $this->error($code, $this->t('ai/messages.provider_update_failed'), $status, [
                    'settings' => [$code],
                ]);
            }

            return $this->success('AI_SETTINGS_UPDATED', $this->t('ai/messages.provider_updated'), (array)($result['data'] ?? []));
        });
    }

    /** @param array<string,mixed> $actor */
    private function canManageSettings(array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $roles = is_array($actor['roles'] ?? null) ? (array)$actor['roles'] : [];
        if (in_array('admin', $roles, true)) {
            return true;
        }

        $permissionCodes = is_array($actor['permission_codes'] ?? null) ? (array)$actor['permission_codes'] : [];
        return in_array('ai.admin', $permissionCodes, true);
    }
}
