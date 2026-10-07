<?php
declare(strict_types=1);

namespace Module\Crm\YandexDisk\Web\Controller;

use Web\System\Core\Controller;

final class YandexDiskWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_yandex_disk.php', [
            'title' => 'Яндекс.Диск',
            'route' => 'module-yandex-disk',
        ]);
    }
}
