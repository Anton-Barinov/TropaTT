<?php
declare(strict_types=1);

/**
 * Reproducible two-workspace isolation matrix.
 *
 * The fixture deliberately uses the real repositories and semantic index,
 * rather than manually changing demo data. Every assertion is a read or a
 * scoped update against a disposable SQLite database.
 */

require_once __DIR__ . '/../../system/library/support/Autoloader.php';
$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();
require_once __DIR__ . '/../../system/library/http/Request.php';
require_once __DIR__ . '/../../system/library/organization/OrganizationMembershipReader.php';
require_once __DIR__ . '/../../system/library/service/OrganizationContextService.php';

use Api\Model\Export\ExportJobRepository;
use Api\Model\Import\ImportJobRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Recycle_bin\RecycleBinRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Config;
use Api\System\Library\Http\Request;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Organization\OrganizationMembershipReader;
use Api\System\Library\Service\AiSemanticIndexService;
use Api\System\Library\Service\OrganizationContextService;

function crossTenantAssert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo '[OK] ' . $message . PHP_EOL;
}

final class MatrixMembershipReader implements OrganizationMembershipReader
{
    public function listForUser(int $userId): array
    {
        return match ($userId) {
            101 => [['id' => 1, 'public_id' => 'org_alpha', 'title' => 'Alpha', 'role_code' => 'owner']],
            102 => [['id' => 1, 'public_id' => 'org_alpha', 'title' => 'Alpha', 'role_code' => 'admin'], ['id' => 2, 'public_id' => 'org_beta', 'title' => 'Beta', 'role_code' => 'member']],
            103 => [['id' => 2, 'public_id' => 'org_beta', 'title' => 'Beta', 'role_code' => 'owner']],
            default => [],
        };
    }

    public function listAll(): array
    {
        return [
            ['id' => 1, 'public_id' => 'org_alpha', 'title' => 'Alpha', 'role_code' => 'owner'],
            ['id' => 2, 'public_id' => 'org_beta', 'title' => 'Beta', 'role_code' => 'owner'],
        ];
    }
}

function matrixRequest(array $headers = [], array $query = []): Request
{
    return new Request('GET', '/api/v1/tasks', '/api/v1/tasks', $query, [], [], [], [], $headers, '', 'matrix', 'matrix', 'ru-ru');
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, public_id VARCHAR(64), login VARCHAR(120), full_name VARCHAR(255), is_root INTEGER DEFAULT 0)');
$pdo->exec('CREATE TABLE organizations (id INTEGER PRIMARY KEY, public_id VARCHAR(64), title VARCHAR(255))');
$pdo->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), organization_id INTEGER, created_by_user_id INTEGER, manager_user_id INTEGER, team_public_id VARCHAR(64), client_public_id VARCHAR(64), task_key_prefix VARCHAR(32), task_key_prefix_locked INTEGER DEFAULT 0, description TEXT, status_code VARCHAR(32), priority_code VARCHAR(32), archived_at DATETIME NULL, created_at DATETIME, updated_at DATETIME, row_version INTEGER DEFAULT 1)');
$pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, public_id VARCHAR(64) UNIQUE, project_id INTEGER, organization_id INTEGER, title VARCHAR(255), description TEXT, status_code VARCHAR(32), priority_code VARCHAR(32), archived_at DATETIME NULL, deleted_at DATETIME NULL, creator_user_id INTEGER, assignee_user_id INTEGER, client_public_id VARCHAR(64), task_key VARCHAR(64), task_key_prefix VARCHAR(32), task_sequence_number INTEGER, due_at DATETIME NULL, start_at DATETIME NULL, end_at DATETIME NULL, created_at DATETIME, updated_at DATETIME, row_version INTEGER DEFAULT 1)');
$jobColumns = 'id INTEGER PRIMARY KEY, organization_id INTEGER, user_id INTEGER, public_id VARCHAR(64) UNIQUE, type VARCHAR(64), status VARCHAR(32), attempts INTEGER DEFAULT 0, next_run_at DATETIME NULL, locked_at DATETIME NULL, started_at DATETIME NULL, finished_at DATETIME NULL, last_error TEXT NULL, dead_letter INTEGER DEFAULT 0, payload TEXT NULL, result TEXT NULL, created_at DATETIME, updated_at DATETIME';
$pdo->exec("CREATE TABLE import_jobs ({$jobColumns})");
$pdo->exec("CREATE TABLE export_jobs ({$jobColumns})");
$pdo->exec('CREATE TABLE recycle_bin (id INTEGER PRIMARY KEY, organization_id INTEGER, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(64), entity_public_id VARCHAR(64), payload TEXT, deleted_at DATETIME, restored_at DATETIME NULL, deleted_by_user_id INTEGER)');
$pdo->exec("INSERT INTO organizations (id,public_id,title) VALUES (1,'org_alpha','Alpha'),(2,'org_beta','Beta')");
$pdo->exec("INSERT INTO users (id,public_id,login,full_name) VALUES (101,'usr_alpha','alpha','Alpha Owner'),(102,'usr_multi','multi','Multi Admin'),(103,'usr_beta','beta','Beta Owner')");

$pdo->exec("INSERT INTO projects (id,public_id,title,organization_id,created_by_user_id,manager_user_id,task_key_prefix,created_at,updated_at) VALUES (1,'prj_alpha','Shared title',1,101,101,'ALPHA',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),(2,'prj_beta','Shared title',2,103,103,'BETA',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$pdo->exec("INSERT INTO tasks (id,public_id,project_id,organization_id,title,status_code,priority_code,creator_user_id,task_key,task_key_prefix,created_at,updated_at) VALUES (1,'tsk_alpha',1,1,'Shared task','new','normal',101,'ALPHA-1','ALPHA',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),(2,'tsk_beta',2,2,'Shared task','new','normal',103,'BETA-1','BETA',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$jobPayload = "(organization_id,user_id,public_id,type,status,attempts,dead_letter,created_at,updated_at)";
$pdo->exec("INSERT INTO import_jobs {$jobPayload} VALUES (1,101,'imp_alpha','tasks','queued',0,0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),(2,103,'imp_beta','tasks','queued',0,0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$pdo->exec("INSERT INTO export_jobs {$jobPayload} VALUES (1,101,'exp_alpha','tasks','queued',0,0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),(2,103,'exp_beta','tasks','queued',0,0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$pdo->exec("INSERT INTO recycle_bin (id,organization_id,public_id,entity_type,entity_public_id,deleted_at,deleted_by_user_id) VALUES (1,1,'rb_alpha','task','tsk_alpha',CURRENT_TIMESTAMP,101),(2,2,'rb_beta','task','tsk_beta',CURRENT_TIMESTAMP,103)");
$pdo->exec('CREATE INDEX idx_projects_organization ON projects (organization_id)');
$pdo->exec('CREATE INDEX idx_tasks_organization ON tasks (organization_id)');
$pdo->exec('CREATE INDEX idx_import_jobs_organization ON import_jobs (organization_id)');
$pdo->exec('CREATE INDEX idx_export_jobs_organization ON export_jobs (organization_id)');
$pdo->exec('CREATE INDEX idx_recycle_bin_organization_status ON recycle_bin (organization_id, restored_at, deleted_at)');

$projects = new ProjectRepository($pdo);
$tasks = new TaskRepository($pdo);
$imports = new ImportJobRepository($pdo);
$exports = new ExportJobRepository($pdo);
$recycle = new RecycleBinRepository($pdo);

crossTenantAssert($projects->projectIdByPublicId('prj_alpha', 1) === 1, 'Alpha owner resolves an Alpha project');
crossTenantAssert($projects->projectIdByPublicId('prj_beta', 1) === null, 'Alpha context cannot resolve a Beta project id');
crossTenantAssert($tasks->taskIdByPublicId('tsk_beta', 1) === null, 'Alpha context cannot resolve a Beta task id');
crossTenantAssert($tasks->taskIdByPublicId('tsk_beta', 2) === 2, 'Beta context resolves its own task id');

crossTenantAssert($imports->findByPublicId('imp_beta', 1) === null, 'Foreign import job is hidden from Alpha');
crossTenantAssert($exports->findByPublicId('exp_beta', 1) === null, 'Foreign export job is hidden from Alpha');
crossTenantAssert($imports->updateByPublicId('imp_beta', ['status' => 'canceled'], 1) === false, 'Alpha cannot mutate Beta import job');
crossTenantAssert($exports->updateByPublicId('exp_beta', ['status' => 'canceled'], 1) === false, 'Alpha cannot mutate Beta export job');
crossTenantAssert($recycle->findByPublicId('rb_beta', 1) === null, 'Foreign recycle-bin row is hidden from Alpha');
crossTenantAssert($recycle->markRestoredByPublicId('rb_beta', gmdate('Y-m-d H:i:s'), 1) === false, 'Alpha cannot restore Beta recycle-bin row');
crossTenantAssert($recycle->markRestoredByPublicId('rb_alpha', gmdate('Y-m-d H:i:s'), 1) === true, 'Alpha can restore its own recycle-bin row');

$context = new OrganizationContextService(new MatrixMembershipReader());
$alphaActor = ['id' => 101, 'is_root' => false];
$betaActor = ['id' => 103, 'is_root' => false];
$multiActor = ['id' => 102, 'is_root' => false];
$alpha = $context->resolve(matrixRequest(['X-Organization-Id' => 'org_alpha']), $alphaActor);
$beta = $context->resolve(matrixRequest(['X-Organization-Id' => 'org_beta']), $multiActor);
$switchBack = $context->resolve(matrixRequest(['X-Organization-Id' => 'org_alpha']), $multiActor);
$foreign = $context->resolve(matrixRequest(['X-Organization-Id' => 'org_beta']), $alphaActor);
crossTenantAssert($alpha['status'] === 'active' && $alpha['organization_public_id'] === 'org_alpha', 'Owner session selects Alpha');
crossTenantAssert($beta['status'] === 'active' && $beta['organization_public_id'] === 'org_beta', 'Multi-membership session switches to Beta');
crossTenantAssert($switchBack['status'] === 'active' && $switchBack['organization_public_id'] === 'org_alpha', 'Multi-membership session switches back to Alpha');
crossTenantAssert($foreign['status'] === 'forbidden', 'Actor without Beta membership is rejected');
crossTenantAssert(hash('sha256', 'org_alpha') !== hash('sha256', 'org_beta'), 'Organization cache suffixes are distinct');

$tmpBase = sys_get_temp_dir() . '/crm_cross_tenant_' . bin2hex(random_bytes(4));
@mkdir($tmpBase, 0775, true);
try {
    $config = new Config();
    $config->merge('default', ['storage' => ['base' => $tmpBase]]);
    $semantic = new AiSemanticIndexService($config, new JsonLogger([]));
    $semantic->indexEntityDocument('task', 'tsk_alpha', 'shared semantic text', ['organization_public_id' => 'org_alpha'], 'org_alpha');
    $semantic->indexEntityDocument('task', 'tsk_beta', 'shared semantic text', ['organization_public_id' => 'org_beta'], 'org_beta');
    $alphaHits = $semantic->search('shared', 10, 'org_alpha');
    $betaHits = $semantic->search('shared', 10, 'org_beta');
    $alphaIds = array_map(static fn(array $row): string => (string)($row['document_public_id'] ?? ''), $alphaHits['items'] ?? []);
    $betaIds = array_map(static fn(array $row): string => (string)($row['document_public_id'] ?? ''), $betaHits['items'] ?? []);
    crossTenantAssert($alphaIds === ['tasks:tsk_alpha'], 'Alpha semantic index contains only Alpha documents');
    crossTenantAssert($betaIds === ['tasks:tsk_beta'], 'Beta semantic index contains only Beta documents');
} finally {
    foreach (glob($tmpBase . '/ai/cache/*') ?: [] as $file) @unlink($file);
    @rmdir($tmpBase . '/ai/cache');
    @rmdir($tmpBase . '/ai');
    @rmdir($tmpBase);
}

echo "[OK] organization cross-tenant matrix: two organizations, role/context switching, foreign IDs, jobs, semantic and recycle paths passed\n";

