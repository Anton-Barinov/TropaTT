<?php
declare(strict_types=1);

use Module\Crm\ActiveCollabMigration\Repository\ActiveCollabMigrationRepository;
use Module\Crm\ActiveCollabMigration\Service\ActiveCollabClient;

require __DIR__ . '/../api/repository/ActiveCollabMigrationRepository.php';
require __DIR__ . '/../api/service/ActiveCollabClient.php';

function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
@$pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn(): string => gmdate('Y-m-d H:i:s'));
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, public_id TEXT, login TEXT, full_name TEXT, is_root INTEGER, is_active INTEGER)');
$pdo->exec('CREATE TABLE organization_memberships (user_id INTEGER, organization_id INTEGER)');
$pdo->exec('CREATE TABLE module_activecollab_connections (id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER, public_id TEXT UNIQUE, name TEXT, auth_type TEXT, base_url TEXT, workspace_gid TEXT, status TEXT, last_checked_at TEXT, last_error TEXT, access_token_encrypted TEXT, refresh_token_encrypted TEXT, client_id_encrypted TEXT, client_secret_encrypted TEXT, created_by_user_id INTEGER, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE module_activecollab_job_items (id INTEGER PRIMARY KEY, organization_id INTEGER, job_id INTEGER, source_type TEXT, source_id TEXT, status TEXT)');
$pdo->exec('CREATE TABLE module_activecollab_source_mappings (id INTEGER PRIMARY KEY, organization_id INTEGER, public_id TEXT, connection_id INTEGER, workspace_gid TEXT, source_type TEXT, source_id TEXT, source_parent_id TEXT, target_type TEXT, target_public_id TEXT, source_checksum TEXT, target_checksum TEXT, state TEXT, created_by_job_id INTEGER, last_seen_at TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE module_activecollab_user_mappings (id INTEGER PRIMARY KEY, organization_id INTEGER, connection_id INTEGER, activecollab_user_gid TEXT, display_name TEXT, email TEXT, crm_user_public_id TEXT, mapping_status TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE module_activecollab_rate_limits (connection_id INTEGER PRIMARY KEY, organization_id INTEGER, requests_made INTEGER, window_started_at TEXT, retry_after_until TEXT, last_http_status INTEGER, last_request_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE module_activecollab_unresolved_entities (id INTEGER PRIMARY KEY, organization_id INTEGER, job_id INTEGER, source_type TEXT, source_id TEXT, reason_code TEXT, reason_text TEXT, payload_json TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE module_activecollab_workspace_recovery_audit (id INTEGER PRIMARY KEY, organization_id INTEGER, actor_user_id INTEGER, connection_public_id TEXT, jobs_assigned INTEGER, details_json TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE module_activecollab_job_logs (id INTEGER PRIMARY KEY, organization_id INTEGER, job_id INTEGER, level TEXT, step TEXT, message TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE module_activecollab_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER, public_id TEXT UNIQUE, connection_id INTEGER, workspace_gid TEXT, mode TEXT, status TEXT, source_scope_json TEXT, target_options_json TEXT, progress_json TEXT, summary_json TEXT, report_markdown TEXT, current_step TEXT, progress_percent REAL DEFAULT 0, last_source_cursor TEXT, lease_token TEXT, lease_until TEXT, started_at TEXT, finished_at TEXT, created_by_user_id INTEGER, created_at TEXT, updated_at TEXT)');
$pdo->exec("INSERT INTO users VALUES (1,'usr_1','u1','User 1',1,1),(2,'usr_2','u2','User 2',0,1)");
$pdo->exec('INSERT INTO organization_memberships VALUES (1,10),(2,20)');

$repo = new ActiveCollabMigrationRepository($pdo);
$repo->setOrganizationId(10);
$conn = $repo->createConnection(['name'=>'source A','base_url'=>'https://ac.example/api/v1','workspace_gid'=>'tenantA','created_by_user_id'=>1]);
check(!empty($conn['public_id']) && (int)$conn['organization_id'] === 10, 'new connection must store active workspace');
$mapping = $repo->upsertMapping((int)$conn['id'], 'tenantA', 'task', 'remote-task-1', ['target_type'=>'task','target_public_id'=>'tsk_1']);
check($mapping['public_id'] !== '', 'mapping insert must persist current workspace');
$job = $repo->createJob(['connection_id'=>(int)$conn['id'],'workspace_gid'=>'tenantA','created_by_user_id'=>1]);
check(!empty($job['public_id']) && (int)$job['organization_id'] === 10, 'new job must inherit active workspace');

$pdo->exec("INSERT INTO module_activecollab_job_items (organization_id,job_id,source_type,source_id,status) VALUES (10,1,'task','s1','pending')");
$pdo->exec("INSERT INTO module_activecollab_job_logs (organization_id,job_id,level,step,message,created_at) VALUES (10,1,'info','x','private','now')");
$repo->setOrganizationId(20);
check($repo->items((int)$job['id']) === [], 'item rows must not cross workspace');
check($repo->logs((int)$job['id']) === [], 'log rows must not cross workspace');
check($repo->findMapping((int)$conn['id'], 'tenantA', 'task', 'remote-task-1') === null, 'mapping rows must not cross workspace');
check($repo->getConnection((string)$conn['public_id']) === null, 'connection must not cross workspace');
check($repo->getJob((string)$job['public_id']) === null, 'job/report root must not cross workspace');
check($repo->listConnections(2, true) === [], 'connection listing must be workspace scoped');
$pdo->exec("INSERT INTO module_activecollab_job_items (organization_id,job_id,source_type,source_id,status) VALUES (20,$job[id],'task','foreign','pending')");
$repo->setOrganizationId(10);
$pdo->exec("INSERT INTO users VALUES (3,'usr_3','u3','User 3',0,1)");
$pdo->exec('INSERT INTO organization_memberships VALUES (3,10)');
$pdo->exec("INSERT INTO module_activecollab_user_mappings (organization_id,connection_id,activecollab_user_gid,crm_user_public_id,mapping_status) VALUES (10,1,'ac-user-3','usr_3','mapped')");
check($repo->mappedUserId((int)$conn['id'], 'ac-user-3') === 3, 'mapped active workspace member should resolve for assignment');
check($repo->mappedUserPublicId((int)$conn['id'], 'ac-user-3') === 'usr_3', 'mapped active workspace member public id should resolve');
$pdo->exec('DELETE FROM organization_memberships WHERE user_id=3 AND organization_id=10');
check($repo->mappedUserId((int)$conn['id'], 'ac-user-3') === null, 'revoked workspace member must no longer resolve by internal id');
check($repo->mappedUserPublicId((int)$conn['id'], 'ac-user-3') === null, 'revoked workspace member must no longer resolve by public id');
$repo->deleteConnection((int)$conn['id']);
check($repo->getConnection((string)$conn['public_id']) === null, 'connection deletion must remove current workspace connection');
check((int)$pdo->query("SELECT COUNT(*) FROM module_activecollab_job_items WHERE organization_id=20 AND source_id='foreign'")->fetchColumn() === 1, 'connection deletion must not remove child rows from another workspace');

try {
    $repo->createJob(['connection_id'=>(int)$conn['id'],'workspace_gid'=>'tenantA','created_by_user_id'=>2]);
    throw new RuntimeException('cross-workspace connection must not create a job');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'ACTIVECOLLAB_WORKSPACE_REQUIRED' || $e->getMessage() === 'ACTIVECOLLAB_SOURCE_IDENTITY_MISMATCH', 'cross-workspace attempt must fail closed');
}


// Explicit, root-authorized recovery repository operation atomically adopts a legacy tree.
$repo->setOrganizationId(10);
$pdo->exec("INSERT INTO module_activecollab_connections (organization_id,public_id,name,auth_type,base_url,workspace_gid,status,created_by_user_id,created_at,updated_at) VALUES (NULL,'asc_legacy','legacy source','pat','https://legacy.example/api/v1','legacy-account','active',2,'now','now')");
$legacyConnectionId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO module_activecollab_jobs (organization_id,public_id,connection_id,workspace_gid,mode,status,source_scope_json,target_options_json,created_by_user_id,created_at,updated_at) VALUES (NULL,'asj_legacy',$legacyConnectionId,'legacy-account','import','queued','{}','{}',2,'now','now')");
$legacyJobId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO module_activecollab_job_items (organization_id,job_id,source_type,source_id,status) VALUES (NULL,$legacyJobId,'task','legacy-task','pending')");
$pdo->exec("INSERT INTO module_activecollab_job_logs (organization_id,job_id,level,step,message,created_at) VALUES (NULL,$legacyJobId,'info','legacy','old log','now')");
$pdo->exec("INSERT INTO module_activecollab_unresolved_entities (organization_id,job_id,source_type,source_id,reason_code,reason_text,created_at) VALUES (NULL,$legacyJobId,'task','legacy-task','unmapped','old unresolved','now')");
$pdo->exec("INSERT INTO module_activecollab_source_mappings (organization_id,public_id,connection_id,workspace_gid,source_type,source_id,state,created_at,updated_at) VALUES (NULL,'asm_legacy',$legacyConnectionId,'legacy-account','task','legacy-task','active','now','now')");
$pdo->exec("INSERT INTO module_activecollab_user_mappings (organization_id,connection_id,activecollab_user_gid,mapping_status,created_at,updated_at) VALUES (NULL,$legacyConnectionId,'user-1','unmapped','now','now')");
$pdo->exec("INSERT INTO module_activecollab_rate_limits (connection_id,organization_id,requests_made,updated_at) VALUES ($legacyConnectionId,NULL,3,'now')");
check(count($repo->listLegacyConnections(1)) === 1, 'root should be able to inspect pending legacy records');
try { $repo->listLegacyConnections(2); throw new RuntimeException('non-root recovery inspection must fail'); } catch (RuntimeException $e) { check($e->getMessage() === 'ACTIVECOLLAB_ROOT_REQUIRED', 'legacy inspection must be root only'); }
try { $repo->assignLegacyConnection('asc_legacy', 2); throw new RuntimeException('non-root recovery assignment must fail'); } catch (RuntimeException $e) { check($e->getMessage() === 'ACTIVECOLLAB_ROOT_REQUIRED', 'legacy assignment must be root only'); }
$assigned = $repo->assignLegacyConnection('asc_legacy', 1);
check($assigned['jobs_assigned'] === 1, 'recovery should report number of adopted jobs');
check((int)$pdo->query("SELECT created_by_user_id FROM module_activecollab_jobs WHERE id=$legacyJobId")->fetchColumn() === 1, 'recovered jobs must be owned by the root operator who can process them');
check((int)$pdo->query("SELECT organization_id FROM module_activecollab_connections WHERE id=$legacyConnectionId")->fetchColumn() === 10, 'recovery must assign the parent connection');
check((int)$pdo->query("SELECT created_by_user_id FROM module_activecollab_connections WHERE id=$legacyConnectionId")->fetchColumn() === 1, 'recovered connection must belong to the root operator in the target workspace');
foreach (['module_activecollab_jobs','module_activecollab_job_items','module_activecollab_job_logs','module_activecollab_unresolved_entities'] as $table) check((int)$pdo->query("SELECT COUNT(*) FROM $table WHERE organization_id=10")->fetchColumn() > 0, "$table must inherit recovery workspace");
foreach (['module_activecollab_source_mappings','module_activecollab_user_mappings','module_activecollab_rate_limits'] as $table) check((int)$pdo->query("SELECT COUNT(*) FROM $table WHERE organization_id=10")->fetchColumn() > 0, "$table must inherit recovery workspace");
check((int)$pdo->query("SELECT COUNT(*) FROM module_activecollab_workspace_recovery_audit WHERE organization_id=10 AND actor_user_id=1 AND connection_public_id='asc_legacy'")->fetchColumn() === 1, 'recovery must write an audit event');
$auditDetails = json_decode((string)$pdo->query("SELECT details_json FROM module_activecollab_workspace_recovery_audit WHERE connection_public_id='asc_legacy'")->fetchColumn(), true);
check(in_array(2, $auditDetails['previous_job_owners'] ?? [], true), 'audit must preserve previous job ownership information');
check(($auditDetails['previous_connection_owner'] ?? 0) === 2, 'audit must preserve previous connection ownership information');

$pdo->exec("INSERT INTO module_activecollab_connections (organization_id,public_id,name,auth_type,base_url,workspace_gid,status,created_by_user_id,created_at,updated_at) VALUES (NULL,'asc_rollback','legacy source','pat','https://legacy.example/api/v1','legacy-account','active',1,'now','now')");
$rollbackConnectionId=(int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO module_activecollab_jobs (organization_id,public_id,connection_id,workspace_gid,mode,status,source_scope_json,target_options_json,created_by_user_id,created_at,updated_at) VALUES (NULL,'asj_rollback',$rollbackConnectionId,'legacy-account','import','queued','{}','{}',2,'now','now')");
$rollbackJobId=(int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO module_activecollab_job_items (organization_id,job_id,source_type,source_id,status) VALUES (NULL,$rollbackJobId,'task','rollback-task','pending')");
$pdo->exec("CREATE TRIGGER fail_recovery_audit BEFORE INSERT ON module_activecollab_workspace_recovery_audit BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
try { $repo->assignLegacyConnection('asc_rollback', 1); throw new RuntimeException('audit failure must abort recovery'); }
catch (PDOException) { check($pdo->query("SELECT organization_id FROM module_activecollab_connections WHERE public_id='asc_rollback'")->fetchColumn() === null, 'audit failure must roll back parent assignment');
check($pdo->query("SELECT organization_id FROM module_activecollab_jobs WHERE id=$rollbackJobId")->fetchColumn() === null, 'audit failure must roll back job assignment');
check($pdo->query("SELECT organization_id FROM module_activecollab_job_items WHERE job_id=$rollbackJobId")->fetchColumn() === null, 'audit failure must roll back child assignment'); }

ActiveCollabClient::assertAccountMatchesRoot('https://app.activecollab.com/12345/api/v1', '12345');
try { ActiveCollabClient::assertAccountMatchesRoot('https://app.activecollab.com/12345/api/v1', '99999'); throw new RuntimeException('wrong cloud account must fail'); }
catch (RuntimeException $e) { check($e->getMessage() === 'ACTIVECOLLAB_ACCOUNT_ROOT_MISMATCH', 'cloud account mismatch must fail closed'); }
try { ActiveCollabClient::assertAccountMatchesRoot('https://app.activecollab.com/12345/api/v1?next=https://evil.example', '12345'); throw new RuntimeException('query URL must fail'); }
catch (RuntimeException $e) { check($e->getMessage() === 'ACTIVECOLLAB_BASE_URL_INVALID', 'query parameters must be rejected'); }
foreach ([
    'https://app.activecollab.com/12345/../99999/api/v1',
    'https://app.activecollab.com/12345/%2e%2e/99999/api/v1',
    'https://app.activecollab.com/12345/%2E%2E/99999/api/v1',
    'https://app.activecollab.com/12345%2f..%2f99999/api/v1',
    'https://app.activecollab.com/12345%5c..%5c99999/api/v1',
    'https://app.activecollab.com/12345/%252e%252e/99999/api/v1',
] as $ambiguousRoot) {
    try { ActiveCollabClient::assertAccountMatchesRoot($ambiguousRoot, '12345'); throw new RuntimeException('ambiguous cloud API root must fail: '.$ambiguousRoot); }
    catch (RuntimeException $e) { check($e->getMessage() === 'ACTIVECOLLAB_BASE_URL_INVALID', 'dot segments and encoded separators must be rejected'); }
}

// An attacker-controlled query signature must not allow fetching arbitrary
// public hosts; only the configured API host and the explicit S3 allowlist are
// accepted for attachment downloads.
$attachmentClient = new ActiveCollabClient($repo, 'https://crm.example/api/v1');
foreach ([
    'https://attacker.example/file?signature=0123456789abcdef&expires=3600',
    'https://attacker.example/file?X-Amz-Signature=0123456789abcdef&X-Amz-Expires=3600',
] as $untrustedSignedUrl) {
    try { $attachmentClient->downloadAttachment('test-token', $untrustedSignedUrl, 1024); throw new RuntimeException('signed arbitrary attachment host must fail closed'); }
    catch (RuntimeException $e) { check($e->getMessage() === 'ACTIVECOLLAB_ATTACHMENT_HOST_BLOCKED', 'signature must not bypass attachment host allowlist'); }
}

$migration = file_get_contents(__DIR__ . '/../api/migration/003_workspace_scope.sql');
check(is_string($migration) && str_contains($migration, 'HAVING COUNT(DISTINCT organization_id)=1'), 'legacy backfill must only assign unique creator membership');
check(str_contains($migration, 'WHERE c.organization_id IS NULL'), 'migration must preserve non-destructive nullable legacy path');
check(str_contains($migration, 'information_schema.tables') && str_contains($migration, 'acw_has_memberships'), 'legacy backfill must skip safely if memberships table is missing');
check(str_contains(file_get_contents(__DIR__ . '/../api/migration/004_workspace_recovery_audit.sql'), 'module_activecollab_workspace_recovery_audit'), 'recovery must have an audit table migration');
foreach (['connections','jobs','job_items','source_mappings','user_mappings','rate_limits','job_logs','unresolved_entities'] as $table) check(str_contains($migration, 'module_activecollab_'.$table), 'migration must cover '.$table);
echo "workspace_scope_test: PASS\n";
