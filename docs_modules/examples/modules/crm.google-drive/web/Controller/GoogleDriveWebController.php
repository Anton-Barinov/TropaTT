<?php
declare(strict_types=1);

namespace Module\Crm\GoogleDrive\Web\Controller;

use Web\System\Core\Controller;

final class GoogleDriveWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_google_drive.php', [
            'title' => 'Google Диск',
            'route' => 'module-google-drive',
        ]);
    }
}
