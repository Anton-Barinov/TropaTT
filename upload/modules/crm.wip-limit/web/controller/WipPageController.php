<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Controller;

use Web\System\Core\Controller;

final class WipPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/module_wip_limit.php', [
            'title' => 'WIP-лимиты',
            'route' => 'module-wip-limit',
        ]);
    }
}
