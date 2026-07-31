<?php
declare(strict_types=1);

namespace Api\Controller\Knowledge;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;

final class KnowledgePageVersionController extends BaseController
{
    private function service(): \Api\System\Library\Service\KnowledgePageVersionService
    {
        return $this->container->get('service.knowledge_page_version');
    }

    private function actor(): array
    {
        $auth = $this->user();
        return is_array($auth['user'] ?? null) ? $auth['user'] : [];
    }

    public function list(array $params): JsonResponse
    {
        $pagePublicId = (string)$params['public_id'];
        $filters = $this->request()->allInput();
        $filters['limit'] = min(100, max(1, (int)($filters['limit'] ?? 30)));
        $filters['page'] = max(1, (int)($filters['page'] ?? 1));

        $result = $this->service()->listVersions($pagePublicId, $filters, $this->actor());

        if ($result === 'KNOWLEDGE_PAGE_NOT_FOUND') {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        return $this->success('KNOWLEDGE_PAGE_VERSION_LIST', $this->t('knowledge/messages.versions', 'Knowledge page versions'), $result);
    }

    public function get(array $params): JsonResponse
    {
        $pagePublicId = (string)$params['public_id'];
        $versionPublicId = (string)$params['version_public_id'];

        $result = $this->service()->getVersion($pagePublicId, $versionPublicId, $this->actor());

        if ($result === 'KNOWLEDGE_PAGE_NOT_FOUND') {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        if ($result === 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND') {
            return $this->error('KNOWLEDGE_PAGE_VERSION_NOT_FOUND', $this->t('knowledge/messages.version_not_found', 'Version not found'), 404);
        }

        return $this->success('KNOWLEDGE_PAGE_VERSION_DETAIL', $this->t('knowledge/messages.version_detail', 'Version detail'), [
            'version' => $result,
        ]);
    }

    public function restore(array $params): JsonResponse
    {
        $pagePublicId = (string)$params['public_id'];
        $versionPublicId = (string)$params['version_public_id'];
        $input = $this->request()->allInput();

        $result = $this->service()->restoreVersion($pagePublicId, $versionPublicId, $input, $this->actor());

        if ($result === 'KNOWLEDGE_PAGE_NOT_FOUND') {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        if ($result === 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND') {
            return $this->error('KNOWLEDGE_PAGE_VERSION_NOT_FOUND', $this->t('knowledge/messages.version_not_found', 'Version not found'), 404);
        }

        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('knowledge/messages.row_version_conflict', 'The page was changed by another user'), 409);
        }

        return $this->success('KNOWLEDGE_PAGE_VERSION_RESTORED', $this->t('knowledge/messages.version_restored', 'Version restored'), [
            'page' => $result,
        ], meta: ['row_version' => (int)($result['row_version'] ?? 1)]);
    }

    public function diff(array $params): JsonResponse
    {
        $pagePublicId = (string)$params['public_id'];
        $versionPublicId = (string)$params['version_public_id'];

        $result = $this->service()->diffVersion($pagePublicId, $versionPublicId, $this->actor());

        if ($result === 'KNOWLEDGE_PAGE_NOT_FOUND') {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        if ($result === 'KNOWLEDGE_PAGE_VERSION_NOT_FOUND') {
            return $this->error('KNOWLEDGE_PAGE_VERSION_NOT_FOUND', $this->t('knowledge/messages.version_not_found', 'Version not found'), 404);
        }

        return $this->success('KNOWLEDGE_PAGE_VERSION_DIFF', $this->t('knowledge/messages.diff', 'Version diff'), $result);
    }

    public function lock(array $params): JsonResponse
    {
        $pagePublicId = (string)$params['public_id'];
        $input = $this->request()->allInput();

        $result = $this->service()->lockPage($pagePublicId, $input, $this->actor());

        if ($result === 'KNOWLEDGE_PAGE_NOT_FOUND') {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        if ($result === 'KNOWLEDGE_PAGE_ALREADY_LOCKED') {
            return $this->error('KNOWLEDGE_PAGE_ALREADY_LOCKED', $this->t('knowledge/messages.page_locked', 'Page is already locked'), 409);
        }

        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('knowledge/messages.row_version_conflict', 'The page was changed by another user'), 409);
        }

        return $this->success('KNOWLEDGE_PAGE_LOCKED', $this->t('knowledge/messages.page_locked', 'Page locked'), [
            'page' => $result,
        ], meta: ['row_version' => (int)($result['row_version'] ?? 1)]);
    }

    public function unlock(array $params): JsonResponse
    {
        $pagePublicId = (string)$params['public_id'];
        $input = $this->request()->allInput();

        $result = $this->service()->unlockPage($pagePublicId, $input, $this->actor());

        if ($result === 'KNOWLEDGE_PAGE_NOT_FOUND') {
            return $this->error('KNOWLEDGE_PAGE_NOT_FOUND', $this->t('knowledge/messages.page_not_found', 'Knowledge page not found'), 404);
        }

        if ($result === 'ROW_VERSION_CONFLICT') {
            return $this->error('ROW_VERSION_CONFLICT', $this->t('knowledge/messages.row_version_conflict', 'The page was changed by another user'), 409);
        }

        return $this->success('KNOWLEDGE_PAGE_UNLOCKED', $this->t('knowledge/messages.page_unlocked', 'Page unlocked'), [
            'page' => $result,
        ], meta: ['row_version' => (int)($result['row_version'] ?? 1)]);
    }
}
