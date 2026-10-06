<?php
declare(strict_types=1);

namespace Module\Crm\ClientPortal\Web\Controller;

use Api\System\Library\Database\DatabaseConnectionPool;
use Module\Crm\ClientPortal\Service\ClientPortalService;

final class ClientPortalWebController
{
    public function index(): void
    {
        $orgId = (int)($_SESSION['user']['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();
        $service = new ClientPortalService($db);
        $requests = $service->listRequests($orgId);

        include __DIR__ . '/../template/page/client_portal.php';
    }
}
