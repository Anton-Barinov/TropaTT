<?php
declare(strict_types=1);

namespace Module\Crm\CorporateMail\Web\Controller;

use Api\System\Library\Database\DatabaseConnectionPool;
use Module\Crm\CorporateMail\Service\CorporateMailService;

final class CorporateMailWebController
{
    public function index(): void
    {
        $orgId = (int)($_SESSION['user']['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();
        $service = new CorporateMailService($db);
        $mailboxes = $service->listMailboxes($orgId);

        include __DIR__ . '/../template/page/mailboxes_settings.php';
    }
}
