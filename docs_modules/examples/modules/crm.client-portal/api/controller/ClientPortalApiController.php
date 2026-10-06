<?php
declare(strict_types=1);

namespace Module\Crm\ClientPortal\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\ClientPortal\Service\ClientPortalService;

final class ClientPortalApiController extends BaseController
{
    private function getService(): ClientPortalService
    {
        return new ClientPortalService($this->container->get('db.pdo'));
    }

    public function listRequests(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $orgId = (int)($auth['user']['organization_id'] ?? 1);
        $clientPublicId = $this->resolveClientPublicId();

        $service = $this->getService();
        $items = $service->listRequests($orgId, $clientPublicId);

        return $this->success('CLIENT_SERVICE_REQUESTS_LIST', 'Requests listed', ['items' => $items]);
    }

    public function createRequest(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $orgId = (int)($auth['user']['organization_id'] ?? 1);
        $clientPublicId = $this->resolveClientPublicId();
        if ($clientPublicId === null) {
            return $this->error('FORBIDDEN', 'Only client users or administrators can create service requests', 403);
        }

        $input = $this->request()->allInput();
        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $category = trim((string)($input['category'] ?? 'general'));

        if ($title === '' || $description === '') {
            return $this->error('VALIDATION_ERROR', 'Title and description are required', 422);
        }

        $userPublicId = (string)($auth['user']['public_id'] ?? 'usr_guest');
        $service = $this->getService();
        $pubId = $service->submitRequest($orgId, $clientPublicId, $category, $title, $description, $userPublicId);

        return $this->success('CLIENT_SERVICE_REQUEST_CREATED', 'Request created', ['public_id' => $pubId]);
    }

    public function listMessages(array $params): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $orgId = (int)($auth['user']['organization_id'] ?? 1);
        $reqPubId = (string)($params['public_id'] ?? '');
        $clientPublicId = $this->resolveClientPublicId();
        $isClient = ($clientPublicId !== null && empty($auth['user']['is_root']));

        $service = $this->getService();
        $req = $service->getRequest($orgId, $reqPubId, $clientPublicId);
        if (!$req) {
            return $this->error('NOT_FOUND', 'Request not found or access denied', 404);
        }

        $messages = $service->getMessages($orgId, $reqPubId, $isClient);
        return $this->success('CLIENT_SERVICE_MESSAGES_LIST', 'Messages listed', ['items' => $messages]);
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
