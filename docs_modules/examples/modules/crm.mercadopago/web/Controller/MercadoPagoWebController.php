<?php

declare(strict_types=1);

namespace Module\Crm\MercadoPago\Web;

class MercadoPagoWebController
{
    public function index(): string
    {
        return '<div class="crm-mercadopago-wrap"><h2>Mercado Pago Payments</h2><p>Payment links & reconciliation dashboard</p></div>';
    }
}
