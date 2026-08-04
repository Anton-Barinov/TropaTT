<?php
declare(strict_types=1);

namespace Api\Controller\User;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\UserService;
use Api\System\Library\Validation\Validator;

final class UserController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();

        // SEC-004: Non-root users should only see their own team members
        $auth = $this->user();
        if ($auth && !(bool)($auth['user']['is_root'] ?? false)) {
            $input['created_by_user_id'] = (int)($auth['user']['id'] ?? 0);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            ksort($input);
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('user', $cacheKey, 60, function () use ($input) {
                /** @var UserService $service */
                $service = $this->container->get('service.user');
                return $service->list($input);
            });
        } else {
            /** @var UserService $service */
            $service = $this->container->get('service.user');
            $result = $service->list($input);
        }

        $items = $result['items'];
        if (!$this->isCurrentUserRoot()) {
            $items = array_map(fn(array $u) => $this->stripFinancialRates($u), $items);
        }

        return $this->success('USER_LIST', $this->t('user/messages.list'), ['items' => $items], meta: $result['meta']);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $user = $service->get((string)$params['public_id']);
        if (!$user) {
            return $this->error('USER_NOT_FOUND', $this->t('user/messages.not_found'), 404, ['user' => [$this->t('user/messages.not_found')]]);
        }

        if (!$this->isCurrentUserRoot() && !$this->isViewingSelf((string)$params['public_id'])) {
            $user = $this->stripFinancialRates($user);
        }

        return $this->success('USER_DETAIL', $this->t('user/messages.detail'), ['user' => $user]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->validatedInput(['login', 'password', 'email', 'full_name', 'locale', 'is_root', 'token', 'role_public_ids', 'team_public_id']);

        // SEC-002: Only root users may assign root status or roles.
        if (!$this->isCurrentUserRoot()) {
            unset($input['is_root'], $input['role_public_ids']);
        }

        $v = new Validator();
        $v->require($input, 'login', $this->t('common/messages.field_required'))
            ->require($input, 'password', $this->t('common/messages.field_required'))
            ->maxLen($input, 'login', 120, $this->t('user/messages.max_120'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->create($input, $auth['user']);

        if (!$result['ok']) {
            return $this->error((string)$result['code'], $this->t('user/messages.create_failed'), 403, ['user' => [(string)$result['code']]]);
        }

        // SEC-002: Financial fields (cost_rate/bill_rate) are root-only data.
        // The create input never accepts them, but the returned user record
        // must still be stripped for non-root actors (see AGENTS.md).
        if (!$this->isCurrentUserRoot()) {
            $result['user'] = $this->stripFinancialRates($result['user']);
        }

        $this->invalidateCache('worklog');

        return $this->success('USER_CREATED', $this->t('user/messages.created'), ['user' => $result['user']], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->validatedInput(['email', 'full_name', 'locale', 'cost_rate', 'bill_rate', 'is_active', 'password', 'token', 'is_root', 'role_public_ids', 'team_public_id']);

        // SEC-002: Only root users may change root status, role assignments,
        // or financial rates (cost_rate/bill_rate) of other users.
        if (!$this->isCurrentUserRoot()) {
            unset($input['is_root'], $input['role_public_ids'], $input['cost_rate'], $input['bill_rate']);
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->update((string)$params['public_id'], $input, $auth['user']);

        if (!$result['ok']) {
            $status = in_array((string)$result['code'], ['USER_NOT_FOUND'], true) ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('user/messages.update_failed'), $status, ['user' => [(string)$result['code']]]);
        }

        // SEC-002: Never echo financial rates of the updated user back to a
        // non-root actor, even when the update itself did not touch them.
        if (!$this->isCurrentUserRoot()) {
            $result['user'] = $this->stripFinancialRates($result['user']);
        }

        $this->invalidateCache('worklog');

        return $this->success('USER_UPDATED', $this->t('user/messages.updated'), ['user' => $result['user']]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->delete((string)$params['public_id'], $auth['user']);

        if (!$result['ok']) {
            $status = in_array((string)$result['code'], ['USER_NOT_FOUND'], true) ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('user/messages.delete_failed'), $status, ['user' => [(string)$result['code']]]);
        }

        $this->invalidateCache('worklog');

        return $this->success('USER_DELETED', $this->t('user/messages.deleted'));
    }

    public function tokens(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->tokenInfo((string)$params['public_id'], $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = (string)($result['code'] ?? '') === 'USER_NOT_FOUND' ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('user/messages.token_info_failed'), $status, [
                'user' => [(string)($result['code'] ?? 'ERROR')],
            ]);
        }

        return $this->success('USER_TOKEN_INFO', $this->t('user/messages.token_info'), [
            'token_factor' => $result['token'],
        ]);
    }

    public function rotateToken(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->rotateToken((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = (string)($result['code'] ?? '') === 'USER_NOT_FOUND' ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('user/messages.token_rotate_failed'), $status, [
                'user' => [(string)($result['code'] ?? 'ERROR')],
            ]);
        }

        return $this->success('USER_TOKEN_ROTATED', $this->t('user/messages.token_rotated'), [
            'plain_token' => (string)$result['plain_token'],
        ]);
    }

    public function revokeToken(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->revokeToken((string)$params['public_id'], $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = (string)($result['code'] ?? '') === 'USER_NOT_FOUND' ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('user/messages.token_revoke_failed'), $status, [
                'user' => [(string)($result['code'] ?? 'ERROR')],
            ]);
        }

        return $this->success('USER_TOKEN_REVOKED', $this->t('user/messages.token_revoked'));
    }

    private function isCurrentUserRoot(): bool
    {
        $auth = $this->user();
        return (bool)($auth['user']['is_root'] ?? false);
    }

    private function isViewingSelf(string $publicId): bool
    {
        $auth = $this->user();
        return (string)($auth['user']['public_id'] ?? '') === $publicId;
    }

    private function stripFinancialRates(array $user): array
    {
        unset($user['cost_rate'], $user['bill_rate']);
        return $user;
    }

    public function activity(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var UserService $service */
        $service = $this->container->get('service.user');
        $result = $service->activity((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $status = (string)($result['code'] ?? '') === 'USER_NOT_FOUND' ? 404 : 403;
            return $this->error((string)$result['code'], $this->t('user/messages.activity_failed'), $status, [
                'user' => [(string)($result['code'] ?? 'ERROR')],
            ]);
        }

        return $this->success('USER_ACTIVITY', $this->t('user/messages.activity'), [
            'request_logs' => $result['request']['items'],
            'security_logs' => $result['security']['items'],
            'audit_logs' => $result['audit']['items'],
        ], meta: [
            'request_pagination' => $result['request']['meta']['pagination'],
            'security_pagination' => $result['security']['meta']['pagination'],
            'audit_pagination' => $result['audit']['meta']['pagination'],
        ]);
    }
}
