<?php
declare(strict_types=1);

namespace Module\Crm\Zoom\Web\Controller;

use Web\System\Core\Controller;

final class ZoomWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_zoom.php', [
            'title' => 'Zoom Видеоконференции',
            'route' => 'module-zoom',
        ]);
    }
}
