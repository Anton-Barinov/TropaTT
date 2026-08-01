<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param array<string,mixed> $members */
function roleOf(array $members, string $userPublicId): ?string
{
    foreach ($members as $item) {
        if ((string)($item['user_public_id'] ?? '') === $userPublicId) {
            return (string)($item['role_code'] ?? '');
        }
    }

    return null;
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    // Roles for organization and retention permission boundaries.
    $roleOrgCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'org_edge_' . $suffix,
        'title' => 'Org Edge ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleOrgCreate['status'] === 201, 'Org role create must return 201');
    $roleOrgPublicId = (string)($roleOrgCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($roleOrgPublicId !== '', 'Org role public_id is required');

    $roleRetentionCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'ret_edge_' . $suffix,
        'title' => 'Retention Edge ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleRetentionCreate['status'] === 201, 'Retention role create must return 201');
    $roleRetentionPublicId = (string)($roleRetentionCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($roleRetentionPublicId !== '', 'Retention role public_id is required');

    $setOrgPerms = liveRequest('PUT', 'api/v1/roles/' . $roleOrgPublicId . '/permissions', [
        'permission_codes' => ['organization.manage'],
    ], $rootHeaders);
    liveAssert($setOrgPerms['status'] === 200, 'Org role permissions set must return 200');

    $setRetentionPerms = liveRequest('PUT', 'api/v1/roles/' . $roleRetentionPublicId . '/permissions', [
        'permission_codes' => ['settings.manage'],
    ], $rootHeaders);
    liveAssert($setRetentionPerms['status'] === 200, 'Retention role permissions set must return 200');

    // Users.
    $orgLogin = 'org_edge_user_' . $suffix;
    $orgTokenFactor = 'org-edge-token-' . $suffix;
    $orgUserCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $orgLogin,
        'password' => 'OrgEdge123!',
        'token' => $orgTokenFactor,
        'email' => $orgLogin . '@crm.local',
        'role_public_ids' => [$roleOrgPublicId],
    ], $rootHeaders);
    liveAssert($orgUserCreate['status'] === 201, 'Org user create must return 201');
    $orgUserPublicId = (string)($orgUserCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($orgUserPublicId !== '', 'Org user public_id is required');

    $owner2Login = 'org_owner2_' . $suffix;
    $owner2TokenFactor = 'org-owner2-token-' . $suffix;
    $owner2Create = liveRequest('POST', 'api/v1/users', [
        'login' => $owner2Login,
        'password' => 'Owner2Edge123!',
        'token' => $owner2TokenFactor,
        'email' => $owner2Login . '@crm.local',
        'role_public_ids' => [$roleOrgPublicId],
    ], $rootHeaders);
    liveAssert($owner2Create['status'] === 201, 'Owner2 user create must return 201');
    $owner2PublicId = (string)($owner2Create['payload']['data']['user']['public_id'] ?? '');
    liveAssert($owner2PublicId !== '', 'Owner2 public_id is required');

    $retLogin = 'ret_edge_user_' . $suffix;
    $retTokenFactor = 'ret-edge-token-' . $suffix;
    $retUserCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $retLogin,
        'password' => 'RetEdge123!',
        'token' => $retTokenFactor,
        'email' => $retLogin . '@crm.local',
        'role_public_ids' => [$roleRetentionPublicId],
    ], $rootHeaders);
    liveAssert($retUserCreate['status'] === 201, 'Retention user create must return 201');
    $retUserPublicId = (string)($retUserCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($retUserPublicId !== '', 'Retention user public_id is required');

    $orgLoginResp = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $orgLogin,
        'password' => 'OrgEdge123!',
        'token' => $orgTokenFactor,
    ]);
    liveAssert($orgLoginResp['status'] === 200, 'Org user login must return 200');
    $orgHeaders = ['Authorization' => 'Bearer ' . (string)($orgLoginResp['payload']['data']['access_token'] ?? '')];

    $retLoginResp = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $retLogin,
        'password' => 'RetEdge123!',
        'token' => $retTokenFactor,
    ]);
    liveAssert($retLoginResp['status'] === 200, 'Retention user login must return 200');
    $retHeaders = ['Authorization' => 'Bearer ' . (string)($retLoginResp['payload']['data']['access_token'] ?? '')];

    // Organization edge-cases.
    $orgCreate = liveRequest('POST', 'api/v1/organizations', [
        'title' => 'Org Edge Workspace ' . $suffix,
        'slug' => 'org-edge-' . $suffix,
    ], $rootHeaders);
    liveAssert($orgCreate['status'] === 201, 'Organization create must return 201');
    $organizationPublicId = (string)($orgCreate['payload']['data']['organization']['public_id'] ?? '');
    liveAssert($organizationPublicId !== '', 'Organization public_id is required');

    // Non-member with organization.manage must still not access/manage чужую организацию.
    $orgGetBeforeMember = liveRequest('GET', 'api/v1/organizations/' . $organizationPublicId, [], $orgHeaders);
    liveAssert($orgGetBeforeMember['status'] === 404, 'Org user before membership must not access organization');

    $orgMembersBeforeMember = liveRequest('GET', 'api/v1/organizations/' . $organizationPublicId . '/members', [], $orgHeaders);
    liveAssert($orgMembersBeforeMember['status'] === 404, 'Org user before membership must not list members');

    // Add org user as owner and second user as member.
    $addOwner = liveRequest('POST', 'api/v1/organizations/' . $organizationPublicId . '/members', [
        'user_public_id' => $orgUserPublicId,
        'role_code' => 'owner',
    ], $rootHeaders);
    liveAssert($addOwner['status'] === 200, 'Add owner member must return 200');

    $addSecondMember = liveRequest('POST', 'api/v1/organizations/' . $organizationPublicId . '/members', [
        'user_public_id' => $owner2PublicId,
        'role_code' => 'member',
    ], $rootHeaders);
    liveAssert($addSecondMember['status'] === 200, 'Add second member must return 200');

    $membersAfterAdd = liveRequest('GET', 'api/v1/organizations/' . $organizationPublicId . '/members', [], $orgHeaders);
    liveAssert($membersAfterAdd['status'] === 200, 'Owner must list members');
    $members = (array)($membersAfterAdd['payload']['data']['items'] ?? []);
    liveAssert(roleOf($members, $owner2PublicId) === 'member', 'Second user role must start as member');

    // Role transitions: member -> admin -> owner.
    $promoteAdmin = liveRequest('POST', 'api/v1/organizations/' . $organizationPublicId . '/members', [
        'user_public_id' => $owner2PublicId,
        'role_code' => 'admin',
    ], $orgHeaders);
    liveAssert($promoteAdmin['status'] === 200, 'Promote member to admin must return 200');

    $membersAfterAdmin = liveRequest('GET', 'api/v1/organizations/' . $organizationPublicId . '/members', [], $orgHeaders);
    $members = (array)($membersAfterAdmin['payload']['data']['items'] ?? []);
    liveAssert(roleOf($members, $owner2PublicId) === 'admin', 'Second user role must be admin after transition');

    $promoteOwner = liveRequest('POST', 'api/v1/organizations/' . $organizationPublicId . '/members', [
        'user_public_id' => $owner2PublicId,
        'role_code' => 'owner',
    ], $orgHeaders);
    liveAssert($promoteOwner['status'] === 200, 'Promote admin to owner must return 200');

    $membersAfterOwner = liveRequest('GET', 'api/v1/organizations/' . $organizationPublicId . '/members', [], $orgHeaders);
    $members = (array)($membersAfterOwner['payload']['data']['items'] ?? []);
    liveAssert(roleOf($members, $owner2PublicId) === 'owner', 'Second user role must be owner after transition');

    // Ownership transfer: remove root owner now that another owners exist.
    $removeRootOwner = liveRequest('DELETE', 'api/v1/organizations/' . $organizationPublicId . '/members/' . $root['user_public_id'], [], $orgHeaders);
    liveAssert($removeRootOwner['status'] === 200, 'Removing previous owner after transfer must return 200');

    // Last-owner protection: reduce to single owner and ensure self-remove fails.
    $removeOwner2 = liveRequest('DELETE', 'api/v1/organizations/' . $organizationPublicId . '/members/' . $owner2PublicId, [], $orgHeaders);
    liveAssert($removeOwner2['status'] === 200, 'Removing second owner while another owner exists must return 200');

    $removeSelfLastOwner = liveRequest('DELETE', 'api/v1/organizations/' . $organizationPublicId . '/members/' . $orgUserPublicId, [], $orgHeaders);
    liveAssert($removeSelfLastOwner['status'] === 422, 'Removing last owner must be blocked with 422');
    liveAssert((string)($removeSelfLastOwner['payload']['code'] ?? '') === 'ORGANIZATION_MEMBER_REMOVE_FAILED', 'Last-owner protection code mismatch');

    // Retention permission boundaries.
    $retGetForbidden = liveRequest('GET', 'api/v1/retention/metadata', [], $orgHeaders);
    liveAssert($retGetForbidden['status'] === 403, 'Org-only user must be forbidden on retention metadata GET');

    $retSetForbidden = liveRequest('PATCH', 'api/v1/retention/metadata', [
        'request_logs_days' => 77,
    ], $orgHeaders);
    liveAssert($retSetForbidden['status'] === 403, 'Org-only user must be forbidden on retention metadata PATCH');

    $retGetAllowed = liveRequest('GET', 'api/v1/retention/metadata', [], $retHeaders);
    liveAssert($retGetAllowed['status'] === 200, 'Retention-manage user must access retention metadata');

    $retSetInvalid = liveRequest('PATCH', 'api/v1/retention/metadata', [
        'request_logs_days' => 0,
    ], $retHeaders);
    liveAssert($retSetInvalid['status'] === 422, 'Retention metadata invalid payload must return 422');

    $retSetValid = liveRequest('PATCH', 'api/v1/retention/metadata', [
        'request_logs_days' => 77,
        'security_logs_days' => 88,
    ], $retHeaders);
    liveAssert($retSetValid['status'] === 200, 'Retention metadata valid patch must return 200');

    $retGetAlias = liveRequest('GET', 'api/v1/retention/get', [], $retHeaders);
    liveAssert($retGetAlias['status'] === 200, 'Retention alias GET must return 200');
    $retention = (array)($retGetAlias['payload']['data']['retention'] ?? []);
    liveAssert((int)($retention['request_logs_days'] ?? 0) === 77, 'Retention request_logs_days must persist');
    liveAssert((int)($retention['security_logs_days'] ?? 0) === 88, 'Retention security_logs_days must persist');

    // Cleanup.
    liveRequest('DELETE', 'api/v1/organizations/' . $organizationPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $orgUserPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $owner2PublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $retUserPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $roleOrgPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $roleRetentionPublicId, [], $rootHeaders);

    echo "[OK] advanced_retention_organization_edges_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_retention_organization_edges_live: ' . $e->getMessage() . "\n");
    exit(1);
}
