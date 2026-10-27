<?php
declare(strict_types=1);

use Module\Crm\ClickUpMigration\Controller\ClickUpMigrationController;

return [
 ['methods'=>['POST'],'route'=>'/oauth/authorize-url','controller'=>ClickUpMigrationController::class,'action'=>'oauthAuthorizeUrl','auth'=>true,'required_permissions'=>['module.clickup-migration.manage','module.clickup-migration.secret_manage']],
 ['methods'=>['POST'],'route'=>'/oauth/exchange','controller'=>ClickUpMigrationController::class,'action'=>'exchangeOAuth','auth'=>true,'required_permissions'=>['module.clickup-migration.manage','module.clickup-migration.secret_manage']],
 ['methods'=>['GET'],'route'=>'/connections','controller'=>ClickUpMigrationController::class,'action'=>'listConnections','auth'=>true,'required_permissions'=>['module.clickup-migration.view']],
 ['methods'=>['POST'],'route'=>'/connections','controller'=>ClickUpMigrationController::class,'action'=>'createConnection','auth'=>true,'required_permissions'=>['module.clickup-migration.manage','module.clickup-migration.secret_manage']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}','controller'=>ClickUpMigrationController::class,'action'=>'getConnection','auth'=>true,'required_permissions'=>['module.clickup-migration.view']],
 ['methods'=>['PATCH'],'route'=>'/connections/{public_id}','controller'=>ClickUpMigrationController::class,'action'=>'updateConnection','auth'=>true,'required_permissions'=>['module.clickup-migration.manage','module.clickup-migration.secret_manage']],
 ['methods'=>['DELETE'],'route'=>'/connections/{public_id}','controller'=>ClickUpMigrationController::class,'action'=>'deleteConnection','auth'=>true,'required_permissions'=>['module.clickup-migration.delete']],
 ['methods'=>['POST'],'route'=>'/connections/{public_id}/test','controller'=>ClickUpMigrationController::class,'action'=>'testConnection','auth'=>true,'required_permissions'=>['module.clickup-migration.manage']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}/projects','controller'=>ClickUpMigrationController::class,'action'=>'discover','auth'=>true,'required_permissions'=>['module.clickup-migration.view']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}/user-mappings','controller'=>ClickUpMigrationController::class,'action'=>'listUserMappings','auth'=>true,'required_permissions'=>['module.clickup-migration.view']],
 ['methods'=>['PATCH'],'route'=>'/connections/{public_id}/user-mappings/{mapping_id}','controller'=>ClickUpMigrationController::class,'action'=>'updateUserMapping','auth'=>true,'required_permissions'=>['module.clickup-migration.manage']],
 ['methods'=>['GET'],'route'=>'/jobs','controller'=>ClickUpMigrationController::class,'action'=>'listJobs','auth'=>true,'required_permissions'=>['module.clickup-migration.view']],
 ['methods'=>['POST'],'route'=>'/jobs','controller'=>ClickUpMigrationController::class,'action'=>'createJob','auth'=>true,'required_permissions'=>['module.clickup-migration.run','project.manage','task.manage','import.manage']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}','controller'=>ClickUpMigrationController::class,'action'=>'getJob','auth'=>true,'required_permissions'=>['module.clickup-migration.view']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/run','controller'=>ClickUpMigrationController::class,'action'=>'startJob','auth'=>true,'required_permissions'=>['module.clickup-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/pause','controller'=>ClickUpMigrationController::class,'action'=>'pauseJob','auth'=>true,'required_permissions'=>['module.clickup-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/resume','controller'=>ClickUpMigrationController::class,'action'=>'resumeJob','auth'=>true,'required_permissions'=>['module.clickup-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/cancel','controller'=>ClickUpMigrationController::class,'action'=>'cancelJob','auth'=>true,'required_permissions'=>['module.clickup-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/retry-failed','controller'=>ClickUpMigrationController::class,'action'=>'retryFailed','auth'=>true,'required_permissions'=>['module.clickup-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/rollback','controller'=>ClickUpMigrationController::class,'action'=>'rollbackJob','auth'=>true,'required_permissions'=>['module.clickup-migration.delete']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/items','controller'=>ClickUpMigrationController::class,'action'=>'listJobItems','auth'=>true,'required_permissions'=>['module.clickup-migration.view']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/logs','controller'=>ClickUpMigrationController::class,'action'=>'listJobLogs','auth'=>true,'required_permissions'=>['module.clickup-migration.view']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/report','controller'=>ClickUpMigrationController::class,'action'=>'getReport','auth'=>true,'required_permissions'=>['module.clickup-migration.report_view']],
];
