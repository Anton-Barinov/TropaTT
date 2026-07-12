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

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $userId = (string)($auth['user']['id'] ?? 0);
            $cacheKey = 'list:' . $userId . ':' . md5(json_encode($input));
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

        $input = $this->request()->allInput();
        $v = new Validator();
        $v->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'title', 255, $this->t('team/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        $team = $service->create($input, $auth['user']);

        $this->invalidateCache('team');

        return $this->success('TEAM_CREATED', $this->t('team/messages.created'), ['team' => $team], 201);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

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

        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        $team = $service->update((string)$params['public_id'], $this->request()->allInput(), $auth['user']);

        if (!$team) {
            return $this->error('TEAM_NOT_FOUND', $this->t('team/messages.not_found'), 404, [
                'team' => [$this->t('team/messages.not_found')],
            ]);
        }

        $this->invalidateCache('team');

        return $this->success('TEAM_UPDATED', $this->t('team/messages.updated'), ['team' => $team]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TeamService $service */
        $service = $this->container->get('service.team');
        $ok = $service->delete((string)$params['public_id'], $auth['user']);

        if (!$ok) {
            return $this->error('TEAM_NOT_FOUND', $this->t('team/messages.not_found'), 404, [
                'team' => [$this->t('team/messages.not_found')],
            ]);
        }

        $this->invalidateCache('team');

        return $this->success('TEAM_DELETED', $this->t('team/messages.deleted'));
    }
}
