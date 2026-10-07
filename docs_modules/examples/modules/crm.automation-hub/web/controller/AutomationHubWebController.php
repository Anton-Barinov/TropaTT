<?php
declare(strict_types=1);

namespace Module\Crm\AutomationHub\Web\Controller;

use Web\System\Core\Controller;

final class AutomationHubWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_automation_hub.php', [
            'title' => 'Автоматизация и интеграции',
            'route' => 'module-automation-hub',
        ]);
    }
}
