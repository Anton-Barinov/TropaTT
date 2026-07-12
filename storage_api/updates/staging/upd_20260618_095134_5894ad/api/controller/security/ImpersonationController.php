<?php
declare(strict_types=1);

namespace Api\Controller\Security;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ImpersonationService;
use Api\System\Library\Validation\Validator;

final class ImpersonationController extends BaseController
{
    public function start(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validator = new Validator();
        $validator->require($input, 'target_user_public_id', $this->t('common/messages.field_required'))
            ->maxLen($input, 'target_user_public_id', 64, $this->t('security/messages.max_64'))
            ->maxLen($input, 'reason', 1000, $this->t('security/messages.max_1000'));
        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        /** @var ImpersonationService $service */
        $service = $this->container->get('service.impersonation');
        $result = $service->start($auth['user'], $input, $this->request()->ip(), $this->request()->userAgent());
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'IMPERSONATION_START_FAILED');
            $status = match ($code) {
                'TARGET_USER_REQUIRED' => 422,
                'TARGET_USER_NOT_FOUND', 'ACTOR_NOT_FOUND' => 404,
                'IMPERSONATION_SELF_FORBIDDEN', 'FORBIDDEN_ROOT_PROTECTED', 'FORBIDDEN_HIERARCHY', 'FORBIDDEN' => 403,
                'IMPERSONATION_ALREADY_ACTIVE' => 409,
                default => 400,
            };

            return $this->error($code, $this->t('security/messages.impersonation_start_failed'), $status, [
                'impersonation' => [$code],
            ]);
        }

        return $this->success('IMPERSONATION_STARTED', $this->t('security/messages.impersonation_started'), [
            'audit' => $result['audit'],
            'impersonation_access_token' => $result['impersonation_access_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'session_public_id' => $result['session_public_id'],
            'target_user' => $result['target_user'],
        ]);
    }

    public function status(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var ImpersonationService $service */
        $service = $this->container->get('service.impersonation');
        $result = $service->status($auth['user'], (string)($auth['session_public_id'] ?? ''));

        return $this->success('IMPERSONATION_STATUS', $this->t('security/messages.impersonation_status'), [
            'current' => $result['current'],
            'active_started_by_me' => $result['active_started_by_me'],
        ]);
    }

    public function stop(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        if (isset($input['audit_public_id']) && strlen((string)$input['audit_public_id']) > 64) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'audit_public_id' => [$this->t('security/messages.max_64')],
            ]);
        }

        /** @var ImpersonationService $service */
        $service = $this->container->get('service.impersonation');
        $result = $service->stop(
            $auth['user'],
            (string)($auth['session_public_id'] ?? ''),
            isset($input['audit_public_id']) ? (string)$input['audit_public_id'] : null,
            $this->request()->ip(),
            $this->request()->userAgent()
        );
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'IMPERSONATION_STOP_FAILED');
            $status = match ($code) {
                'IMPERSONATION_NOT_FOUND' => 404,
                'FORBIDDEN' => 403,
                'IMPERSONATION_NOT_ACTIVE', 'IMPERSONATION_ALREADY_STOPPED' => 409,
                default => 400,
            };

            return $this->error($code, $this->t('security/messages.impersonation_stop_failed'), $status, [
                'impersonation' => [$code],
            ]);
        }

        return $this->success('IMPERSONATION_STOPPED', $this->t('security/messages.impersonation_stopped'), [
            'audit' => $result['audit'],
            'revoked_sessions' => (int)($result['revoked_sessions'] ?? 0),
        ]);
    }
}
