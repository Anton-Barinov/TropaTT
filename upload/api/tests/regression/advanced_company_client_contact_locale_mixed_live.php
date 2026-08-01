<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicEnvelope(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicEnvelope($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'ccc_locale_' . $suffix,
        'title' => 'CCC Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['company.manage', 'client.manage', 'contact.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'ccc_locale_' . $suffix;
    $token = 'ccc-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'CccLocale123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'CccLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $companyList = liveRequest('GET', 'api/v1/companies', [], $headers);
    liveAssert($companyList['status'] === 200, 'Companies list must return 200');
    liveAssert((string)($companyList['payload']['message'] ?? '') === 'Company list', 'Companies list message mismatch');
    assertNoCyrillicEnvelope((string)($companyList['payload']['message'] ?? ''), 'companies.list.message');

    $clientList = liveRequest('GET', 'api/v1/clients', [], $headers);
    liveAssert($clientList['status'] === 200, 'Clients list must return 200');
    liveAssert((string)($clientList['payload']['message'] ?? '') === 'Client list', 'Clients list message mismatch');
    assertNoCyrillicEnvelope((string)($clientList['payload']['message'] ?? ''), 'clients.list.message');

    $contactList = liveRequest('GET', 'api/v1/contacts', [], $headers);
    liveAssert($contactList['status'] === 200, 'Contacts list must return 200');
    liveAssert((string)($contactList['payload']['message'] ?? '') === 'Contact list', 'Contacts list message mismatch');
    assertNoCyrillicEnvelope((string)($contactList['payload']['message'] ?? ''), 'contacts.list.message');

    $companyValidation = liveRequest('POST', 'api/v1/companies', [], $headers);
    liveAssert($companyValidation['status'] === 422, 'Company validation must return 422');
    liveAssert((string)($companyValidation['payload']['message'] ?? '') === 'Validation error', 'Company validation message mismatch');
    assertNoCyrillicEnvelope($companyValidation['payload']['errors'] ?? [], 'companies.validation.errors');

    $clientValidation = liveRequest('POST', 'api/v1/clients', [], $headers);
    liveAssert($clientValidation['status'] === 422, 'Client validation must return 422');
    liveAssert((string)($clientValidation['payload']['message'] ?? '') === 'Validation error', 'Client validation message mismatch');
    assertNoCyrillicEnvelope($clientValidation['payload']['errors'] ?? [], 'clients.validation.errors');

    $contactValidation = liveRequest('POST', 'api/v1/contacts', [], $headers);
    liveAssert($contactValidation['status'] === 422, 'Contact validation must return 422');
    liveAssert((string)($contactValidation['payload']['message'] ?? '') === 'Validation error', 'Contact validation message mismatch');
    assertNoCyrillicEnvelope($contactValidation['payload']['errors'] ?? [], 'contacts.validation.errors');

    $companyNotFound = liveRequest('GET', 'api/v1/companies/com_missing_' . $suffix, [], $headers);
    liveAssert($companyNotFound['status'] === 404, 'Company not found must return 404');
    liveAssert((string)($companyNotFound['payload']['message'] ?? '') === 'Company not found', 'Company not found message mismatch');
    assertNoCyrillicEnvelope($companyNotFound['payload']['errors'] ?? [], 'companies.not_found.errors');

    $clientNotFound = liveRequest('GET', 'api/v1/clients/cli_missing_' . $suffix, [], $headers);
    liveAssert($clientNotFound['status'] === 404, 'Client not found must return 404');
    liveAssert((string)($clientNotFound['payload']['message'] ?? '') === 'Client not found', 'Client not found message mismatch');
    assertNoCyrillicEnvelope($clientNotFound['payload']['errors'] ?? [], 'clients.not_found.errors');

    $contactNotFound = liveRequest('GET', 'api/v1/contacts/ctc_missing_' . $suffix, [], $headers);
    liveAssert($contactNotFound['status'] === 404, 'Contact not found must return 404');
    liveAssert((string)($contactNotFound['payload']['message'] ?? '') === 'Contact not found', 'Contact not found message mismatch');
    assertNoCyrillicEnvelope($contactNotFound['payload']['errors'] ?? [], 'contacts.not_found.errors');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_company_client_contact_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_company_client_contact_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
