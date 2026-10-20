<?php
declare(strict_types=1);

use Module\Crm\CorporateMail\Api\Controller\CorporateMailApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => CorporateMailApiController::class, 'action' => 'getStatus', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/mailboxes', 'controller' => CorporateMailApiController::class, 'action' => 'listMailboxes', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/mailboxes/{public_id}', 'controller' => CorporateMailApiController::class, 'action' => 'getMailbox', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/mailboxes', 'controller' => CorporateMailApiController::class, 'action' => 'saveMailbox', 'auth' => true],
    ['methods' => ['DELETE'], 'route' => '/mailboxes/{public_id}', 'controller' => CorporateMailApiController::class, 'action' => 'deleteMailbox', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/mailboxes/{public_id}/test', 'controller' => CorporateMailApiController::class, 'action' => 'testMailbox', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/mailboxes/{public_id}/sync', 'controller' => CorporateMailApiController::class, 'action' => 'syncMailbox', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/test-connection', 'controller' => CorporateMailApiController::class, 'action' => 'testConnection', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/import-env', 'controller' => CorporateMailApiController::class, 'action' => 'importEnv', 'auth' => true],
];
