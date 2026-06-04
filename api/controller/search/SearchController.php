<?php
declare(strict_types=1);

namespace Api\Controller\Search;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\SearchService;

final class SearchController extends BaseController
{
    public function global(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $query = trim((string)$this->request()->input('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'q' => [$this->t('search/messages.query_too_short')],
            ]);
        }

        $limit = min(50, max(1, (int)$this->request()->input('limit', 10)));

        /** @var SearchService $service */
        $service = $this->container->get('service.search');
        $result = $service->global($query, $authUser['user'], $limit);

        return $this->success('SEARCH_GLOBAL', $this->t('search/messages.global'), $result);
    }

    public function tasks(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $query = trim((string)$this->request()->input('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'q' => [$this->t('search/messages.query_too_short')],
            ]);
        }

        $limit = min(100, max(1, (int)$this->request()->input('limit', 20)));

        /** @var SearchService $service */
        $service = $this->container->get('service.search');
        $items = $service->tasks($query, $authUser['user'], $limit);

        return $this->success('SEARCH_TASKS', $this->t('search/messages.tasks'), [
            'query' => $query,
            'items' => $items,
            'count' => count($items),
        ]);
    }

    public function projects(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $query = trim((string)$this->request()->input('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'q' => [$this->t('search/messages.query_too_short')],
            ]);
        }

        $limit = min(100, max(1, (int)$this->request()->input('limit', 20)));

        /** @var SearchService $service */
        $service = $this->container->get('service.search');
        $items = $service->projects($query, $authUser['user'], $limit);

        return $this->success('SEARCH_PROJECTS', $this->t('search/messages.projects'), [
            'query' => $query,
            'items' => $items,
            'count' => count($items),
        ]);
    }

    public function clients(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $query = trim((string)$this->request()->input('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'q' => [$this->t('search/messages.query_too_short')],
            ]);
        }

        $limit = min(100, max(1, (int)$this->request()->input('limit', 20)));
        $type = $this->request()->input('type');
        $typeFilter = null;
        if ($type !== null && $type !== '') {
            $typeFilter = array_map('trim', explode(',', (string)$type));
        }

        /** @var SearchService $service */
        $service = $this->container->get('service.search');
        $items = $service->counterparties($query, $limit, $typeFilter);

        return $this->success('SEARCH_CLIENTS', $this->t('search/messages.clients'), [
            'query' => $query,
            'items' => $items,
            'count' => count($items),
        ]);
    }

    /**
     * Unified counterparty search with optional type filter.
     * ?type=organization,individual,sole_proprietor,legal_entity
     */
    public function counterparties(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $query = trim((string)$this->request()->input('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'q' => [$this->t('search/messages.query_too_short')],
            ]);
        }

        $limit = min(100, max(1, (int)$this->request()->input('limit', 20)));
        $type = $this->request()->input('type');
        $typeFilter = null;
        if ($type !== null && $type !== '') {
            $typeFilter = array_map('trim', explode(',', (string)$type));
        }

        /** @var SearchService $service */
        $service = $this->container->get('service.search');
        $items = $service->counterparties($query, $limit, $typeFilter);

        return $this->success('SEARCH_COUNTERPARTIES', $this->t('search/messages.counterparties'), [
            'query' => $query,
            'items' => $items,
            'count' => count($items),
        ]);
    }

    public function suggestions(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $query = trim((string)$this->request()->input('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.validation_error'), 422, [
                'q' => [$this->t('search/messages.query_too_short')],
            ]);
        }

        $limit = min(50, max(1, (int)$this->request()->input('limit', 15)));

        /** @var SearchService $service */
        $service = $this->container->get('service.search');
        $result = $service->suggestions($query, $authUser['user'], $limit);

        return $this->success('SEARCH_SUGGESTIONS', $this->t('search/messages.suggestions'), $result);
    }
}
