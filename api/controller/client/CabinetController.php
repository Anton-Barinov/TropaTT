<?php
declare(strict_types=1);

namespace Api\Controller\Client;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\ClientCabinetService;

final class CabinetController extends BaseController
{
    public function projects(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $clientPublicId = $this->resolveClientPublicId();
        if ($clientPublicId === null) {
            return $this->error('FORBIDDEN', $this->t('client/messages.cabinet_forbidden_scope'), 403);
        }

        /** @var ClientCabinetService $service */
        $service = $this->container->get('service.client_cabinet');
        $result = $service->listProjects($clientPublicId, $this->request()->allInput());

        return $this->success('CLIENT_CABINET_PROJECT_LIST', $this->t('client/messages.cabinet_project_list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function project(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $clientPublicId = $this->resolveClientPublicId();
        if ($clientPublicId === null) {
            return $this->error('FORBIDDEN', $this->t('client/messages.cabinet_forbidden_scope'), 403);
        }

        /** @var ClientCabinetService $service */
        $service = $this->container->get('service.client_cabinet');
        $project = $service->getProject($clientPublicId, (string)$params['public_id']);
        if ($project === null) {
            return $this->error('PROJECT_NOT_FOUND', $this->t('client/messages.cabinet_project_not_found'), 404, [
                'project' => [$this->t('client/messages.cabinet_project_not_found')],
            ]);
        }
        if ($project === 'FORBIDDEN_CLIENT_SCOPE') {
            return $this->error('FORBIDDEN_CLIENT_SCOPE', $this->t('client/messages.cabinet_forbidden_scope'), 403, [
                'project' => [$this->t('client/messages.cabinet_forbidden_scope_hint')],
            ]);
        }

        return $this->success('CLIENT_CABINET_PROJECT_DETAIL', $this->t('client/messages.cabinet_project_detail'), [
            'project' => $project,
        ]);
    }

    public function projectTasks(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $clientPublicId = $this->resolveClientPublicId();
        if ($clientPublicId === null) {
            return $this->error('FORBIDDEN', $this->t('client/messages.cabinet_forbidden_scope'), 403);
        }

        /** @var ClientCabinetService $service */
        $service = $this->container->get('service.client_cabinet');
        $result = $service->listProjectTasks($clientPublicId, (string)$params['public_id'], $this->request()->allInput());
        if (is_string($result)) {
            if ($result === 'PROJECT_NOT_FOUND') {
                return $this->error('PROJECT_NOT_FOUND', $this->t('client/messages.cabinet_project_not_found'), 404, [
                    'project' => [$this->t('client/messages.cabinet_project_not_found')],
                ]);
            }

            return $this->error('FORBIDDEN_CLIENT_SCOPE', $this->t('client/messages.cabinet_forbidden_scope'), 403, [
                'project' => [$this->t('client/messages.cabinet_forbidden_scope_hint')],
            ]);
        }

        return $this->success('CLIENT_CABINET_PROJECT_TASK_LIST', $this->t('client/messages.cabinet_project_task_list'), [
            'project' => $result['project'],
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    private function resolveClientPublicId(): ?string
    {
        $user = $this->user()['user'] ?? [];
        $isRoot = (bool)($user['is_root'] ?? false);
        $inputClientId = trim((string)$this->request()->input('client_public_id', ''));

        if ($isRoot && $inputClientId !== '') {
            return $inputClientId;
        }

        $userEmail = trim((string)($user['email'] ?? ''));
        if ($userEmail === '') {
            return null;
        }
        $stmt = $this->container->get('db.pdo')->prepare(
            'SELECT public_id FROM clients WHERE LOWER(email) = LOWER(:email) LIMIT 1'
        );
        $stmt->execute(['email' => $userEmail]);
        $cid = $stmt->fetchColumn();
        return $cid ?: null;
    }

}
