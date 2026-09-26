<?php
declare(strict_types=1);

/** Contract guard for organization-aware file reads/writes. */
$root = dirname(__DIR__, 2);
$repo = file_get_contents($root . '/model/file/FileRepository.php') ?: '';
$service = file_get_contents($root . '/system/library/service/FileService.php') ?: '';
$controller = file_get_contents($root . '/controller/file/FileController.php') ?: '';
$migrationManager = file_get_contents($root . '/system/library/database/migration/MigrationManager.php') ?: '';
$checks = [
    'repository accepts organization scope' => str_contains($repo, 'listByEntity(string $entityType, string $entityPublicId, ?int $organizationId = null)') && str_contains($repo, 'findByPublicId(string $publicId, ?int $organizationId = null)'),
    'repository scopes writes' => str_contains($repo, 'softDelete(string $publicId, string $deletedAt, ?int $organizationId = null)'),
    'service propagates organization id' => substr_count($service, '$organizationId') >= 6,
    'controller resolves shared context' => str_contains($controller, 'organizationScopedActor') && str_contains($controller, 'rejectInvalidOrganizationContext'),
    // A file must inherit its ENTITY's organization, not the caller's active
    // workspace, and the list must not hide an accessible entity's files when
    // the stored org differs (the "uploaded file disappears after refresh" bug).
    'create inherits the entity organization' => str_contains($service, 'resolveEntityOrganizationId($entityType, $entityPublicId) ?? $organizationId') && str_contains($service, '], $fileOrganizationId);'),
    'list is not narrowed by the actor organization' => str_contains($service, '->listByEntity($type, $entityId, null)'),
    'backfill migration is registered' => str_contains($migrationManager, 'FileOrganizationBackfillMigration'),
];
$failed = [];
foreach ($checks as $label => $ok) { echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL; if (!$ok) $failed[] = $label; }
if ($failed !== []) exit(1);
echo "[OK] file_organization_scope_unit\n";
