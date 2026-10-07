<?php
declare(strict_types=1);

namespace Module\Crm\Dropbox\Web\Controller;

use Web\System\Core\Controller;

final class DropboxWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_dropbox.php', [
            'title' => 'Dropbox Хранилище',
            'route' => 'module-dropbox',
        ]);
    }
}
