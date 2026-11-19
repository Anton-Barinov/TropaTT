<?php
declare(strict_types=1);

namespace Api\Controller\Team;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\TeamService;
use Api\System\Library\Validation\Validator;

final class TeamController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        $auth['user'] = $this->organizationScopedActor((array)$auth['user']);

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $userId = (string)($auth['user']['id'] ?? 0);
            $cacheKey = 'list:' . $userId . ':' . $this->organizationContextCacheKey() . ':' . hash('sha256', json_encode($input));
            $result = $cache->remember('team', $cacheKey, 60, function () use ($input, $auth) {
                /** @var TeamService $service */
                $service = $this->container->get('service.team');
                return $service->list($input, $auth['user']);
            });
        } else {
            /** @var TeamService $service */
            $service = $this->container->get('service.team');
            $result = $service->list($this->request()->allInput(), $auth['user']);
        }

        return $this->success('TEAM_LIST', $this->t('team/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        $auth['user'] = $this->organizationScopedActor((array)$auth['user']);

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('team/messages.max_255'));

        $orgId = isset($auth['user']['organization_id']) ? (int)$auth['user']['organization_id'] : null;
        if ($orgId !== null && $orgId <= 0) {
            $orgId = null;
        }

        /** @var \Api\Model\Team\TeamRepository $repo */
        $repo = $this->container->get('repository.team');

        if (!empty($input['manager_user_public_id'])) {
            $mId = $repo->userIdByPublicId(trim((string)$input['manager_user_public_id']), $orgId);
            if ($mId === null) {
                $v->addError('manager_user_public_id', $this->t('common/messages.validation_error'));
            }
        }

        if (!empty($input['parent_public_id'])) {
            $parent = $repo->findByPublicId(trim((string)$input['parent_public_id']), $orgId);
            if (!$parent) {
                $v->addError('parent_public_id', $this->t('common/messages.validation_error'));
            }
        }

        if (isset($input['member_user_public_ids'])) {
            if (!is_array($input['member_user_public_ids'])) {
                $v->addError('member_user_public_ids', $this->t('common/messages.validation_error'));
            } else {
                $norm = array_values(array_unique(array_filter(array_map(static fn($v): string => trim((string)$v), $input['member_user_public_ids']), static fn(string $v): bool => $v !== '')));
                if ($norm !== []) {
                    $resolved = $repo->userIdsByPublicIds($norm, $orgId);
                    if (count($resolved) !== count($norm)) {
                        $v->addError('member_user_public_ids', $this->t('common/messages.validation_error'));
                    }
                }
            }
        }

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        $team = $service->create($input, $auth['user']);

        $this->invalidateCache('team');
        $this->invalidateCache('worklog');

        return $this->success('TEAM_CREATED', $this->t('team/messages.created'), ['team' => $team], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        $auth['user'] = $this->organizationScopedActor((array)$auth['user']);

        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        $team = $service->get((string)$params['public_id'], $auth['user']);

        if (!$team) {
            return $this->error('TEAM_NOT_FOUND', $this->t('team/messages.not_found'), 404, [
                'team' => [$this->t('team/messages.not_found')],
            ]);
        }

        return $this->success('TEAM_DETAIL', $this->t('team/messages.detail'), ['team' => $team]);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        $auth['user'] = $this->organizationScopedActor((array)$auth['user']);

        $input = $this->request()->allInput();
        $orgId = isset($auth['user']['organization_id']) ? (int)$auth['user']['organization_id'] : null;
        if ($orgId !== null && $orgId <= 0) {
            $orgId = null;
        }

        /** @var \Api\Model\Team\TeamRepository $repo */
        $repo = $this->container->get('repository.team');

        $v = new Validator();
        if (array_key_exists('title', $input)) {
            $v->require($input, 'title', $this->t('common/messages.field_required'))
                ->maxLen($input, 'title', 255, $this->t('team/messages.max_255'));
        }

        if (array_key_exists('manager_user_public_id', $input)) {
            $raw = $input['manager_user_public_id'];
            if ($raw !== null && trim((string)$raw) !== '') {
                $mId = $repo->userIdByPublicId(trim((string)$raw), $orgId);
                if ($mId === null) {
                    $v->addError('manager_user_public_id', $this->t('common/messages.validation_error'));
                }
            }
        }

        if (array_key_exists('parent_public_id', $input)) {
            $raw = $input['parent_public_id'];
            if ($raw !== null && trim((string)$raw) !== '') {
                $parent = $repo->findByPublicId(trim((string)$raw), $orgId);
                if (!$parent || $parent['public_id'] === $params['public_id']) {
                    $v->addError('parent_public_id', $this->t('common/messages.validation_error'));
                }
            }
        }

        if (array_key_exists('member_user_public_ids', $input)) {
            if (!is_array($input['member_user_public_ids'])) {
                $v->addError('member_user_public_ids', $this->t('common/messages.validation_error'));
            } else {
                $norm = array_values(array_unique(array_filter(array_map(static fn($v): string => trim((string)$v), $input['member_user_public_ids']), static fn(string $v): bool => $v !== '')));
                if ($norm !== []) {
                    $resolved = $repo->userIdsByPublicIds($norm, $orgId);
                    if (count($resolved) !== count($norm)) {
                        $v->addError('member_user_public_ids', $this->t('common/messages.validation_error'));
                    }
                }
            }
        }

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        $team = $service->update((string)$params['public_id'], $input, $auth['user']);

        if (!$team) {
            return $this->error('TEAM_NOT_FOUND', $this->t('team/messages.not_found'), 404, [
                'team' => [$this->t('team/messages.not_found')],
            ]);
        }

        $this->invalidateCache('team');
        $this->invalidateCache('worklog');

        return $this->success('TEAM_UPDATED', $this->t('team/messages.updated'), ['team' => $team]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        $auth['user'] = $this->organizationScopedActor((array)$auth['user']);

        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);

        if (!$ok) {
            return $this->error('TEAM_NOT_FOUND', $this->t('team/messages.not_found'), 404, [
                'team' => [$this->t('team/messages.not_found')],
            ]);
        }

        $this->invalidateCache('team');
        $this->invalidateCache('worklog');

        return $this->success('TEAM_DELETED', $this->t('team/messages.deleted'));
    }
}
