<?php
declare(strict_types=1);

use Module\Crm\CorporateMail\Api\Controller\CorporateMailApiController;

return [
    'GET' => [
        'api/v1/mail/mailboxes' => [CorporateMailApiController::class, 'listMailboxes'],
    ],
];
