<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Web\Controller;

use Web\Controller\BaseController;

final class StarterKitsWebController extends BaseController
{
    public function index(): string
    {
        $this->requirePermission('settings.view');
        return $this->render(__DIR__ . '/../template/page/starter_kits.php', [
            'page_title' => 'Стартовые комплекты',
        ]);
    }
}
