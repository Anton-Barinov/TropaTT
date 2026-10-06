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
        $orgId = (int)($params['org_id'] ?? 1);
        $rawBody = (string)$this->request()->body();
        $signature = (string)($this->request()->header('X-Hub-Signature-256') ?? '');
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

        $orgId = (int)($auth['user']['organization_id'] ?? 1);
        $service = $this->getService();
        $items = $service->listConversations($orgId);

        return $this->success('WHATSAPP_INBOX_LIST', 'Inbox listed', ['items' => $items]);
    }
}
