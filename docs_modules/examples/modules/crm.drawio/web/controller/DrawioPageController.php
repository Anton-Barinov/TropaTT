<?php
declare(strict_types=1);

namespace Module\Crm\Drawio\Controller;

use Web\System\Core\Controller;

final class DrawioPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/drawio.php', [
            'title' => 'Диаграммы draw.io',
            'route' => 'module-drawio',
        ]);
    }
}
