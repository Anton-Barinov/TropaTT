<?php
declare(strict_types=1);

namespace Api\Controller\External;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;

/**
 * Controller for managing external guest users (client portal).
 *
 * Endpoints:
 *   POST   /api/v1/external-users/invite     — invite from a contact
 *   POST   /api/v1/external-users/accept     — accept invitation (set password)
 *   GET    /api/v1/external-users             — list all external users
 *   POST   /api/v1/external-users/{id}/deactivate — deactivate external user
 */
final class ExternalUserController extends BaseController
{
    /**
     * POST /api/v1/external-users/invite
     *
     * Invite an external user from a contact record.
     * Body: { contact_id: int }
     */
    public function invite(): JsonResponse
    {
        $input = $this->request()->allInput();
        $contactPublicId = trim((string)($input['contact_public_id'] ?? $input['contact_id'] ?? ''));
        $authUser = $this->user();

        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('security/messages.unauthorized'), 401);
        }
        $actor = $authUser['user'] ?? [];

        if ($contactPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('external_users.contact_id_required'), 422, [
                'contact_id' => [$this->t('external_users.contact_id_required')],
            ]);
        }

        /** @var \Api\System\Library\Service\ExternalUserService $service */
        $service = $this->container->get('service.external_user');
        $result = $service->inviteByPublicId($contactPublicId, $actor);

        if (!$result['ok']) {
            $errorCode = strtoupper((string)($result['error'] ?? 'UNKNOWN'));
            return $this->error(
                'EXTERNAL_INVITE_FAILED',
                $this->t('external_users.invite_failed'),
                422,
                ['error' => [$result['error'] ?? 'unknown']]
            );
        }

        return $this->success(
            'EXTERNAL_USER_INVITED',
            $this->t('external_users.invited'),
            [
                'token' => $result['token'],
                'user_public_id' => $result['user_public_id'],
                'login' => $result['login'],
                'email' => $result['email'],
            ]
        );
    }

    /**
     * POST /api/v1/external-users/accept
     *
     * Accept an external user invitation and set password.
     * Body: { token: string, password: string }
     *
     * This endpoint is PUBLIC (no auth required) — the user is activating their account.
     */
    public function accept(): JsonResponse
    {
        $input = $this->request()->allInput();

        /** @var \Api\System\Library\Service\ExternalUserService $service */
        $service = $this->container->get('service.external_user');
        $result = $service->acceptInvitation($input);

        if (!$result['ok']) {
            $errorCode = strtoupper((string)($result['error'] ?? 'UNKNOWN'));
            return $this->error(
                'EXTERNAL_ACCEPT_FAILED',
                $this->t('external_users.accept_failed'),
                422,
                ['error' => [$result['error'] ?? 'unknown']]
            );
        }

        return $this->success(
            'EXTERNAL_USER_ACTIVATED',
            $this->t('external_users.activated'),
            [
                'user' => $result['user'],
                'session_token' => $result['session_token'],
            ]
        );
    }

    /**
     * GET /api/v1/external-users
     *
     * List all external users with contact/counterparty info.
     */
    public function list(): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('security/messages.unauthorized'), 401);
        }
        $actor = $authUser['user'] ?? [];

        /** @var \Api\System\Library\Service\ExternalUserService $service */
        $service = $this->container->get('service.external_user');
        $result = $service->listExternalUsers($actor);

        return $this->success(
            'EXTERNAL_USERS_LIST',
            $this->t('external_users.list'),
            $result
        );
    }

    /**
     * POST /api/v1/external-users/{public_id}/deactivate
     *
     * Deactivate an external user (revoke access).
     */
    public function deactivate(array $params): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('security/messages.unauthorized'), 401);
        }
        $actor = $authUser['user'] ?? [];

        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('external_users.public_id_required'), 422);
        }

        /** @var \Api\System\Library\Service\ExternalUserService $service */
        $service = $this->container->get('service.external_user');
        $result = $service->deactivate($publicId, $actor);

        if (!$result['ok']) {
            return $this->error(
                'EXTERNAL_DEACTIVATE_FAILED',
                $this->t('external_users.deactivate_failed'),
                422,
                ['error' => [$result['error'] ?? 'unknown']]
            );
        }

        return $this->success(
            'EXTERNAL_USER_DEACTIVATED',
            $this->t('external_users.deactivated')
        );
    }
}
