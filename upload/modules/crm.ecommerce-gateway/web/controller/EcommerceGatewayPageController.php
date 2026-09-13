<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Controller;

use Web\System\Core\Controller;

final class EcommerceGatewayPageController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/ecommerce_gateway.php', [
            'title' => 'Шлюз интернет-магазинов',
            'route' => 'module-ecommerce-gateway',
        ]);
    }
}
