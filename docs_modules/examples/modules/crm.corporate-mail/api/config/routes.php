<?php
declare(strict_types=1);

use Module\Crm\CorporateMail\Api\Controller\CorporateMailApiController;

return [
    ['methods' => ['GET'], 'route' => '/mailboxes', 'controller' => CorporateMailApiController::class, 'action' => 'listMailboxes', 'auth' => true],
];
