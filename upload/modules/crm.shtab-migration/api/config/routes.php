<?php
declare(strict_types=1);

use Module\Crm\ShtabMigration\Controller\ShtabMigrationController;

return [
 ['methods'=>['GET'],'route'=>'/connections','controller'=>ShtabMigrationController::class,'action'=>'listConnections','auth'=>true,'required_permissions'=>['module.shtab-migration.view']],
 ['methods'=>['POST'],'route'=>'/connections','controller'=>ShtabMigrationController::class,'action'=>'createConnection','auth'=>true,'required_permissions'=>['module.shtab-migration.manage']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}','controller'=>ShtabMigrationController::class,'action'=>'getConnection','auth'=>true,'required_permissions'=>['module.shtab-migration.view']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}/test','controller'=>ShtabMigrationController::class,'action'=>'testConnection','auth'=>true,'required_permissions'=>['module.shtab-migration.view']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}/user-mappings','controller'=>ShtabMigrationController::class,'action'=>'listUserMappings','auth'=>true,'required_permissions'=>['module.shtab-migration.view']],
 ['methods'=>['GET'],'route'=>'/connections/{public_id}/crm-users','controller'=>ShtabMigrationController::class,'action'=>'listCrmUsers','auth'=>true,'required_permissions'=>['module.shtab-migration.view']],
 ['methods'=>['PATCH'],'route'=>'/connections/{public_id}/user-mappings/{mapping_id}','controller'=>ShtabMigrationController::class,'action'=>'updateUserMapping','auth'=>true,'required_permissions'=>['module.shtab-migration.manage']],
 ['methods'=>['DELETE'],'route'=>'/connections/{public_id}','controller'=>ShtabMigrationController::class,'action'=>'deleteConnection','auth'=>true,'required_permissions'=>['module.shtab-migration.delete']],
 ['methods'=>['GET'],'route'=>'/jobs','controller'=>ShtabMigrationController::class,'action'=>'listJobs','auth'=>true,'required_permissions'=>['module.shtab-migration.view']],
 ['methods'=>['POST'],'route'=>'/jobs','controller'=>ShtabMigrationController::class,'action'=>'createJob','auth'=>true,'required_permissions'=>['module.shtab-migration.run','project.manage','task.manage','import.manage']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}','controller'=>ShtabMigrationController::class,'action'=>'getJob','auth'=>true,'required_permissions'=>['module.shtab-migration.view']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/run','controller'=>ShtabMigrationController::class,'action'=>'startJob','auth'=>true,'required_permissions'=>['module.shtab-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/pause','controller'=>ShtabMigrationController::class,'action'=>'pauseJob','auth'=>true,'required_permissions'=>['module.shtab-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/cancel','controller'=>ShtabMigrationController::class,'action'=>'cancelJob','auth'=>true,'required_permissions'=>['module.shtab-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/retry-failed','controller'=>ShtabMigrationController::class,'action'=>'retryFailed','auth'=>true,'required_permissions'=>['module.shtab-migration.run']],
 ['methods'=>['POST'],'route'=>'/jobs/{public_id}/rollback','controller'=>ShtabMigrationController::class,'action'=>'rollbackJob','auth'=>true,'required_permissions'=>['module.shtab-migration.delete']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/items','controller'=>ShtabMigrationController::class,'action'=>'listJobItems','auth'=>true,'required_permissions'=>['module.shtab-migration.view']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/logs','controller'=>ShtabMigrationController::class,'action'=>'listJobLogs','auth'=>true,'required_permissions'=>['module.shtab-migration.view']],
 ['methods'=>['GET'],'route'=>'/jobs/{public_id}/report','controller'=>ShtabMigrationController::class,'action'=>'getReport','auth'=>true,'required_permissions'=>['module.shtab-migration.report_view']],
];
