<?php
declare(strict_types=1);

namespace Module\Crm\CorporateMail\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\CorporateMail\Service\CorporateMailService;

final class CorporateMailApiController extends BaseController
{
    private function getService(): CorporateMailService
    {
        return new CorporateMailService($this->container->get('db.pdo'));
    }

    public function listMailboxes(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $orgId = (int)($auth['user']['organization_id'] ?? 1);
        $service = $this->getService();
        $items = $service->listMailboxes($orgId);

        return $this->success('MAILBOXES_LIST', 'Mailboxes retrieved', ['items' => $items]);
    }
}
