<?php
declare(strict_types=1);

namespace Api\Controller\Audit;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\LogsService;

final class AuditController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        $result = $service->auditList($this->request()->allInput());

        return $this->success('AUDIT_LIST', $this->t('audit/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function byUser(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $filters = $this->request()->allInput();
        $filters['actor_public_id'] = (string)$params['public_id'];

        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        $result = $service->auditList($filters);

        return $this->success('AUDIT_USER', $this->t('audit/messages.user'), [
            'actor_public_id' => (string)$params['public_id'],
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function byEntity(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $filters = $this->request()->allInput();
        $filters['entity_type'] = (string)$params['entity_type'];
        $filters['entity_public_id'] = (string)$params['public_id'];

        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        $result = $service->auditList($filters);

        return $this->success('AUDIT_ENTITY', $this->t('audit/messages.entity'), [
            'entity_type' => (string)$params['entity_type'],
            'entity_public_id' => (string)$params['public_id'],
            'items' => $result['items'],
        ], meta: $result['meta']);
    }
}
