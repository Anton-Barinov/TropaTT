<?php
declare(strict_types=1);

namespace Module\Crm\WhatsAppBusiness\Web\Controller;

use Api\System\Library\Database\DatabaseConnectionPool;
use Module\Crm\WhatsAppBusiness\Service\WhatsAppBusinessService;

final class WhatsAppBusinessWebController
{
    public function index(): void
    {
        $orgId = (int)($_SESSION['user']['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();
        $service = new WhatsAppBusinessService($db);
        $conversations = $service->listConversations($orgId);

        include __DIR__ . '/../template/page/whatsapp_inbox.php';
    }
}
