<?php
declare(strict_types=1);

use Api\System\Library\Service\ClientService;
use Module\Crm\HubSpotMigration\Service\HubSpotMigrationService;

require_once __DIR__ . '/../../../../../upload/api/system/library/service/ClientService.php';
require_once __DIR__ . '/../api/Service/HubSpotMigrationService.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE module_hubspot_migration_sessions (id INTEGER PRIMARY KEY, public_id TEXT, workspace_id INTEGER, status TEXT, processed_records INTEGER DEFAULT 0, imported_records INTEGER DEFAULT 0, skipped_records INTEGER DEFAULT 0, error_records INTEGER DEFAULT 0)");
$db->exec("CREATE TABLE module_hubspot_migration_records (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER, session_public_id TEXT, object_type TEXT, hubspot_id TEXT, contact_label TEXT, raw_payload_json TEXT, normalized_payload_json TEXT, contact_fingerprint TEXT, status TEXT, crm_target_entity TEXT, crm_target_id TEXT, error_message TEXT, processed_at TEXT)");
$db->exec("CREATE TABLE module_hubspot_migration_id_map (id INTEGER PRIMARY KEY AUTOINCREMENT, workspace_id INTEGER, object_type TEXT, hubspot_id TEXT, crm_entity_type TEXT, crm_entity_id TEXT, imported_session_id TEXT)");
$sessionId = 'hsm_' . str_repeat('a', 32);
$db->prepare("INSERT INTO module_hubspot_migration_sessions (id, public_id, workspace_id, status) VALUES (1, ?, 7, 'importing')")->execute([$sessionId]);
$normalized = json_encode(['title' => 'Retry Contact', 'email' => 'retry@example.test', 'phone' => '', 'notes' => 'keep on retry'], JSON_THROW_ON_ERROR);
$db->prepare("INSERT INTO module_hubspot_migration_records (workspace_id, session_public_id, object_type, hubspot_id, contact_label, raw_payload_json, normalized_payload_json, status) VALUES (7, ?, 'contacts', 'hub-1', 'Retry Contact', '{}', ?, 'pending')")->execute([$sessionId, $normalized]);

// A constructor-free ClientService deliberately fails on use. This gives the
// importer a deterministic CRM failure without connecting to an application DB.
$client = (new ReflectionClass(ClientService::class))->newInstanceWithoutConstructor();
$service = new HubSpotMigrationService($db, 7, $client, ['id' => 1, 'organization_id' => 7]);
$first = $service->importBatch($sessionId);
assertSameValue(1, $first['errors'], 'initial import must capture a record failure');
$row = $db->query("SELECT status, normalized_payload_json FROM module_hubspot_migration_records WHERE hubspot_id='hub-1'")->fetch(PDO::FETCH_ASSOC);
assertSameValue('error', $row['status'], 'failed row status');
assertSameValue($normalized, $row['normalized_payload_json'], 'normalized payload retained after failed import');

$retry = $service->retryFailedRecords($sessionId);
assertSameValue(1, $retry['processed'], 'retry must submit the failed row again');
$row = $db->query("SELECT status, normalized_payload_json FROM module_hubspot_migration_records WHERE hubspot_id='hub-1'")->fetch(PDO::FETCH_ASSOC);
assertSameValue('error', $row['status'], 'failed retry returns row to error');
assertSameValue($normalized, $row['normalized_payload_json'], 'normalized payload retained after repeated failure');

echo "PASS: failed HubSpot rows retain payload and are reprocessed by retry endpoint.\n";
