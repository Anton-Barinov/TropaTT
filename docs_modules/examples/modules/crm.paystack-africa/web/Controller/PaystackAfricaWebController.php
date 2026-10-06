<?php

declare(strict_types=1);

namespace Module\Crm\PaystackAfrica\Web;

class PaystackAfricaWebController
{
    public function index(): string
    {
        return '<div class="crm-paystack-wrap"><h2>Paystack Africa Payments</h2><p>Payment links & settlements console</p></div>';
    }
}
