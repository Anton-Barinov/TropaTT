<?php
declare(strict_types=1);

/** Contract guard for organization-aware file reads/writes. */
$root = dirname(__DIR__, 2);
$repo = file_get_contents($root . '/model/file/FileRepository.php') ?: '';
$service = file_get_contents($root . '/system/library/service/FileService.php') ?: '';
$controller = file_get_contents($root . '/controller/file/FileController.php') ?: '';
$checks = [
    'repository accepts organization scope' => str_contains($repo, 'listByEntity(string $entityType, string $entityPublicId, ?int $organizationId = null)') && str_contains($repo, 'findByPublicId(string $publicId, ?int $organizationId = null)'),
    'repository scopes writes' => str_contains($repo, 'softDelete(string $publicId, string $deletedAt, ?int $organizationId = null)'),
    'service propagates organization id' => substr_count($service, '$organizationId') >= 6,
    'controller resolves shared context' => str_contains($controller, 'organizationScopedActor') && str_contains($controller, 'rejectInvalidOrganizationContext'),
];
$failed = [];
foreach ($checks as $label => $ok) { echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL; if (!$ok) $failed[] = $label; }
if ($failed !== []) exit(1);
echo "[OK] file_organization_scope_unit\n";
