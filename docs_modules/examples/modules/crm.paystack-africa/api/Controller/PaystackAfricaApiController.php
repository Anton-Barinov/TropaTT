<?php

declare(strict_types=1);

namespace Module\Crm\PaystackAfrica\Controller;

class PaystackAfricaApiController
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
            'gateway' => 'Paystack Africa',
            'supported_countries' => ['Nigeria', 'Ghana', 'South Africa', 'Kenya'],
            'supported_currencies' => ['NGN', 'GHS', 'ZAR', 'KES']
        ];
    }
}
