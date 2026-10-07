<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Web\Controller;

use Web\System\Core\Controller;

final class StarterKitsWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_starter_kits.php', [
            'title' => 'Стартовые наборы отраслей',
            'route' => 'module-starter-kits',
        ]);
    }
}
