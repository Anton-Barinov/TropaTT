<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $migrationUp = request('POST', '/internal/migration/up', [], $headers);
    assertTrue($migrationUp['status'] === 200, 'Migration up status must be 200');

    $suffix = randomSuffix();
    $companyCreate = request('POST', '/api/v1/companies', [
        'title' => 'Client Filter Company ' . $suffix,
    ], $headers);
    assertTrue($companyCreate['status'] === 201, 'Company create status must be 201');
    $companyPublicId = (string)($companyCreate['payload']['data']['company']['public_id'] ?? '');
    assertTrue($companyPublicId !== '', 'Company public_id is required');

    $clientA = request('POST', '/api/v1/clients', [
        'title' => 'A Client Filter ' . $suffix,
        'client_type' => 'legal_entity',
        'company_public_id' => $companyPublicId,
        'legal_name' => 'ООО Фильтр А ' . $suffix,
        'tax_inn' => '510' . substr(preg_replace('/\D+/', '', $suffix), 0, 7),
        'tax_kpp' => '123456789',
        'tax_ogrn' => '1234567890123',
        'website' => 'https://a-filter-' . strtolower(substr(bin2hex(random_bytes(3)), 0, 6)) . '.crm.local',
        'status' => 'active',
    ], $headers);
    assertTrue($clientA['status'] === 201, 'Client A create status must be 201');
    $clientAPublicId = (string)($clientA['payload']['data']['client']['public_id'] ?? '');
    $clientATaxInn = (string)($clientA['payload']['data']['client']['tax_inn'] ?? '');
    assertTrue($clientAPublicId !== '', 'Client A public_id is required');
    assertTrue($clientATaxInn !== '', 'Client A tax_inn is required');

    usleep(150000);

    $clientB = request('POST', '/api/v1/clients', [
        'title' => 'Z Client Filter ' . $suffix,
        'client_type' => 'individual',
        'company_public_id' => $companyPublicId,
        'status' => 'archived',
        'email' => 'filter_' . strtolower(substr(bin2hex(random_bytes(3)), 0, 6)) . '@crm.local',
    ], $headers);
    assertTrue($clientB['status'] === 201, 'Client B create status must be 201');
    $clientBPublicId = (string)($clientB['payload']['data']['client']['public_id'] ?? '');
    assertTrue($clientBPublicId !== '', 'Client B public_id is required');

    $filterByInn = request('GET', '/api/v1/clients?tax_inn=' . urlencode($clientATaxInn), [], $headers);
    assertTrue($filterByInn['status'] === 200, 'Filter by tax_inn status must be 200');
    $itemsByInn = $filterByInn['payload']['data']['items'] ?? [];
    assertTrue(is_array($itemsByInn) && count($itemsByInn) >= 1, 'Filter by tax_inn must return at least one item');
    assertTrue((string)($itemsByInn[0]['tax_inn'] ?? '') === $clientATaxInn, 'Filter by tax_inn must match exact inn');

    $withWebsite = request('GET', '/api/v1/clients?has_website=1&search=' . urlencode($suffix), [], $headers);
    assertTrue($withWebsite['status'] === 200, 'Filter has_website=1 status must be 200');
    $withWebsiteItems = $withWebsite['payload']['data']['items'] ?? [];
    $withWebsiteIds = array_map(static fn(array $row): string => (string)($row['public_id'] ?? ''), is_array($withWebsiteItems) ? $withWebsiteItems : []);
    assertTrue(in_array($clientAPublicId, $withWebsiteIds, true), 'Filter has_website=1 must include client A');
    assertTrue(!in_array($clientBPublicId, $withWebsiteIds, true), 'Filter has_website=1 must exclude client B');

    $withoutWebsite = request('GET', '/api/v1/clients?has_website=0&search=' . urlencode($suffix), [], $headers);
    assertTrue($withoutWebsite['status'] === 200, 'Filter has_website=0 status must be 200');
    $withoutWebsiteItems = $withoutWebsite['payload']['data']['items'] ?? [];
    $withoutWebsiteIds = array_map(static fn(array $row): string => (string)($row['public_id'] ?? ''), is_array($withoutWebsiteItems) ? $withoutWebsiteItems : []);
    assertTrue(in_array($clientBPublicId, $withoutWebsiteIds, true), 'Filter has_website=0 must include client B');

    $statusFilter = request('GET', '/api/v1/clients?status=active&search=' . urlencode($suffix), [], $headers);
    assertTrue($statusFilter['status'] === 200, 'Filter by status status must be 200');
    $statusItems = $statusFilter['payload']['data']['items'] ?? [];
    $statusIds = array_map(static fn(array $row): string => (string)($row['public_id'] ?? ''), is_array($statusItems) ? $statusItems : []);
    assertTrue(in_array($clientAPublicId, $statusIds, true), 'Status active filter must include client A');
    assertTrue(!in_array($clientBPublicId, $statusIds, true), 'Status active filter must exclude client B');

    $sortByTitle = request('GET', '/api/v1/clients?search=' . urlencode($suffix) . '&sort_by=title&sort_dir=ASC', [], $headers);
    assertTrue($sortByTitle['status'] === 200, 'Sort by title status must be 200');
    $sortItems = $sortByTitle['payload']['data']['items'] ?? [];
    assertTrue(is_array($sortItems) && count($sortItems) >= 2, 'Sort by title must return at least two filtered items');
    assertTrue((string)($sortItems[0]['public_id'] ?? '') === $clientAPublicId, 'Title ASC sort must place client A first');

    $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
    $emptyByDate = request('GET', '/api/v1/clients?search=' . urlencode($suffix) . '&created_to=' . urlencode($yesterday), [], $headers);
    assertTrue($emptyByDate['status'] === 200, 'Created_to filter status must be 200');
    $dateItems = $emptyByDate['payload']['data']['items'] ?? [];
    assertTrue(is_array($dateItems) && count($dateItems) === 0, 'Created_to filter for yesterday must exclude today clients');

    $searchRank = request('GET', '/api/v1/search/clients?q=' . urlencode($clientATaxInn) . '&limit=10', [], $headers);
    assertTrue($searchRank['status'] === 200, 'Search clients status must be 200');
    $rankItems = $searchRank['payload']['data']['items'] ?? [];
    assertTrue(is_array($rankItems) && count($rankItems) > 0, 'Search clients must return ranked items');
    assertTrue((string)($rankItems[0]['public_id'] ?? '') === $clientAPublicId, 'Search ranking must prioritize exact tax_inn match');

    $deleteA = request('DELETE', '/api/v1/clients/' . $clientAPublicId, [], $headers);
    assertTrue($deleteA['status'] === 200, 'Delete client A status must be 200');
    $deleteB = request('DELETE', '/api/v1/clients/' . $clientBPublicId, [], $headers);
    assertTrue($deleteB['status'] === 200, 'Delete client B status must be 200');
    $deleteCompany = request('DELETE', '/api/v1/companies/' . $companyPublicId, [], $headers);
    assertTrue($deleteCompany['status'] === 200, 'Delete company status must be 200');

    echo "[OK] Client filters/sorting smoke passed\n";
    echo "client_a_public_id={$clientAPublicId}\n";
    echo "client_b_public_id={$clientBPublicId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Client filters/sorting smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
