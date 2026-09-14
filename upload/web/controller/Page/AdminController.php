<?php
declare(strict_types=1);

namespace Web\Controller\Page;

use Web\System\Core\Controller;

final class AdminController extends Controller
{
    public function index(): void
    {
        // Модули в поставку больше не входят (ставятся из маркетплейса), поэтому
        // карточку шлюза витрин показываем только когда модуль реально установлен —
        // иначе ссылка на страницу модуля вела бы в никуда на чистой установке.
        $modulesDir = dirname($this->baseDir) . '/modules';

        $this->render('page/admin', [
            'title' => 'Администрирование',
            'route' => 'admin',
            'ecommerce_gateway_installed' => is_dir($modulesDir . '/crm.ecommerce-gateway'),
        ]);
    }
}
