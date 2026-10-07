<?php
declare(strict_types=1);

use Module\Crm\CorporateMail\Api\Controller\CorporateMailApiController;

return [
    ['methods' => ['GET'], 'route' => '/mailboxes', 'controller' => CorporateMailApiController::class, 'action' => 'listMailboxes', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/mailboxes', 'controller' => CorporateMailApiController::class, 'action' => 'createMailbox', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/mailboxes/{id}/test', 'controller' => CorporateMailApiController::class, 'action' => 'testConnection', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/send', 'controller' => CorporateMailApiController::class, 'action' => 'sendMail', 'auth' => true],
];
