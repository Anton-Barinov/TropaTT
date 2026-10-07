<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Web\Controller;

use Web\System\Core\Controller;

final class StorageConnectorsWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_storage_connectors.php', [
            'title' => 'Облачные хранилища',
            'route' => 'module-storage-connectors',
        ]);
    }
}
