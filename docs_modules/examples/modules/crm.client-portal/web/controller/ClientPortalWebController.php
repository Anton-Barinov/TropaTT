<?php
declare(strict_types=1);

namespace Module\Crm\ClientPortal\Web\Controller;

use Web\System\Core\Controller;

final class ClientPortalWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_client_portal.php', [
            'title' => 'Клиентский портал',
            'route' => 'module-client-portal',
        ]);
    }
}
