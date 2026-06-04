<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiPreferenceService;

final class AiPreferenceController extends BaseController
{
    public function get(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var AiPreferenceService $service */
        $service = $this->container->get('service.ai_preference');

        return $this->success('AI_PREFERENCES_GET', $this->t('ai/messages.preferences_get'), [
            'preferences' => $service->getPreferences($auth['user']),
        ]);
    }

    public function patch(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        return $this->withIdempotency(function () use ($auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiPreferenceService $service */
            $service = $this->container->get('service.ai_preference');
            $result = $service->updatePreferences($auth['user'], $this->request()->allInput());
            if (!(bool)($result['ok'] ?? false)) {
                $code = (string)($result['code'] ?? 'AI_PREFERENCES_UPDATE_FAILED');
                $status = in_array($code, ['AI_PREFERENCES_NO_CHANGES', 'AI_PREFERENCES_OPT_OUT_FORBIDDEN'], true) ? 422 : 400;
                return $this->error($code, $this->t('ai/messages.preferences_update_failed'), $status, [
                    'preferences' => [$code],
                ]);
            }

            return $this->success('AI_PREFERENCES_UPDATED', $this->t('ai/messages.preferences_updated'), [
                'preferences' => (array)($result['preferences'] ?? []),
            ]);
        });
    }
}
