<?php
declare(strict_types=1);

namespace Module\Crm\SlackIntegration\Controller;

use Web\System\Core\Controller;

final class SlackPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/slack.php', [
            'title' => 'Уведомления в Slack',
            'route' => 'module-slack-integration',
        ]);
    }
}
