<?php
declare(strict_types=1);

use Module\Crm\EcommerceGateway\Controller\IngestController;
use Module\Crm\EcommerceGateway\Controller\StoreAdminController;

// The router mounts module routes as /_module/{module}/{route} without a version
// segment, so the protocol version is part of every route below: the documented
// contract is /_module/crm.ecommerce-gateway/v1/... (E-COM-01 §4, README).
return [
    // Ingestion (public transport, HMAC-verified inside the controller)
    ['methods' => ['GET'], 'route' => '/v1/ping', 'controller' => IngestController::class, 'action' => 'ping', 'auth' => false],
    ['methods' => ['POST'], 'route' => '/v1/orders', 'controller' => IngestController::class, 'action' => 'orders', 'auth' => false],
    ['methods' => ['POST'], 'route' => '/v1/quick-orders', 'controller' => IngestController::class, 'action' => 'quickOrders', 'auth' => false],
    ['methods' => ['POST'], 'route' => '/v1/callbacks', 'controller' => IngestController::class, 'action' => 'callbacks', 'auth' => false],
    ['methods' => ['POST'], 'route' => '/v1/feedback', 'controller' => IngestController::class, 'action' => 'feedback', 'auth' => false],
    ['methods' => ['POST'], 'route' => '/v1/forms', 'controller' => IngestController::class, 'action' => 'forms', 'auth' => false],

    // Stores (admin / manager UI)
    ['methods' => ['GET'], 'route' => '/v1/stores', 'controller' => StoreAdminController::class, 'action' => 'listStores', 'auth' => true, 'required_permissions' => ['module.ecommerce-gateway.view']],
    ['methods' => ['POST'], 'route' => '/v1/stores', 'controller' => StoreAdminController::class, 'action' => 'createStore', 'auth' => true, 'required_permissions' => ['module.ecommerce-gateway.manage', 'module.ecommerce-gateway.secret_manage']],
    ['methods' => ['GET'], 'route' => '/v1/stores/{public_id}', 'controller' => StoreAdminController::class, 'action' => 'getStore', 'auth' => true, 'required_permissions' => ['module.ecommerce-gateway.view']],
    ['methods' => ['PATCH'], 'route' => '/v1/stores/{public_id}', 'controller' => StoreAdminController::class, 'action' => 'updateStore', 'auth' => true, 'required_permissions' => ['module.ecommerce-gateway.manage']],
    ['methods' => ['DELETE'], 'route' => '/v1/stores/{public_id}', 'controller' => StoreAdminController::class, 'action' => 'deleteStore', 'auth' => true, 'required_permissions' => ['module.ecommerce-gateway.manage']],
    ['methods' => ['POST'], 'route' => '/v1/stores/{public_id}/rotate-secret', 'controller' => StoreAdminController::class, 'action' => 'rotateSecret', 'auth' => true, 'required_permissions' => ['module.ecommerce-gateway.manage', 'module.ecommerce-gateway.secret_manage']],
];
