<?php
declare(strict_types=1);

/** Contract guard for tenant-scoped import/export queue jobs. */
$root = dirname(__DIR__, 2);
$importRepo = file_get_contents($root . '/model/import/ImportJobRepository.php') ?: '';
$exportRepo = file_get_contents($root . '/model/export/ExportJobRepository.php') ?: '';
$importService = file_get_contents($root . '/system/library/service/ImportService.php') ?: '';
$exportService = file_get_contents($root . '/system/library/service/ExportService.php') ?: '';
$importController = file_get_contents($root . '/controller/import/ImportController.php') ?: '';
$exportController = file_get_contents($root . '/controller/export/ExportController.php') ?: '';
$mcp = file_get_contents($root . '/controller/mcp/McpController.php') ?: '';

$checks = [
    'repositories accept and apply organization scope' =>
        str_contains($importRepo, 'list(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false, ?int $organizationId = null)')
        && str_contains($importRepo, "ij.organization_id")
        && str_contains($exportRepo, 'list(array $filters, ?int $actorUserId = null, bool $actorIsRoot = false, ?int $organizationId = null)')
        && str_contains($exportRepo, "ej.organization_id"),
    'workers resume using persisted organization context' =>
        str_contains($importRepo, 'SELECT id, public_id, organization_id FROM import_jobs')
        && str_contains($exportRepo, 'SELECT id, public_id, organization_id FROM export_jobs')
        && str_contains($importService, "'organization_id' => \$this->organizationId(\$job)")
        && str_contains($exportService, "'organization_id' => \$this->organizationId(\$job)"),
    'new jobs persist active organization' =>
        str_contains($importService, "'organization_id' => \$this->organizationId(\$actor)")
        && str_contains($exportService, "'organization_id' => \$this->organizationId(\$actor)"),
    'foreign client organization is rejected' =>
        str_contains($importService, 'inputOrganizationMatches')
        && str_contains($exportService, 'inputOrganizationMatches')
        && str_contains($importController, "isset(\$result['error'])")
        && str_contains($exportController, "isset(\$result['error'])"),
    'REST and MCP propagate resolved organization actor' =>
        str_contains($importController, 'organizationScopedActor')
        && str_contains($exportController, 'organizationScopedActor')
        && substr_count($mcp, 'service->list($this->jobFilters($arguments), $this->organizationScopedActor($this->actor()))') >= 2,
];

$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failed[] = $label;
}
if ($failed !== []) exit(1);
echo "[OK] organization_import_export_scope_unit\n";
