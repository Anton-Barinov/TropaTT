<?php
declare(strict_types=1);

namespace Api\Controller\Security;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\SessionService;
use Api\System\Library\Validation\Validator;

final class SessionController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SessionService $service */
        $service = $this->container->get('service.session');
        $result = $service->list($auth['user'], $this->request()->allInput());

        return $this->success('SESSION_LIST', $this->t('security/messages.session_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function revoke(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SessionService $service */
        $service = $this->container->get('service.session');
        $result = $service->revoke($auth['user'], (string)$params['public_id']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = (string)($result['code'] ?? '') === 'FORBIDDEN' ? 403 : 404;
            $message = $status === 403
                ? $this->t('common/messages.forbidden')
                : $this->t('auth/messages.session_not_found');

            return $this->error((string)$result['code'], $message, $status, [
                'session' => [$message],
            ]);
        }

        return $this->success('SESSION_REVOKED', $this->t('security/messages.session_revoked'));
    }

    public function revokeOthers(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var SessionService $service */
        $service = $this->container->get('service.session');
        $count = $service->revokeOthers($auth['user'], (string)($auth['session_public_id'] ?? ''));

        return $this->success('SESSION_REVOKE_OTHERS', $this->t('security/messages.session_revoke_others'), [
            'revoked_count' => $count,
        ]);
    }

    public function revokeDevice(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validator = new Validator();
        $validator->require($input, 'device_fingerprint', $this->t('security/messages.device_fingerprint_required'));
        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        /** @var SessionService $service */
        $service = $this->container->get('service.session');
        $count = $service->revokeDevice(
            $auth['user'],
            trim((string)$input['device_fingerprint']),
            (string)($auth['session_public_id'] ?? '')
        );

        return $this->success('SESSION_REVOKE_DEVICE', $this->t('security/messages.session_revoke_device'), [
            'revoked_count' => $count,
            'device_fingerprint' => trim((string)$input['device_fingerprint']),
        ]);
    }
}
