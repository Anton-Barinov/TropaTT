<?php
declare(strict_types=1);

namespace Api\Controller\Security;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\TwoFactorService;
use Api\System\Library\Validation\Validator;

final class TwoFactorController extends BaseController
{
    public function status(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TwoFactorService $service */
        $service = $this->container->get('service.two_factor');
        $result = $service->status($auth['user']);

        return $this->success('TWO_FACTOR_STATUS', $this->t('security/messages.two_factor_status'), [
            'enabled' => (bool)$result['enabled'],
            'two_factor' => $result['two_factor'],
        ]);
    }

    public function enable(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validator = new Validator();
        $validator->require($input, 'current_password', $this->t('common/messages.field_required'));
        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        /** @var TwoFactorService $service */
        $service = $this->container->get('service.two_factor');
        $result = $service->enable($auth['user'], (string)$input['current_password']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'TWO_FACTOR_ENABLE_FAILED');
            $status = match ($code) {
                'INVALID_CURRENT_PASSWORD' => 422,
                'USER_NOT_FOUND' => 404,
                'TWO_FACTOR_ALREADY_ENABLED' => 409,
                default => 400,
            };

            return $this->error($code, $this->t('security/messages.two_factor_enable_failed'), $status, [
                'two_factor' => [$code],
            ]);
        }

        return $this->success('TWO_FACTOR_ENABLED', $this->t('security/messages.two_factor_enabled'), [
            'two_factor' => $result['two_factor'],
            'setup_code' => $result['setup_secret'],
            'recovery_codes' => $result['backup_codes'],
        ]);
    }

    public function disable(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validator = new Validator();
        $validator->require($input, 'current_password', $this->t('common/messages.field_required'));
        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        /** @var TwoFactorService $service */
        $service = $this->container->get('service.two_factor');
        $result = $service->disable($auth['user'], (string)$input['current_password']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'TWO_FACTOR_DISABLE_FAILED');
            $status = match ($code) {
                'INVALID_CURRENT_PASSWORD' => 422,
                'USER_NOT_FOUND' => 404,
                'TWO_FACTOR_NOT_ENABLED' => 409,
                default => 400,
            };

            return $this->error($code, $this->t('security/messages.two_factor_disable_failed'), $status, [
                'two_factor' => [$code],
            ]);
        }

        return $this->success('TWO_FACTOR_DISABLED', $this->t('security/messages.two_factor_disabled'));
    }
}
