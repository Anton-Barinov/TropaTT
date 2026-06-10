<?php
declare(strict_types=1);

namespace Api\Controller\Tag;

use Api\Controller\Common\BaseController;
use Api\System\Library\Cache\ApiFileCache;
use Api\System\Library\Service\TagService;
use Api\System\Library\Validation\Validator;

final class TagController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $cache = $this->getApiFileCache();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'list:' . md5(json_encode($input));
            $result = $cache->remember('tag', $cacheKey, 60, function () use ($input) {
                /** @var TagService $service */
                $service = $this->container->get('service.tag');
                return $service->list($input);
            });
        } else {
            /** @var TagService $service */
            $service = $this->container->get('service.tag');
            $result = $service->list($this->request()->allInput());
        }

        return $this->success('TAG_LIST', $this->t('tag/messages.list'), ['items' => $result['items']], meta: $result['meta']);
    }

    private function getApiFileCache(): ?ApiFileCache
    {
        if (!$this->container->has('cache.api')) {
            return null;
        }
        $cache = $this->container->get('cache.api');
        return ($cache instanceof ApiFileCache && $cache->isEnabled()) ? $cache : null;
    }

    private function invalidateTagCache(): void
    {
        $cache = $this->getApiFileCache();
        if ($cache !== null) {
            $cache->invalidateNamespace('tag');
        }
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $item = $service->get((string)$params['public_id']);
        if (!$item) {
            return $this->error('TAG_NOT_FOUND', $this->t('tag/messages.not_found'), 404, [
                'tag' => [$this->t('tag/messages.not_found')],
            ]);
        }

        return $this->success('TAG_DETAIL', $this->t('tag/messages.detail'), ['tag' => $item]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        // Accept 'name' as alias for 'title'
        if (empty($input['title']) && !empty($input['name'])) {
            $input['title'] = $input['name'];
        }
        // Auto-generate code from title if not provided
        if (empty($input['code']) && !empty($input['title'])) {
            $input['code'] = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', trim((string)$input['title'])));
        }
        $v = new Validator();
        $v->require($input, 'code', $this->t('common/messages.field_required'))
            ->require($input, 'title', $this->t('common/messages.field_required'))
            ->maxLen($input, 'code', 64, $this->t('tag/messages.max_64'))
            ->maxLen($input, 'title', 255, $this->t('tag/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $item = $service->create($input);
        if (is_string($item) && $item === 'TAG_CODE_EXISTS') {
            return $this->error('TAG_CODE_EXISTS', $this->t('tag/messages.code_exists'), 409, [
                'code' => [$this->t('tag/messages.code_exists')],
            ]);
        }

        $this->invalidateTagCache();

        return $this->success('TAG_CREATED', $this->t('tag/messages.created'), ['tag' => $item], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        // Accept 'name' as alias for 'title'
        if (empty($input['title']) && !empty($input['name'])) {
            $input['title'] = $input['name'];
        }
        $v = new Validator();
        $v->maxLen($input, 'code', 64, $this->t('tag/messages.max_64'))
            ->maxLen($input, 'title', 255, $this->t('tag/messages.max_255'));

        if ($v->fails()) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, $v->errors());
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $item = $service->update((string)$params['public_id'], $input);
        if ($item === null) {
            return $this->error('TAG_NOT_FOUND', $this->t('tag/messages.not_found'), 404, [
                'tag' => [$this->t('tag/messages.not_found')],
            ]);
        }
        if (is_string($item) && $item === 'TAG_CODE_EXISTS') {
            return $this->error('TAG_CODE_EXISTS', $this->t('tag/messages.code_exists'), 409, [
                'code' => [$this->t('tag/messages.code_exists')],
            ]);
        }

        $this->invalidateTagCache();

        return $this->success('TAG_UPDATED', $this->t('tag/messages.updated'), ['tag' => $item]);
    }

    public function delete(array $params): \Api\System\Library\Http\JsonResponse
    {
        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $ok = $service->delete((string)$params['public_id']);
        if (!$ok) {
            return $this->error('TAG_NOT_FOUND', $this->t('tag/messages.not_found'), 404, [
                'tag' => [$this->t('tag/messages.not_found')],
            ]);
        }

        $this->invalidateTagCache();

        return $this->success('TAG_DELETED', $this->t('tag/messages.deleted'));
    }

    public function listTaskTags(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $items = $service->listTaskTags((string)$params['task_public_id'], $auth['user']);
        if ($items === null) {
            return $this->error('TASK_NOT_FOUND', $this->t('tag/messages.task_not_found'), 404, [
                'task' => [$this->t('tag/messages.task_not_found')],
            ]);
        }

        return $this->success('TASK_TAG_LIST', $this->t('tag/messages.task_tags'), ['items' => $items]);
    }

    public function attachTaskTag(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $ok = $service->attachToTask((string)$params['task_public_id'], (string)$params['tag_public_id'], $auth['user']);
        if (!$ok) {
            return $this->error('TASK_OR_TAG_NOT_FOUND', $this->t('tag/messages.task_or_tag_not_found'), 404, [
                'task' => [$this->t('tag/messages.task_or_tag_not_found')],
            ]);
        }

        return $this->success('TASK_TAG_ATTACHED', $this->t('tag/messages.attached'));
    }

    public function detachTaskTag(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var TagService $service */
        $service = $this->container->get('service.tag');
        $ok = $service->detachFromTask((string)$params['task_public_id'], (string)$params['tag_public_id'], $auth['user']);
        if (!$ok) {
            return $this->error('TASK_OR_TAG_NOT_FOUND', $this->t('tag/messages.task_or_tag_not_found'), 404, [
                'task' => [$this->t('tag/messages.task_or_tag_not_found')],
            ]);
        }

        return $this->success('TASK_TAG_DETACHED', $this->t('tag/messages.detached'));
    }
}
