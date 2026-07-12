<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiIntentSettingService;
use Api\System\Library\Validation\Validator;

final class AiIntentController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        /** @var AiIntentSettingService $service */
        $service = $this->container->get('service.ai_intent_settings');
        $result = $service->list($this->request()->allInput());

        return $this->success('AI_INTENT_SETTINGS_LIST', $this->t('ai/messages.intent_settings_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'model', 190, $this->t('ai/messages.intent_model_too_long'))
            ->maxLen($input, 'feature_flag', 128, $this->t('ai/messages.intent_feature_flag_too_long'))
            ->maxLen($input, 'required_permission', 128, $this->t('ai/messages.intent_required_permission_too_long'))
            ->maxLen($input, 'temperature', 16, $this->t('ai/messages.intent_temperature_too_long'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        return $this->withIdempotency(function () use ($params, $input, $auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiIntentSettingService $service */
            $service = $this->container->get('service.ai_intent_settings');
            $result = $service->update(trim((string)($params['intent_code'] ?? '')), $input, $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = (string)($result['code'] ?? 'AI_INTENT_UPDATE_FAILED');
                $status = match ($code) {
                    'AI_INTENT_NOT_FOUND' => 404,
                    'AI_PROVIDER_NOT_FOUND', 'AI_INTENT_NOT_ALLOWED', 'AI_INTENT_NO_CHANGES' => 422,
                    default => 400,
                };
                return $this->error($code, $this->t('ai/messages.intent_settings_update_failed'), $status, [
                    'intent' => [$code],
                ]);
            }

            return $this->success('AI_INTENT_SETTINGS_UPDATED', $this->t('ai/messages.intent_settings_updated'), [
                'item' => $result['item'],
            ]);
        });
    }
}
