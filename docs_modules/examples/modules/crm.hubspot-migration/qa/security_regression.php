<?php
declare(strict_types=1);

use Api\System\Library\Service\ClientService;
use Module\Crm\HubSpotMigration\Mcp\MigrationRunTool;
use Module\Crm\HubSpotMigration\Service\HubSpotMigrationService;

require_once __DIR__ . '/../../../../../upload/api/system/library/service/ClientService.php';
require_once __DIR__ . '/../api/Service/HubSpotMigrationService.php';
require_once __DIR__ . '/../api/Mcp/MigrationRunTool.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};

$baseline = [
    'title' => 'Imported Person', 'email' => 'person@example.test', 'phone' => '+1000000000',
    'notes' => 'initial note', 'status' => 'active', 'counterparty_type' => 'individual',
    'legal_name' => '', 'person_last_name' => '', 'person_first_name' => '', 'person_middle_name' => '',
    'person_birth_date' => null, 'tax_inn' => '', 'tax_kpp' => '', 'tax_ogrn' => '', 'tax_ogrnip' => '',
    'bank_account' => '', 'bank_name' => '', 'bank_bik' => '', 'bank_corr_account' => '',
    'website' => '', 'messenger' => '', 'address_legal' => '', 'address_postal' => '', 'address_actual' => '',
    'extra_attributes' => [],
];
$baselineHash = HubSpotMigrationService::clientFingerprint($baseline);
foreach (['status' => 'inactive', 'counterparty_type' => 'legal_entity', 'client_type' => 'legal_entity', 'legal_name' => 'Edited Ltd', 'tax_inn' => '123', 'address_actual' => 'Somewhere', 'extra_attributes' => ['tag' => 'changed']] as $field => $value) {
    $changed = $baseline;
    $changed[$field] = $value;
    $assert(!hash_equals($baselineHash, HubSpotMigrationService::clientFingerprint($changed)), "rollback fingerprint changes when {$field} changes");
}
$assert(hash_equals($baselineHash, HubSpotMigrationService::clientFingerprint($baseline)), 'same semantic client data has a stable fingerprint');

$piiResult = MigrationRunTool::publicResult('import_batch', [
    'session_id' => 'hsm_' . str_repeat('a', 32), 'processed' => 25, 'imported' => 20, 'skipped' => 2, 'errors' => 3,
    'report' => ['items' => [['contact' => ['title' => 'Private Name', 'email' => 'private@example.test', 'phone' => '+1999']]]],
]);
$serialized = json_encode($piiResult, JSON_THROW_ON_ERROR);
$assert(!str_contains($serialized, 'Private Name') && !str_contains($serialized, 'private@example.test') && !str_contains($serialized, '+1999'), 'MCP result omits contact PII and report');
$assert($piiResult['imported'] === 20 && !isset($piiResult['report']), 'MCP result contains operation counts only');
$rollbackResult = MigrationRunTool::publicResult('rollback', ['session_id' => 'hsm_' . str_repeat('a', 32), 'deleted_unchanged_records' => 4, 'retained_changed_or_unavailable_records' => 2, 'report' => ['items' => [['contact' => ['title' => 'Private Name']]]]]);
$assert(!str_contains(json_encode($rollbackResult, JSON_THROW_ON_ERROR), 'Private Name'), 'MCP rollback result omits report PII');

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE module_hubspot_migration_sessions (id INTEGER PRIMARY KEY, public_id TEXT, workspace_id INTEGER, status TEXT, total_records INTEGER DEFAULT 0, processed_records INTEGER DEFAULT 0, imported_records INTEGER DEFAULT 0, skipped_records INTEGER DEFAULT 0, error_records INTEGER DEFAULT 0)");
$db->exec("CREATE TABLE module_hubspot_migration_records (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER, session_public_id TEXT, object_type TEXT, hubspot_id TEXT, contact_label TEXT, raw_payload_json TEXT, normalized_payload_json TEXT, contact_fingerprint TEXT, status TEXT, crm_target_entity TEXT, crm_target_id TEXT, error_message TEXT, processed_at TEXT)");
$db->exec("CREATE TABLE module_hubspot_migration_id_map (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER, object_type TEXT, hubspot_id TEXT, crm_entity_type TEXT, crm_entity_id TEXT, imported_session_id TEXT)");
$sessionId = 'hsm_' . str_repeat('b', 32);
$db->prepare("INSERT INTO module_hubspot_migration_sessions (id, public_id, workspace_id, status) VALUES (1, ?, 7, 'draft')")->execute([$sessionId]);
$client = (new ReflectionClass(ClientService::class))->newInstanceWithoutConstructor();
$service = new HubSpotMigrationService($db, 7, $client, ['id' => 1, 'organization_id' => 7]);
for ($batch = 0; $batch < 10; $batch++) {
    $records = [];
    for ($i = 0; $i < 100; $i++) {
        $n = $batch * 100 + $i;
        $records[] = ['id' => 'hs-' . $n, 'properties' => ['firstname' => 'Contact' . $n, 'email' => 'user' . $n . '@example.test']];
    }
    $service->stageContacts($sessionId, $records);
}
$rejected = false;
try {
    $service->stageContacts($sessionId, [['id' => 'hs-over-limit', 'properties' => ['firstname' => 'Extra', 'email' => 'extra@example.test']]]);
} catch (RuntimeException $e) {
    $rejected = $e->getMessage() === 'SESSION_RECORD_LIMIT_EXCEEDED';
}
$assert($rejected, 'session rejects staging above 1000 records');
$assert((int)$db->query('SELECT COUNT(*) FROM module_hubspot_migration_records')->fetchColumn() === 1000, 'over-limit stage is atomic and does not add records');
$page1 = $service->preview($sessionId, 1, 25);
$page2 = $service->preview($sessionId, 2, 25);
$assert(count($page1['items']) === 25 && count($page2['items']) === 25, 'preview returns only one bounded page');
$assert($page1['pagination']['total_records'] === 1000 && $page1['pagination']['total_pages'] === 40 && $page2['pagination']['page'] === 2, 'preview reports correct pagination metadata');
$assert($page1['counts']['ready'] === 1000, 'preview counts cover all records without loading them all into response');
$largePage = $service->preview($sessionId, 1, 1000);
$assert(count($largePage['items']) === 50 && $largePage['pagination']['per_page'] === 50, 'preview clamps oversized page requests to 50 rows');

echo "PASS: {$checks} HubSpot security regression checks\n";
