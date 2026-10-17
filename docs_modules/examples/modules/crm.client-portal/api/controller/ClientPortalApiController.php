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

        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }

        $user = (array)$auth['user'];
        $orgId = $this->activeOrganizationId($user);
        if ($orgId === null) {
            return $this->error('FORBIDDEN', 'An active workspace is required', 403);
        }

        if ($this->isExternalClient($user)) {
            $clientPublicId = $this->resolveClientPublicId($user, $orgId);
            if ($clientPublicId === null) {
                return $this->error('FORBIDDEN', 'No client account is linked to this user in the active workspace', 403);
            }
        } else {
            if (!$this->canManageClientRequests($user)) {
                return $this->error('FORBIDDEN', 'Insufficient permissions to view client service requests', 403);
            }
            $clientPublicId = $this->resolveRequestedClientPublicId($orgId);
            if ($clientPublicId === false) {
                return $this->error('NOT_FOUND', 'Client not found in the active workspace', 404);
            }
        }

        $service = $this->getService();
        $items = $service->listRequests($orgId, $clientPublicId, !$this->isExternalClient($user));

        return $this->success('CLIENT_SERVICE_REQUESTS_LIST', 'Requests listed', ['items' => $items]);
    }

    public function createRequest(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }

        $user = (array)$auth['user'];
        $orgId = $this->activeOrganizationId($user);
        if ($orgId === null) {
            return $this->error('FORBIDDEN', 'An active workspace is required', 403);
        }

        if ($this->isExternalClient($user)) {
            $clientPublicId = $this->resolveClientPublicId($user, $orgId);
        } else {
            if (!$this->canManageClientRequests($user)) {
                return $this->error('FORBIDDEN', 'Insufficient permissions to create client service requests', 403);
            }
            $clientPublicId = $this->resolveRequestedClientPublicId($orgId);
            if ($clientPublicId === false) {
                return $this->error('NOT_FOUND', 'Client not found in the active workspace', 404);
            }
        }
        if ($clientPublicId === null) {
            return $this->error('FORBIDDEN', 'A client account must be selected or linked to this user', 403);
        }

        $input = $this->request()->allInput();
        if (!is_scalar($input['title'] ?? null)
            || !is_scalar($input['description'] ?? null)
            || !is_scalar($input['category'] ?? 'general')) {
            return $this->error('VALIDATION_ERROR', 'Request fields must be plain text', 422);
        }
        $title = trim((string)$input['title']);
        $description = trim((string)$input['description']);
        $category = trim((string)($input['category'] ?? 'general'));

        $titleLength = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        if ($title === '' || $titleLength > 255 || $description === '' || strlen($description) > 10000
            || $category === '' || strlen($category) > 64 || preg_match('/^[A-Za-z0-9_-]+$/D', $category) !== 1) {
            return $this->error('VALIDATION_ERROR', 'Title, description, or category has an invalid length or format', 422);
        }

        $userPublicId = (string)($user['public_id'] ?? 'usr_guest');
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

        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }

        $user = (array)$auth['user'];
        $orgId = $this->activeOrganizationId($user);
        if ($orgId === null) {
            return $this->error('FORBIDDEN', 'An active workspace is required', 403);
        }

        $reqPubId = (string)($params['public_id'] ?? '');
        $isExternalClient = $this->isExternalClient($user);
        if ($isExternalClient) {
            $clientPublicId = $this->resolveClientPublicId($user, $orgId);
            if ($clientPublicId === null) {
                return $this->error('FORBIDDEN', 'No client account is linked to this user in the active workspace', 403);
            }
        } else {
            if (!$this->canManageClientRequests($user)) {
                return $this->error('FORBIDDEN', 'Insufficient permissions to view client service messages', 403);
            }
            $clientPublicId = null;
        }

        $service = $this->getService();
        $req = $service->getRequest($orgId, $reqPubId, $clientPublicId);
        if (!$req) {
            return $this->error('NOT_FOUND', 'Request not found or access denied', 404);
        }

        $messages = $service->getMessages($orgId, $reqPubId, $clientPublicId, !$isExternalClient);
        return $this->success('CLIENT_SERVICE_MESSAGES_LIST', 'Messages listed', ['items' => $messages]);
    }

    public function addMessage(array $params): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }

        $user = (array)$auth['user'];
        $orgId = $this->activeOrganizationId($user);
        if ($orgId === null) {
            return $this->error('FORBIDDEN', 'An active workspace is required', 403);
        }
        $isExternalClient = $this->isExternalClient($user);
        if ($isExternalClient) {
            $clientPublicId = $this->resolveClientPublicId($user, $orgId);
            if ($clientPublicId === null) {
                return $this->error('FORBIDDEN', 'No client account is linked to this user in the active workspace', 403);
            }
        } elseif (!$this->canManageClientRequests($user)) {
            return $this->error('FORBIDDEN', 'Insufficient permissions to post client service messages', 403);
        } else {
            $clientPublicId = null;
        }

        $requestPublicId = trim((string)($params['public_id'] ?? ''));
        $service = $this->getService();
        if ($service->getRequest($orgId, $requestPublicId, $clientPublicId) === null) {
            return $this->error('NOT_FOUND', 'Request not found or access denied', 404);
        }
        $input = $this->request()->allInput();
        if (!is_scalar($input['message_text'] ?? null)) {
            return $this->error('VALIDATION_ERROR', 'Message must be plain text', 422);
        }
        $message = trim((string)$input['message_text']);
        if ($message === '' || strlen($message) > 10000) {
            return $this->error('VALIDATION_ERROR', 'Message must contain 1 to 10000 bytes', 422);
        }
        $visible = true;
        if (!$isExternalClient && array_key_exists('is_client_visible', $input)) {
            $visible = in_array($input['is_client_visible'], [true, 1, '1', 'true'], true);
        }
        $messagePublicId = $service->postMessage(
            $orgId,
            $requestPublicId,
            $isExternalClient ? 'client' : 'staff',
            (string)($user['public_id'] ?? ''),
            $message,
            $visible
        );

        return $this->success('CLIENT_SERVICE_MESSAGE_CREATED', 'Message created', ['public_id' => $messagePublicId]);
    }

    public function approveScope(array $params): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }

        $user = (array)$auth['user'];
        $orgId = $this->activeOrganizationId($user);
        if ($orgId === null) {
            return $this->error('FORBIDDEN', 'An active workspace is required', 403);
        }
        $isExternalClient = $this->isExternalClient($user);
        if ($isExternalClient) {
            $clientPublicId = $this->resolveClientPublicId($user, $orgId);
            if ($clientPublicId === null) {
                return $this->error('FORBIDDEN', 'No client account is linked to this user in the active workspace', 403);
            }
        } elseif (!$this->canManageClientRequests($user)) {
            return $this->error('FORBIDDEN', 'Insufficient permissions to resolve client approvals', 403);
        } else {
            $clientPublicId = $this->resolveRequestedClientPublicId($orgId);
            if ($clientPublicId === null || $clientPublicId === false) {
                return $this->error('VALIDATION_ERROR', 'A client in the active workspace is required', 422);
            }
        }

        $requestPublicId = trim((string)($params['public_id'] ?? ''));
        $input = $this->request()->allInput();
        $approvalPublicId = is_scalar($input['approval_public_id'] ?? null) ? trim((string)$input['approval_public_id']) : '';
        $decision = is_scalar($input['decision'] ?? null) ? trim((string)$input['decision']) : '';
        $note = is_scalar($input['note'] ?? null) ? trim((string)$input['note']) : '';
        if ($approvalPublicId === '' || !in_array($decision, ['approved', 'rejected'], true) || strlen($note) > 10000) {
            return $this->error('VALIDATION_ERROR', 'Approval decision is invalid', 422);
        }

        $stmt = $this->container->get('db.pdo')->prepare(
            'SELECT public_id FROM crm_client_service_approvals
             WHERE public_id = :approval_id AND request_public_id = :request_id AND organization_id = :organization_id
             LIMIT 1'
        );
        $stmt->execute([
            ':approval_id' => $approvalPublicId,
            ':request_id' => $requestPublicId,
            ':organization_id' => $orgId,
        ]);
        if ($stmt->fetchColumn() === false) {
            return $this->error('NOT_FOUND', 'Approval not found or access denied', 404);
        }

        $resolved = $this->getService()->resolveApproval(
            $orgId,
            $approvalPublicId,
            $clientPublicId,
            $decision,
            $note !== '' ? $note : null
        );
        if (!$resolved) {
            return $this->error('CONFLICT', 'Approval has already been resolved', 409);
        }

        return $this->success('CLIENT_SERVICE_APPROVAL_RESOLVED', 'Approval resolved', [
            'approval_public_id' => $approvalPublicId,
            'decision' => $decision,
        ]);
    }

    private function activeOrganizationId(array $user): ?int
    {
        $organizationId = (int)($user['organization_id'] ?? 0);
        return $organizationId > 0 ? $organizationId : null;
    }

    private function isExternalClient(array $user): bool
    {
        return (bool)($user['is_external'] ?? false) && !(bool)($user['is_root'] ?? false);
    }

    private function canManageClientRequests(array $user): bool
    {
        if ((bool)($user['is_root'] ?? false)) {
            return true;
        }

        if (!$this->container->has('service.authz')) {
            return false;
        }

        return $this->container->get('service.authz')->hasPermissions($user, ['client.manage']);
    }

    private function resolveClientPublicId(array $user, int $organizationId): ?string
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0 || !$this->isExternalClient($user)) {
            return null;
        }

        $stmt = $this->container->get('db.pdo')->prepare(
            "SELECT DISTINCT cp.public_id
             FROM contacts c
             INNER JOIN counterparties cp ON cp.id = c.counterparty_id
             WHERE c.user_id = :user_id
               AND c.organization_id = :contact_org_id
               AND cp.organization_id = :counterparty_org_id
               AND cp.counterparty_type IN ('individual', 'sole_proprietor', 'legal_entity')
               AND cp.status = 'active'
             ORDER BY cp.public_id ASC
             LIMIT 2"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':contact_org_id' => $organizationId,
            ':counterparty_org_id' => $organizationId,
        ]);
        $clientIds = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        return count($clientIds) === 1 ? (string)$clientIds[0] : null;
    }

    /** @return string|false|null false means the supplied client is not in this workspace */
    private function resolveRequestedClientPublicId(int $organizationId): string|false|null
    {
        $clientPublicId = trim((string)$this->request()->input('client_public_id', ''));
        if ($clientPublicId === '') {
            return null;
        }

        $stmt = $this->container->get('db.pdo')->prepare(
            "SELECT public_id
             FROM counterparties
             WHERE public_id = :public_id
               AND organization_id = :organization_id
               AND counterparty_type IN ('individual', 'sole_proprietor', 'legal_entity')
               AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute([
            ':public_id' => $clientPublicId,
            ':organization_id' => $organizationId,
        ]);
        $resolved = $stmt->fetchColumn();

        return $resolved === false ? false : (string)$resolved;
    }
}
