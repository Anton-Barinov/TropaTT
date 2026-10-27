<?php
declare(strict_types=1);

use Module\Crm\PublicForms\Api\Controller\PublicFormsApiController;

return [
    ['methods' => ['GET'], 'route' => '/forms/{slug}/slots', 'controller' => PublicFormsApiController::class, 'action' => 'getBookingSlots', 'auth' => false],
    ['methods' => ['GET'], 'route' => '/manage/calendar-users', 'controller' => PublicFormsApiController::class, 'action' => 'adminListCalendarUsers', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/manage/routing-options', 'controller' => PublicFormsApiController::class, 'action' => 'adminListRoutingOptions', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['GET'], 'route' => '/manage/forms', 'controller' => PublicFormsApiController::class, 'action' => 'adminListForms', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/manage/forms', 'controller' => PublicFormsApiController::class, 'action' => 'adminSaveForm', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['GET'], 'route' => '/manage/forms/{public_id}', 'controller' => PublicFormsApiController::class, 'action' => 'adminGetForm', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['PATCH'], 'route' => '/manage/forms/{public_id}', 'controller' => PublicFormsApiController::class, 'action' => 'adminSaveForm', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/manage/forms/{public_id}/publish', 'controller' => PublicFormsApiController::class, 'action' => 'adminSetPublished', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['GET'], 'route' => '/manage/submissions', 'controller' => PublicFormsApiController::class, 'action' => 'adminListSubmissions', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['client.manage']],
    ['methods' => ['GET'], 'route' => '/forms/{slug}', 'controller' => PublicFormsApiController::class, 'action' => 'getForm', 'auth' => false],
    ['methods' => ['POST'], 'route' => '/forms/{slug}/submit', 'controller' => PublicFormsApiController::class, 'action' => 'submitForm', 'auth' => false],
];
