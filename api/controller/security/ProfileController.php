<?php
declare(strict_types=1);

namespace Api\Controller\Security;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\UserProfileService;
use Api\System\Library\Validation\Validator;

final class ProfileController extends BaseController
{
    public function me(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        $user = $service->me($auth['user']);
        if (!$user) {
            return $this->error('USER_NOT_FOUND', $this->t('security/messages.user_not_found'), 404, [
                'user' => [$this->t('security/messages.user_not_found')],
            ]);
        }

        return $this->success('PROFILE_ME', $this->t('security/messages.profile_me'), ['user' => $user]);
    }

    public function updateMe(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->maxLen($input, 'full_name', 255, $this->t('security/messages.max_255'))
            ->maxLen($input, 'email', 190, $this->t('security/messages.max_190'))
            ->maxLen($input, 'locale', 20, $this->t('security/messages.max_20'))
            ->maxLen($input, 'timezone', 64, $this->t('security/messages.max_64'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        $result = $service->updateMe($auth['user'], $input);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'PROFILE_UPDATE_FAILED');
            $status = match ($code) {
                'USER_NOT_FOUND' => 404,
                'EMAIL_CHANGE_REQUIRES_VERIFICATION' => 409,
                default => 422,
            };

            return $this->error($code, $this->t('security/messages.profile_update_failed'), $status, [
                'user' => [$code],
            ]);
        }

        return $this->success('PROFILE_UPDATED', $this->t('security/messages.profile_updated'), ['user' => $result['user']]);
    }

    public function getPreferences(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        $preferences = $service->preferences($auth['user']);

        return $this->success('PROFILE_PREFERENCES', $this->t('security/messages.profile_preferences'), [
            'preferences' => $preferences,
        ]);
    }

    public function setPreferences(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $preferences = $input['preferences'] ?? null;
        if (!is_array($preferences)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'preferences' => [$this->t('security/messages.preferences_object_expected')],
            ]);
        }

        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        $updated = $service->setPreferences($auth['user'], $preferences);

        return $this->success('PROFILE_PREFERENCES_UPDATED', $this->t('security/messages.profile_preferences_updated'), [
            'preferences' => $updated,
        ]);
    }

    public function changePassword(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'current_password', $this->t('common/messages.field_required'))
            ->require($input, 'new_password', $this->t('common/messages.field_required'))
            ->maxLen($input, 'new_password', 255, $this->t('security/messages.max_255'));
        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        $current = (string)$input['current_password'];
        $new = (string)$input['new_password'];
        if (strlen($new) < 12 || !preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'new_password' => [$this->t('security/messages.min_password_12_complex')],
            ]);
        }

        /** @var UserProfileService $service */
        $service = $this->container->get('service.user_profile');
        $result = $service->changePassword($auth['user'], $current, $new, (string)($auth['session_public_id'] ?? ''));
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'PROFILE_CHANGE_PASSWORD_FAILED');
            $status = $code === 'USER_NOT_FOUND' ? 404 : 422;

            return $this->error($code, $this->t('security/messages.profile_change_password_failed'), $status, [
                'password' => [$code],
            ]);
        }

        return $this->success('PROFILE_PASSWORD_CHANGED', $this->t('security/messages.profile_password_changed'));
    }
}
