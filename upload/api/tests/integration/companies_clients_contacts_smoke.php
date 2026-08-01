<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    // Ensure permission registry is seeded including company/client/contact scopes.
    $permissions = request('GET', '/api/v1/permissions', [], $headers);
    assertTrue($permissions['status'] === 200, 'Permissions list status must be 200');

    $companyCreate = request('POST', '/api/v1/companies', [
        'title' => 'Smoke Company ' . randomSuffix(),
    ], $headers);
    assertTrue($companyCreate['status'] === 201, 'Company create status must be 201');
    $companyPublicId = (string)($companyCreate['payload']['data']['company']['public_id'] ?? '');
    assertTrue($companyPublicId !== '', 'Company public_id is required');

    $companyGet = request('GET', '/api/v1/companies/' . $companyPublicId, [], $headers);
    assertTrue($companyGet['status'] === 200, 'Company get status must be 200');

    $clientCreate = request('POST', '/api/v1/clients', [
        'title' => 'Smoke Client ' . randomSuffix(),
        'company_public_id' => $companyPublicId,
        'email' => 'client_' . randomSuffix() . '@crm.local',
        'phone' => '+79990001122',
        'status' => 'active',
    ], $headers);
    assertTrue($clientCreate['status'] === 201, 'Client create status must be 201');
    $clientPublicId = (string)($clientCreate['payload']['data']['client']['public_id'] ?? '');
    assertTrue($clientPublicId !== '', 'Client public_id is required');

    $clientGet = request('GET', '/api/v1/clients/' . $clientPublicId, [], $headers);
    assertTrue($clientGet['status'] === 200, 'Client get status must be 200');

    $contactCreate = request('POST', '/api/v1/contacts', [
        'full_name' => 'Smoke Contact ' . randomSuffix(),
        'company_public_id' => $companyPublicId,
        'client_public_id' => $clientPublicId,
        'email' => 'contact_' . randomSuffix() . '@crm.local',
        'phone' => '+79990003344',
    ], $headers);
    assertTrue($contactCreate['status'] === 201, 'Contact create status must be 201');
    $contactPublicId = (string)($contactCreate['payload']['data']['contact']['public_id'] ?? '');
    assertTrue($contactPublicId !== '', 'Contact public_id is required');

    $contactGet = request('GET', '/api/v1/contacts/' . $contactPublicId, [], $headers);
    assertTrue($contactGet['status'] === 200, 'Contact get status must be 200');

    $contactDelete = request('DELETE', '/api/v1/contacts/' . $contactPublicId, [], $headers);
    assertTrue($contactDelete['status'] === 200, 'Contact delete status must be 200');

    $clientDelete = request('DELETE', '/api/v1/clients/' . $clientPublicId, [], $headers);
    assertTrue($clientDelete['status'] === 200, 'Client delete status must be 200');

    $companyDelete = request('DELETE', '/api/v1/companies/' . $companyPublicId, [], $headers);
    assertTrue($companyDelete['status'] === 200, 'Company delete status must be 200');

    echo "Companies/Clients/Contacts smoke: OK\n";
    echo "company_public_id={$companyPublicId}\n";
    echo "client_public_id={$clientPublicId}\n";
    echo "contact_public_id={$contactPublicId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Companies/Clients/Contacts smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
