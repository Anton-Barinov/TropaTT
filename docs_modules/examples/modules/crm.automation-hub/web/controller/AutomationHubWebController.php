<?php
declare(strict_types=1);

namespace Module\Crm\AutomationHub\Web\Controller;

use Api\System\Library\Database\DatabaseConnectionPool;
use Module\Crm\AutomationHub\Service\AutomationHubService;

final class AutomationHubWebController
{
    public function index(): void
    {
        $orgId = (int)($_SESSION['user']['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();
        $service = new AutomationHubService($db);
        $recipes = $service->listRecipes($orgId);

        include __DIR__ . '/../template/page/automation_hub.php';
    }
}
