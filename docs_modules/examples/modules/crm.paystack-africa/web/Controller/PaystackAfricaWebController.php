<?php
declare(strict_types=1);

namespace Module\Crm\PaystackAfrica\Web\Controller;

use Web\System\Core\Controller;

final class PaystackAfricaWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_paystack_africa.php', [
            'title' => 'Paystack Платежи',
            'route' => 'module-paystack-africa',
        ]);
    }
}
