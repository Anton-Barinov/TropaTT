<?php
declare(strict_types=1);

namespace Api\Controller\Security;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\InvitationService;
use Api\System\Library\Validation\Validator;

final class InvitationController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var InvitationService $service */
        $service = $this->container->get('service.invitation');
        $result = $service->list($this->request()->allInput(), $auth['user']);

        return $this->success('INVITATION_LIST', $this->t('security/messages.invitation_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $validator = new Validator();
        $validator->require($input, 'email', $this->t('common/messages.field_required'))
            ->maxLen($input, 'email', 190, $this->t('security/messages.max_190'));
        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        $email = trim((string)$input['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'email' => [$this->t('security/messages.invalid_email')],
            ]);
        }

        /** @var InvitationService $service */
        $service = $this->container->get('service.invitation');
        $result = $service->create($input, $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = (string)($result['code'] ?? '') === 'INVITATION_EMAIL_EXISTS' ? 409 : 400;

            return $this->error((string)$result['code'], $this->t('security/messages.invitation_create_failed'), $status, [
                'invitation' => [(string)$result['code']],
            ]);
        }

        return $this->success('INVITATION_CREATED', $this->t('security/messages.invitation_created'), [
            'invitation' => $result['invitation'],
            'accept_token' => $result['accept_token'],
        ], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var InvitationService $service */
        $service = $this->container->get('service.invitation');
        $item = $service->get((string)$params['public_id'], $auth['user']);
        if (!$item) {
            return $this->error('INVITATION_NOT_FOUND', $this->t('security/messages.invitation_not_found'), 404, [
                'invitation' => [$this->t('security/messages.invitation_not_found')],
            ]);
        }

        return $this->success('INVITATION_DETAIL', $this->t('security/messages.invitation_detail'), [
            'invitation' => $item,
        ]);
    }

    public function accept(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $validator = new Validator();
        $validator->require($input, 'invitation_token', $this->t('common/messages.field_required'))
            ->require($input, 'login', $this->t('common/messages.field_required'))
            ->require($input, 'full_name', $this->t('common/messages.field_required'))
            ->require($input, 'password', $this->t('common/messages.field_required'));
        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $validator->errors());
        }

        $password = (string)$input['password'];
        if (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'password' => [$this->t('security/messages.min_password_12_complex')],
            ]);
        }

        /** @var InvitationService $service */
        $service = $this->container->get('service.invitation');
        $result = $service->accept($input);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? '');
            $status = match ($code) {
                'INVITATION_NOT_FOUND', 'INVITATION_EXPIRED' => 404,
                'USER_LOGIN_EXISTS', 'USER_EMAIL_EXISTS' => 409,
                default => 400,
            };

            return $this->error($code, $this->t('security/messages.invitation_accept_failed'), $status, [
                'invitation' => [$code],
            ]);
        }

        return $this->success('INVITATION_ACCEPTED', $this->t('security/messages.invitation_accepted'), [
            'invitation' => $result['invitation'],
            'user' => $result['user'],
            'user_token' => $result['user_token'],
        ], 201);
    }
}
