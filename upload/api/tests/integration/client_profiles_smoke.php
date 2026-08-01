<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $migrationUp = request('POST', '/internal/migration/up', [], $headers);
    assertTrue($migrationUp['status'] === 200, 'Migration up status must be 200');

    $companyCreate = request('POST', '/api/v1/companies', [
        'title' => 'Client Profile Company ' . randomSuffix(),
    ], $headers);
    assertTrue($companyCreate['status'] === 201, 'Company create status must be 201');
    $companyPublicId = (string)($companyCreate['payload']['data']['company']['public_id'] ?? '');
    assertTrue($companyPublicId !== '', 'Company public_id is required');

    $individualCreate = request('POST', '/api/v1/clients', [
        'title' => 'Individual Client ' . randomSuffix(),
        'client_type' => 'individual',
        'company_public_id' => $companyPublicId,
        'email' => 'individual_' . randomSuffix() . '@crm.local',
        'website' => 'https://individual.crm.local',
        'extra_attributes' => ['source' => 'integration-smoke', 'vip' => true],
        'status' => 'active',
    ], $headers);
    assertTrue($individualCreate['status'] === 201, 'Individual client create status must be 201');
    $individualPublicId = (string)($individualCreate['payload']['data']['client']['public_id'] ?? '');
    assertTrue($individualPublicId !== '', 'Individual client public_id is required');

    $soleCreate = request('POST', '/api/v1/clients', [
        'title' => 'SP Client ' . randomSuffix(),
        'client_type' => 'sole_proprietor',
        'company_public_id' => $companyPublicId,
        'legal_name' => 'ИП Смоук Тест',
        'tax_inn' => '123456789012',
        'tax_ogrnip' => '123456789012345',
        'bank_account' => '12345678901234567890',
        'bank_bik' => '123456789',
        'bank_corr_account' => '12345678901234567890',
        'bank_name' => 'Smoke Bank',
        'status' => 'active',
    ], $headers);
    assertTrue($soleCreate['status'] === 201, 'Sole proprietor client create status must be 201');
    $solePublicId = (string)($soleCreate['payload']['data']['client']['public_id'] ?? '');
    assertTrue($solePublicId !== '', 'Sole proprietor client public_id is required');

    $legalCreate = request('POST', '/api/v1/clients', [
        'title' => 'LLC Client ' . randomSuffix(),
        'client_type' => 'legal_entity',
        'company_public_id' => $companyPublicId,
        'legal_name' => 'ООО Смоук Тест',
        'tax_inn' => '1234567890',
        'tax_kpp' => '123456789',
        'tax_ogrn' => '1234567890123',
        'bank_account' => '12345678901234567890',
        'bank_bik' => '123456789',
        'bank_corr_account' => '12345678901234567890',
        'website' => 'https://legal.crm.local',
    ], $headers);
    assertTrue($legalCreate['status'] === 201, 'Legal entity client create status must be 201');
    $legalPublicId = (string)($legalCreate['payload']['data']['client']['public_id'] ?? '');
    assertTrue($legalPublicId !== '', 'Legal entity client public_id is required');

    $legalGet = request('GET', '/api/v1/clients/' . $legalPublicId, [], $headers);
    assertTrue($legalGet['status'] === 200, 'Legal entity client get status must be 200');
    assertTrue((string)($legalGet['payload']['data']['client']['client_type'] ?? '') === 'legal_entity', 'Legal entity client type must be legal_entity');

    $invalidType = request('POST', '/api/v1/clients', [
        'title' => 'Invalid Type ' . randomSuffix(),
        'client_type' => 'enterprise',
    ], $headers);
    assertTrue($invalidType['status'] === 422, 'Invalid client type status must be 422');

    $missingLegalRequired = request('POST', '/api/v1/clients', [
        'title' => 'Invalid Legal ' . randomSuffix(),
        'client_type' => 'legal_entity',
    ], $headers);
    assertTrue($missingLegalRequired['status'] === 422, 'Missing legal required fields status must be 422');

    $invalidInn = request('POST', '/api/v1/clients', [
        'title' => 'Invalid SP INN ' . randomSuffix(),
        'client_type' => 'sole_proprietor',
        'legal_name' => 'ИП Невалид',
        'tax_inn' => '123',
        'tax_ogrnip' => '123456789012345',
    ], $headers);
    assertTrue($invalidInn['status'] === 422, 'Invalid INN status must be 422');

    $invalidWebsite = request('POST', '/api/v1/clients', [
        'title' => 'Invalid Website ' . randomSuffix(),
        'client_type' => 'individual',
        'website' => 'not-a-url',
    ], $headers);
    assertTrue($invalidWebsite['status'] === 422, 'Invalid website status must be 422');

    $legalUpdate = request('PATCH', '/api/v1/clients/' . $legalPublicId, [
        'notes' => 'Updated legal client note',
        'extra_attributes' => ['account_manager' => 'root'],
    ], $headers);
    assertTrue($legalUpdate['status'] === 200, 'Legal entity client update status must be 200');
    assertTrue((string)($legalUpdate['payload']['data']['client']['notes'] ?? '') === 'Updated legal client note', 'Legal notes must be updated');

    $listByType = request('GET', '/api/v1/clients?client_type=legal_entity&search=' . urlencode('ООО'), [], $headers);
    assertTrue($listByType['status'] === 200, 'Clients list by type status must be 200');

    $deleteIndividual = request('DELETE', '/api/v1/clients/' . $individualPublicId, [], $headers);
    assertTrue($deleteIndividual['status'] === 200, 'Individual client delete status must be 200');

    $deleteSole = request('DELETE', '/api/v1/clients/' . $solePublicId, [], $headers);
    assertTrue($deleteSole['status'] === 200, 'Sole proprietor client delete status must be 200');

    $deleteLegal = request('DELETE', '/api/v1/clients/' . $legalPublicId, [], $headers);
    assertTrue($deleteLegal['status'] === 200, 'Legal entity client delete status must be 200');

    $deleteCompany = request('DELETE', '/api/v1/companies/' . $companyPublicId, [], $headers);
    assertTrue($deleteCompany['status'] === 200, 'Company delete status must be 200');

    echo "[OK] Client profiles smoke passed\n";
    echo "individual_public_id={$individualPublicId}\n";
    echo "sole_public_id={$solePublicId}\n";
    echo "legal_public_id={$legalPublicId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Client profiles smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
