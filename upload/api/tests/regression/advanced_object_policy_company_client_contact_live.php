<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'obj_ccc_' . $suffix,
        'title' => 'Object CCC ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['company.manage', 'client.manage', 'contact.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $ownerLogin = 'obj_ccc_owner_' . $suffix;
    $ownerTokenFactor = 'obj-ccc-owner-token-' . $suffix;
    $ownerCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $ownerLogin,
        'password' => 'ObjCccOwner123!',
        'token' => $ownerTokenFactor,
        'email' => $ownerLogin . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($ownerCreate['status'] === 201, 'Owner user create must return 201');
    $ownerUserPublicId = (string)($ownerCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($ownerUserPublicId !== '', 'Owner user public_id is required');

    $viewerLogin = 'obj_ccc_viewer_' . $suffix;
    $viewerTokenFactor = 'obj-ccc-viewer-token-' . $suffix;
    $viewerCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $viewerLogin,
        'password' => 'ObjCccViewer123!',
        'token' => $viewerTokenFactor,
        'email' => $viewerLogin . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($viewerCreate['status'] === 201, 'Viewer user create must return 201');
    $viewerUserPublicId = (string)($viewerCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($viewerUserPublicId !== '', 'Viewer user public_id is required');

    $ownerLoginResp = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $ownerLogin,
        'password' => 'ObjCccOwner123!',
        'token' => $ownerTokenFactor,
    ]);
    liveAssert($ownerLoginResp['status'] === 200, 'Owner login must return 200');
    $ownerToken = (string)($ownerLoginResp['payload']['data']['access_token'] ?? '');
    liveAssert($ownerToken !== '', 'Owner token is required');
    $ownerHeaders = ['Authorization' => 'Bearer ' . $ownerToken];

    $viewerLoginResp = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $viewerLogin,
        'password' => 'ObjCccViewer123!',
        'token' => $viewerTokenFactor,
    ]);
    liveAssert($viewerLoginResp['status'] === 200, 'Viewer login must return 200');
    $viewerToken = (string)($viewerLoginResp['payload']['data']['access_token'] ?? '');
    liveAssert($viewerToken !== '', 'Viewer token is required');
    $viewerHeaders = ['Authorization' => 'Bearer ' . $viewerToken];

    $companyCreate = liveRequest('POST', 'api/v1/companies', [
        'title' => 'Obj Policy Company ' . $suffix,
    ], $ownerHeaders);
    liveAssert($companyCreate['status'] === 201, 'Company create must return 201');
    $companyPublicId = (string)($companyCreate['payload']['data']['company']['public_id'] ?? '');
    liveAssert($companyPublicId !== '', 'Company public_id is required');

    $clientCreate = liveRequest('POST', 'api/v1/clients', [
        'title' => 'Obj Policy Client ' . $suffix,
        'company_public_id' => $companyPublicId,
        'email' => 'client_' . $suffix . '@crm.local',
    ], $ownerHeaders);
    liveAssert($clientCreate['status'] === 201, 'Client create must return 201');
    $clientPublicId = (string)($clientCreate['payload']['data']['client']['public_id'] ?? '');
    liveAssert($clientPublicId !== '', 'Client public_id is required');

    $contactCreate = liveRequest('POST', 'api/v1/contacts', [
        'full_name' => 'Obj Policy Contact ' . $suffix,
        'company_public_id' => $companyPublicId,
        'client_public_id' => $clientPublicId,
        'email' => 'contact_' . $suffix . '@crm.local',
    ], $ownerHeaders);
    liveAssert($contactCreate['status'] === 201, 'Contact create must return 201');
    $contactPublicId = (string)($contactCreate['payload']['data']['contact']['public_id'] ?? '');
    liveAssert($contactPublicId !== '', 'Contact public_id is required');

    $viewerCompanyGet = liveRequest('GET', 'api/v1/companies/' . $companyPublicId, [], $viewerHeaders);
    liveAssert($viewerCompanyGet['status'] === 404, 'Non-owner company get must return 404');

    $viewerClientGet = liveRequest('GET', 'api/v1/clients/' . $clientPublicId, [], $viewerHeaders);
    liveAssert($viewerClientGet['status'] === 404, 'Non-owner client get must return 404');

    $viewerContactGet = liveRequest('GET', 'api/v1/contacts/' . $contactPublicId, [], $viewerHeaders);
    liveAssert($viewerContactGet['status'] === 404, 'Non-owner contact get must return 404');

    $viewerCompanyUpdate = liveRequest('PATCH', 'api/v1/companies/' . $companyPublicId, ['title' => 'Forbidden'], $viewerHeaders);
    liveAssert($viewerCompanyUpdate['status'] === 404, 'Non-owner company update must return 404');

    $viewerClientUpdate = liveRequest('PATCH', 'api/v1/clients/' . $clientPublicId, ['title' => 'Forbidden'], $viewerHeaders);
    liveAssert($viewerClientUpdate['status'] === 404, 'Non-owner client update must return 404');

    $viewerContactUpdate = liveRequest('PATCH', 'api/v1/contacts/' . $contactPublicId, ['full_name' => 'Forbidden'], $viewerHeaders);
    liveAssert($viewerContactUpdate['status'] === 404, 'Non-owner contact update must return 404');

    $viewerCompanyDelete = liveRequest('DELETE', 'api/v1/companies/' . $companyPublicId, [], $viewerHeaders);
    liveAssert($viewerCompanyDelete['status'] === 404, 'Non-owner company delete must return 404');

    $viewerClientDelete = liveRequest('DELETE', 'api/v1/clients/' . $clientPublicId, [], $viewerHeaders);
    liveAssert($viewerClientDelete['status'] === 404, 'Non-owner client delete must return 404');

    $viewerContactDelete = liveRequest('DELETE', 'api/v1/contacts/' . $contactPublicId, [], $viewerHeaders);
    liveAssert($viewerContactDelete['status'] === 404, 'Non-owner contact delete must return 404');

    $viewerClientByForeignCompany = liveRequest('POST', 'api/v1/clients', [
        'title' => 'Forbidden relation',
        'company_public_id' => $companyPublicId,
    ], $viewerHeaders);
    liveAssert($viewerClientByForeignCompany['status'] === 422, 'Non-owner client create with foreign company must return 422');
    liveAssert((string)($viewerClientByForeignCompany['payload']['code'] ?? '') === 'COMPANY_NOT_FOUND', 'Non-owner client create code mismatch');

    $viewerContactByForeignLinks = liveRequest('POST', 'api/v1/contacts', [
        'full_name' => 'Forbidden relation',
        'company_public_id' => $companyPublicId,
        'client_public_id' => $clientPublicId,
    ], $viewerHeaders);
    liveAssert($viewerContactByForeignLinks['status'] === 422, 'Non-owner contact create with foreign links must return 422');

    $viewerCompaniesList = liveRequest('GET', 'api/v1/companies', [], $viewerHeaders);
    liveAssert($viewerCompaniesList['status'] === 200, 'Viewer companies list must return 200');
    $viewerCompanies = $viewerCompaniesList['payload']['data']['items'] ?? [];
    liveAssert(is_array($viewerCompanies), 'Viewer companies list items must be array');
    foreach ($viewerCompanies as $item) {
        liveAssert((string)($item['public_id'] ?? '') !== $companyPublicId, 'Viewer must not see owner company in list');
    }

    $viewerClientsList = liveRequest('GET', 'api/v1/clients', [], $viewerHeaders);
    liveAssert($viewerClientsList['status'] === 200, 'Viewer clients list must return 200');
    $viewerClients = $viewerClientsList['payload']['data']['items'] ?? [];
    liveAssert(is_array($viewerClients), 'Viewer clients list items must be array');
    foreach ($viewerClients as $item) {
        liveAssert((string)($item['public_id'] ?? '') !== $clientPublicId, 'Viewer must not see owner client in list');
    }

    $viewerContactsList = liveRequest('GET', 'api/v1/contacts', [], $viewerHeaders);
    liveAssert($viewerContactsList['status'] === 200, 'Viewer contacts list must return 200');
    $viewerContacts = $viewerContactsList['payload']['data']['items'] ?? [];
    liveAssert(is_array($viewerContacts), 'Viewer contacts list items must be array');
    foreach ($viewerContacts as $item) {
        liveAssert((string)($item['public_id'] ?? '') !== $contactPublicId, 'Viewer must not see owner contact in list');
    }

    $ownerCompanyGet = liveRequest('GET', 'api/v1/companies/' . $companyPublicId, [], $ownerHeaders);
    liveAssert($ownerCompanyGet['status'] === 200, 'Owner company get must return 200');

    $ownerClientGet = liveRequest('GET', 'api/v1/clients/' . $clientPublicId, [], $ownerHeaders);
    liveAssert($ownerClientGet['status'] === 200, 'Owner client get must return 200');

    $ownerContactGet = liveRequest('GET', 'api/v1/contacts/' . $contactPublicId, [], $ownerHeaders);
    liveAssert($ownerContactGet['status'] === 200, 'Owner contact get must return 200');

    $ownerContactDelete = liveRequest('DELETE', 'api/v1/contacts/' . $contactPublicId, [], $ownerHeaders);
    liveAssert($ownerContactDelete['status'] === 200, 'Owner contact delete must return 200');

    $ownerClientDelete = liveRequest('DELETE', 'api/v1/clients/' . $clientPublicId, [], $ownerHeaders);
    liveAssert($ownerClientDelete['status'] === 200, 'Owner client delete must return 200');

    $ownerCompanyDelete = liveRequest('DELETE', 'api/v1/companies/' . $companyPublicId, [], $ownerHeaders);
    liveAssert($ownerCompanyDelete['status'] === 200, 'Owner company delete must return 200');

    liveRequest('DELETE', 'api/v1/users/' . $ownerUserPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $viewerUserPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_object_policy_company_client_contact_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_object_policy_company_client_contact_live: ' . $e->getMessage() . "\n");
    exit(1);
}
