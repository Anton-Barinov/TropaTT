<?php
declare(strict_types=1);

/** Contract guard for the workspace invitation lifecycle and legacy isolation. */
$root = dirname(__DIR__, 2);
$migration = file_get_contents($root . '/system/library/database/migration/OrganizationInvitationScopeMigration.php') ?: '';
$repository = file_get_contents($root . '/model/security/InvitationRepository.php') ?: '';
$service = file_get_contents($root . '/system/library/service/InvitationService.php') ?: '';
$organizationController = file_get_contents($root . '/controller/organization/OrganizationController.php') ?: '';
$securityController = file_get_contents($root . '/controller/security/InvitationController.php') ?: '';
$routes = file_get_contents($root . '/config/routes.php') ?: '';

$checks = [
    'migration has stable key' => str_contains($migration, '20260918_000002_organization_invitation_scope'),
    'migration adds organization scope' => str_contains($migration, "organization_id",),
    'migration adds revocation metadata' => str_contains($migration, "revoked_at"),
    'migration is idempotent' => str_contains($migration, 'addColumnIfNotExists'),
    'repository lists by organization' => str_contains($repository, 'listForOrganization'),
    'repository revokes by organization' => str_contains($repository, 'revokeForOrganization'),
    'repository refreshes token by organization' => str_contains($repository, 'refreshForOrganization'),
    'service creates workspace invitations' => str_contains($service, 'createForOrganization'),
    'service accepts into membership' => str_contains($service, 'acceptForOrganization') && str_contains($service, 'addOrUpdateMember'),
    'legacy service create remains available' => preg_match('/public function create\(array \$input, array \$actor\)/', $service) === 1,
    'workspace routes exist' => str_contains($routes, '/organizations/{public_id}/invitations'),
    'workspace controller actions exist' => str_contains($organizationController, 'createInvitation') && str_contains($organizationController, 'resendInvitation'),
    'public organization accept exists' => str_contains($securityController, 'acceptOrganization'),
];

$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) $failed[] = $label;
}
if ($failed !== []) exit(1);
echo '[OK] organization_invitation_lifecycle_unit' . PHP_EOL;
