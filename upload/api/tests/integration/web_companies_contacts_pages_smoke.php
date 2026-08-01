<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $headers = authHeaders((string)$root['token']);

    $webRoutes = require dirname(__DIR__, 3) . '/web/config/routes.php';
    assertTrue(is_array($webRoutes), 'web routes must be array');
    assertTrue(array_key_exists('companies', $webRoutes), 'web route companies must exist');
    assertTrue(array_key_exists('contacts', $webRoutes), 'web route contacts must exist');

    $companiesTpl = file_get_contents(dirname(__DIR__, 3) . '/web/view/template/page/companies.php');
    $contactsTpl = file_get_contents(dirname(__DIR__, 3) . '/web/view/template/page/contacts.php');
    assertTrue(is_string($companiesTpl) && str_contains($companiesTpl, 'data-page="companies"'), 'companies template marker missing');
    assertTrue(is_string($contactsTpl) && str_contains($contactsTpl, 'data-page="contacts"'), 'contacts template marker missing');

    $companyCreate = request('POST', '/api/v1/companies', [
        'title' => 'Web Company ' . randomSuffix(),
        'status' => 'active',
        'tax_number' => '7701234567',
        'email' => 'company@example.local',
    ], $headers);
    assertTrue($companyCreate['status'] === 201, 'Company create must return 201');
    $companyPublicId = (string)($companyCreate['payload']['data']['company']['public_id'] ?? '');
    assertTrue($companyPublicId !== '', 'Company public_id is required');

    $contactCreate = request('POST', '/api/v1/contacts', [
        'full_name' => 'Web Contact ' . randomSuffix(),
        'phone' => '+79990000000',
        'email' => 'contact@example.local',
        'company_public_id' => $companyPublicId,
    ], $headers);
    assertTrue($contactCreate['status'] === 201, 'Contact create must return 201');
    $contactPublicId = (string)($contactCreate['payload']['data']['contact']['public_id'] ?? '');
    assertTrue($contactPublicId !== '', 'Contact public_id is required');

    $companyList = request('GET', '/api/v1/companies?limit=50', [], $headers);
    assertTrue($companyList['status'] === 200, 'Company list must return 200');
    $companyFound = false;
    foreach ((array)($companyList['payload']['data']['items'] ?? []) as $item) {
        if ((string)($item['public_id'] ?? '') === $companyPublicId) {
            $companyFound = true;
            break;
        }
    }
    assertTrue($companyFound, 'Created company must be present in list');

    $contactList = request('GET', '/api/v1/contacts?limit=50', [], $headers);
    assertTrue($contactList['status'] === 200, 'Contact list must return 200');
    $contactFound = false;
    foreach ((array)($contactList['payload']['data']['items'] ?? []) as $item) {
        if ((string)($item['public_id'] ?? '') === $contactPublicId) {
            $contactFound = true;
            break;
        }
    }
    assertTrue($contactFound, 'Created contact must be present in list');

    $contactDelete = request('DELETE', '/api/v1/contacts/' . $contactPublicId, [], $headers);
    assertTrue($contactDelete['status'] === 200, 'Contact delete must return 200');
    $companyDelete = request('DELETE', '/api/v1/companies/' . $companyPublicId, [], $headers);
    assertTrue($companyDelete['status'] === 200, 'Company delete must return 200');

    fwrite(STDOUT, "[OK] web_companies_contacts_pages_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_companies_contacts_pages_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}

