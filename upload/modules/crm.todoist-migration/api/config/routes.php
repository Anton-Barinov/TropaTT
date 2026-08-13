<?php
declare(strict_types=1);

use Module\Crm\TodoistMigration\Controller\TodoistMigrationController;

return [
 ['methods'=>['POST'],'route'=>'/oauth/authorize-url','controller'=>TodoistMigrationController::class,'action'=>'oauthAuthorizeUrl','auth'=>true,'required_permissions'=>['module.todoist-migration.manage','module.todoist-migration.secret_manage']],
 ['methods'=>['POST'],'route'=>'/oauth/exchange','controller'=>TodoistMigrationController::class,'action'=>'exchangeOAuth','auth'=>true,'required_permissions'=>['module.todoist-migration.manage','module.todoist-migration.secret_manage']],
 ['methods'=>['GET'],'route'=>'/connections','controller'=>TodoistMigrationController::class,'action'=>'listConnections','auth'=>true,'required_permissions'=>['module.todoist-migration.view']],
 ['methods'=>['POST'],'route'=>'/connections','controller'=>TodoistMigrationController::class,'action'=>'createConnection','auth'=>true,'required_permissions'=>['module.todoist-migration.manage','module.todoist-migration.secret_manage']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}','controller'=>TodoistMigrationController::class,'action'=>'getConnection','auth'=>true,'required_permissions'=>['module.todoist-migration.view']],
 ['methods'=>['PATCH'],'route'=>'/connections/{public_id}','controller'=>TodoistMigrationController::class,'action'=>'updateConnection','auth'=>true,'required_permissions'=>['module.todoist-migration.manage','module.todoist-migration.secret_manage']],
 ['methods'=>['DELETE'],'route'=>'/connections/{public_id}','controller'=>TodoistMigrationController::class,'action'=>'deleteConnection','auth'=>true,'required_permissions'=>['module.todoist-migration.delete']],
 ['methods'=>['POST'],'route'=>'/connections/{public_id}/test','controller'=>TodoistMigrationController::class,'action'=>'testConnection','auth'=>true,'required_permissions'=>['module.todoist-migration.manage']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}/projects','controller'=>TodoistMigrationController::class,'action'=>'discover','auth'=>true,'required_permissions'=>['module.todoist-migration.view']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}/user-mappings','controller'=>TodoistMigrationController::class,'action'=>'listUserMappings','auth'=>true,'required_permissions'=>['module.todoist-migration.view']],
 ['methods'=>['PATCH'],'route'=>'/connections/{public_id}/user-mappings/{mapping_id}','controller'=>TodoistMigrationController::class,'action'=>'updateUserMapping','auth'=>true,'required_permissions'=>['module.todoist-migration.manage']],
 ['methods'=>['GET'],'route'=>'/jobs','controller'=>TodoistMigrationController::class,'action'=>'listJobs','auth'=>true,'required_permissions'=>['module.todoist-migration.view']],
 ['methods'=>['POST'],'route'=>'/jobs','controller'=>TodoistMigrationController::class,'action'=>'createJob','auth'=>true,'required_permissions'=>['module.todoist-migration.run','project.manage','task.manage','import.manage']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}','controller'=>TodoistMigrationController::class,'action'=>'getJob','auth'=>true,'required_permissions'=>['module.todoist-migration.view']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/run','controller'=>TodoistMigrationController::class,'action'=>'startJob','auth'=>true,'required_permissions'=>['module.todoist-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/pause','controller'=>TodoistMigrationController::class,'action'=>'pauseJob','auth'=>true,'required_permissions'=>['module.todoist-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/resume','controller'=>TodoistMigrationController::class,'action'=>'resumeJob','auth'=>true,'required_permissions'=>['module.todoist-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/cancel','controller'=>TodoistMigrationController::class,'action'=>'cancelJob','auth'=>true,'required_permissions'=>['module.todoist-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/retry-failed','controller'=>TodoistMigrationController::class,'action'=>'retryFailed','auth'=>true,'required_permissions'=>['module.todoist-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/rollback','controller'=>TodoistMigrationController::class,'action'=>'rollbackJob','auth'=>true,'required_permissions'=>['module.todoist-migration.delete']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/items','controller'=>TodoistMigrationController::class,'action'=>'listJobItems','auth'=>true,'required_permissions'=>['module.todoist-migration.view']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/logs','controller'=>TodoistMigrationController::class,'action'=>'listJobLogs','auth'=>true,'required_permissions'=>['module.todoist-migration.view']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/report','controller'=>TodoistMigrationController::class,'action'=>'getReport','auth'=>true,'required_permissions'=>['module.todoist-migration.report_view']],
];
