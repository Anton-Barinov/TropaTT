<?php
declare(strict_types=1);

namespace Api\Controller\Security;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\PasswordResetService;
use Api\System\Library\Validation\Validator;

final class PasswordResetController extends BaseController
{
    public function requestReset(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $identifier = trim((string)($input['identifier'] ?? $input['login'] ?? ''));
        if ($identifier === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'identifier' => [$this->t('security/messages.password_reset_identifier_required')],
            ]);
        }

        /** @var PasswordResetService $service */
        $service = $this->container->get('service.password_reset');
        $service->request($input, $this->request()->ip());

        return $this->success('PASSWORD_RESET_REQUESTED', $this->t('security/messages.password_reset_requested'), [
            'accepted' => true,
        ]);
    }

    public function confirm(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $validator = new Validator();
        $validator->require($input, 'reset_token', $this->t('common/messages.field_required'))
            ->require($input, 'new_password', $this->t('common/messages.field_required'));
        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        $newPassword = (string)$input['new_password'];
        if (strlen($newPassword) < 12 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'new_password' => [$this->t('security/messages.min_password_12_complex')],
            ]);
        }

        /** @var PasswordResetService $service */
        $service = $this->container->get('service.password_reset');
        $result = $service->confirm($input, $this->request()->ip());
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? '');
            $status = match ($code) {
                'PASSWORD_RESET_TOKEN_INVALID', 'PASSWORD_RESET_TOKEN_EXPIRED', 'USER_NOT_FOUND' => 404,
                default => 400,
            };

            return $this->error($code, $this->t('security/messages.password_reset_confirm_failed'), $status, [
                'password_reset' => [$code],
            ]);
        }

        return $this->success('PASSWORD_RESET_COMPLETED', $this->t('security/messages.password_reset_completed'), [
            'reset' => $result['reset'],
        ]);
    }
}
