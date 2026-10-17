<?php
declare(strict_types=1);

namespace Module\Crm\WhatsAppBusiness\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\WhatsAppBusiness\Service\WhatsAppBusinessService;
use Throwable;

final class WhatsAppBusinessApiController extends BaseController
{
    private function getService(): WhatsAppBusinessService
    {
        return new WhatsAppBusinessService($this->container->get('db.pdo'));
    }

    public function handleWebhook(array $params): JsonResponse
    {
        $orgId = (int)($params['org_id'] ?? 0);
        if ($orgId <= 0) {
            return $this->error('VALIDATION_ERROR', 'Invalid workspace identifier', 400);
        }
        $request = $this->request();
        $rawBody = $request->rawBody;
        $contentLength = (int)($request->server('CONTENT_LENGTH') ?? 0);
        if ($contentLength > 262144 || strlen($rawBody) > 262144) {
            return $this->error('PAYLOAD_TOO_LARGE', 'Webhook payload exceeds the 256 KB limit.', 413);
        }
        $contentType = strtolower(trim(explode(';', (string)($request->header('Content-Type') ?? ''))[0]));
        if ($contentType !== 'application/json') {
            return $this->error('UNSUPPORTED_MEDIA_TYPE', 'Webhook payload must use application/json.', 415);
        }
        $signature = trim((string)($request->header('X-Hub-Signature-256') ?? ''));
        $payload = $this->request()->allInput();

        $service = $this->getService();
        try {
            $res = $service->handleWebhook($orgId, $payload, $rawBody, $signature !== '' ? $signature : null);
            return $this->success('WHATSAPP_WEBHOOK_PROCESSED', 'Webhook handled', $res);
        } catch (Throwable $e) {
            return $this->error('WHATSAPP_ERROR', $e->getMessage(), 400);
        }
    }

    public function listInbox(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }
        $user = (array)($auth['user'] ?? []);
        $orgId = (int)($user['organization_id'] ?? 0);
        if ($orgId <= 0) {
            return $this->error('FORBIDDEN', 'An active workspace is required', 403);
        }
        if (empty($user['is_root'])) {
            if (!$this->container->has('service.authz')
                || !$this->container->get('service.authz')->hasPermissions($user, ['settings.view'])) {
                return $this->error('FORBIDDEN', 'Insufficient permissions to view WhatsApp conversations', 403);
            }
        }

        $service = $this->getService();
        $items = $service->listConversations($orgId);

        return $this->success('WHATSAPP_INBOX_LIST', 'Inbox listed', ['items' => $items]);
    }
}
