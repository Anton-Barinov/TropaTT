<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Web\Controller;

use Web\Controller\BaseController;

final class StorageConnectorsWebController extends BaseController
{
    public function index(): string
    {
        $this->requirePermission('settings.view');
        return $this->render(__DIR__ . '/../template/page/storage_connectors.php', [
            'page_title' => 'Внешние хранилища файлов',
        ]);
    }
}
