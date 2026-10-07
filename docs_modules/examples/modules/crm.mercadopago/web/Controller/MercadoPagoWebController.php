<?php
declare(strict_types=1);

namespace Module\Crm\Mercadopago\Web\Controller;

use Web\System\Core\Controller;

final class MercadoPagoWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_mercadopago.php', [
            'title' => 'Mercado Pago Платежи',
            'route' => 'module-mercadopago',
        ]);
    }
}
