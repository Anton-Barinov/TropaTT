<?php
declare(strict_types=1);

use Module\Crm\KaitenMigration\Controller\KaitenMigrationController;

return [
 ['methods'=>['GET'],'route'=>'/connections','controller'=>KaitenMigrationController::class,'action'=>'listConnections','auth'=>true,'required_permissions'=>['module.kaiten-migration.view']],
 ['methods'=>['POST'],'route'=>'/connections','controller'=>KaitenMigrationController::class,'action'=>'createConnection','auth'=>true,'required_permissions'=>['module.kaiten-migration.manage','module.kaiten-migration.secret_manage']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}','controller'=>KaitenMigrationController::class,'action'=>'getConnection','auth'=>true,'required_permissions'=>['module.kaiten-migration.view']],
 ['methods'=>['PATCH'],'route'=>'/connections/{public_id}','controller'=>KaitenMigrationController::class,'action'=>'updateConnection','auth'=>true,'required_permissions'=>['module.kaiten-migration.manage','module.kaiten-migration.secret_manage']],
 ['methods'=>['DELETE'],'route'=>'/connections/{public_id}','controller'=>KaitenMigrationController::class,'action'=>'deleteConnection','auth'=>true,'required_permissions'=>['module.kaiten-migration.delete']],
 ['methods'=>['POST'],'route'=>'/connections/{public_id}/test','controller'=>KaitenMigrationController::class,'action'=>'testConnection','auth'=>true,'required_permissions'=>['module.kaiten-migration.manage']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}/spaces','controller'=>KaitenMigrationController::class,'action'=>'listSpaces','auth'=>true,'required_permissions'=>['module.kaiten-migration.view']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}/workspaces','controller'=>KaitenMigrationController::class,'action'=>'listWorkspaces','auth'=>true,'required_permissions'=>['module.kaiten-migration.view']],
 ['methods'=>['POST'],'route'=>'/connections/{public_id}/discover','controller'=>KaitenMigrationController::class,'action'=>'discover','auth'=>true,'required_permissions'=>['module.kaiten-migration.run']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}/user-mappings','controller'=>KaitenMigrationController::class,'action'=>'listUserMappings','auth'=>true,'required_permissions'=>['module.kaiten-migration.view']],
 ['methods'=>['PATCH'],'route'=>'/connections/{public_id}/user-mappings/{mapping_id}','controller'=>KaitenMigrationController::class,'action'=>'updateUserMapping','auth'=>true,'required_permissions'=>['module.kaiten-migration.manage']],
 ['methods'=>['GET'],'route'=>'/jobs','controller'=>KaitenMigrationController::class,'action'=>'listJobs','auth'=>true,'required_permissions'=>['module.kaiten-migration.view']],
 ['methods'=>['POST'],'route'=>'/jobs','controller'=>KaitenMigrationController::class,'action'=>'createJob','auth'=>true,'required_permissions'=>['module.kaiten-migration.run','project.manage','task.manage','import.manage']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}','controller'=>KaitenMigrationController::class,'action'=>'getJob','auth'=>true,'required_permissions'=>['module.kaiten-migration.view']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/run','controller'=>KaitenMigrationController::class,'action'=>'startJob','auth'=>true,'required_permissions'=>['module.kaiten-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/pause','controller'=>KaitenMigrationController::class,'action'=>'pauseJob','auth'=>true,'required_permissions'=>['module.kaiten-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/resume','controller'=>KaitenMigrationController::class,'action'=>'resumeJob','auth'=>true,'required_permissions'=>['module.kaiten-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/cancel','controller'=>KaitenMigrationController::class,'action'=>'cancelJob','auth'=>true,'required_permissions'=>['module.kaiten-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/retry-failed','controller'=>KaitenMigrationController::class,'action'=>'retryFailed','auth'=>true,'required_permissions'=>['module.kaiten-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/rollback','controller'=>KaitenMigrationController::class,'action'=>'rollbackJob','auth'=>true,'required_permissions'=>['module.kaiten-migration.delete']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/items','controller'=>KaitenMigrationController::class,'action'=>'listJobItems','auth'=>true,'required_permissions'=>['module.kaiten-migration.view']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/logs','controller'=>KaitenMigrationController::class,'action'=>'listJobLogs','auth'=>true,'required_permissions'=>['module.kaiten-migration.view']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/report','controller'=>KaitenMigrationController::class,'action'=>'getReport','auth'=>true,'required_permissions'=>['module.kaiten-migration.report_view']],
];
