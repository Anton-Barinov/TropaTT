<?php

declare(strict_types=1);

namespace Module\Crm\MercadoPago\Controller;

class MercadoPagoApiController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function getStatus(): array
    {
        return [
            'status' => 'active',
            'gateway' => 'Mercado Pago Brazil',
            'supported_methods' => ['pix', 'boleto', 'credit_card']
        ];
    }
}
