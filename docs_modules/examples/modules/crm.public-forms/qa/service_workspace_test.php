<?php

declare(strict_types=1);

use Api\System\Library\Container;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\PublicForms\Mcp\ListFormsTool;
use Module\Crm\PublicForms\Service\PublicFormsService;
use function PublicFormsQa\{check, database, fields, service};

require_once __DIR__ . '/test_support.php';

$db = database();
$db->exec("INSERT INTO projects(public_id,organization_id,title) VALUES ('prj_team_a',10,'Workspace A project')");
$db->exec("INSERT INTO projects(public_id,organization_id,title) VALUES ('prj_team_b',20,'Workspace B project')");
$db->exec("INSERT INTO users(id,public_id,full_name) VALUES (101,'usr_team_a','A User'),(202,'usr_team_b','B User')");
$db->exec('INSERT INTO organization_memberships(organization_id,user_id) VALUES (10,101),(20,202)');
$forms = service($db);

$formA = $forms->saveForm(10, 'workspace-a-form', 'Workspace A', 'intake', fields(), 'I consent.', 'prj_team_a', 'usr_team_a');
$formB = $forms->saveForm(20, 'workspace-b-form', 'Workspace B', 'intake', fields(), 'I consent.', 'prj_team_b', 'usr_team_b');
check($formA !== $formB, 'forms receive independent public identifiers');
check(count($forms->listForms(10)) === 1 && $forms->listForms(10)[0]['public_id'] === $formA, 'workspace A lists only its own forms');
check(count($forms->listForms(20)) === 1 && $forms->listForms(20)[0]['public_id'] === $formB, 'workspace B lists only its own forms');
check($forms->getFormForWorkspace(10, $formB) === null, 'workspace A cannot read workspace B form by public ID');
check($forms->getFormForWorkspace(20, $formA) === null, 'workspace B cannot read workspace A form by public ID');
check(!$forms->setFormPublished(20, $formA, true), 'cross-workspace publish is rejected');
check(!$forms->updateForm(20, $formA, 'forged-update', 'Forged', 'intake', fields(), 'I consent.'), 'cross-workspace update is rejected');
check($forms->listForms(0) === [], 'missing workspace has no visible forms');

$detail = $forms->getFormForWorkspace(10, $formA);
check(is_array($detail) && $detail['fields_schema'][0]['name'] === 'full_name', 'owner workspace can read editable form fields');
check(!$detail['is_published'], 'new forms are drafts and require an explicit publish action');
check($forms->setFormPublished(10, $formA, true), 'owner workspace can publish its form');
check($forms->getPublishedFormBySlug('workspace-a-form') !== null, 'published public form is retrievable');
check($forms->setFormPublished(10, $formA, false), 'owner workspace can unpublish its form');
check($forms->getPublishedFormBySlug('workspace-a-form') === null, 'unpublished form is not exposed publicly');

$newConsentVersion = $forms->getFormForWorkspace(10, $formA)['consent_version'];
check($forms->updateForm(10, $formA, 'workspace-a-form', 'Workspace A updated', 'intake', fields(), 'Updated consent text.'), 'owner workspace can update its form');
check($forms->getFormForWorkspace(10, $formA)['consent_version'] === $newConsentVersion + 1, 'changing consent text increments its version');
check(count($forms->listForms(20)) === 1 && $forms->listForms(20)[0]['public_id'] === $formB, 'workspace A edits do not affect workspace B list');

$routes = require __DIR__ . '/../api/config/routes.php';
foreach ($routes as $route) {
    if (($route['auth'] ?? false) === true) {
        check(($route['workspace_required'] ?? false) === true, 'every protected management endpoint requires an active workspace');
        check(isset($route['required_permissions']) && $route['required_permissions'] !== [], 'every protected management endpoint declares server-side permission');
    }
}

$tool = new ListFormsTool();
$deniedNoActor = $tool->execute([], new ModuleExecutionContext(10, 'usr_team_a'), new Container(['db.pdo' => $db]));
check(($deniedNoActor['error'] ?? '') === 'FORBIDDEN', 'MCP list fails closed without an authenticated actor');
$deniedNoWorkspace = $tool->execute([], new ModuleExecutionContext(0, 'usr_team_a'), new Container([
    'db.pdo' => $db,
    'auth_user' => ['user' => ['id' => 101, 'organization_id' => 10]],
    'service.authz' => new class { public function hasPermissions(array $user, array $permissions): bool { return true; } },
]));
check(($deniedNoWorkspace['error'] ?? '') === 'FORBIDDEN', 'MCP list fails closed without workspace context');
$deniedPermission = $tool->execute([], new ModuleExecutionContext(10, 'usr_team_a'), new Container([
    'db.pdo' => $db,
    'auth_user' => ['user' => ['id' => 101, 'organization_id' => 10]],
    'service.authz' => new class { public function hasPermissions(array $user, array $permissions): bool { return false; } },
]));
check(($deniedPermission['error'] ?? '') === 'FORBIDDEN', 'MCP list checks settings.view for non-root actors');

$allowAuthz = new class { public function hasPermissions(array $user, array $permissions): bool { return in_array('settings.view', $permissions, true); } };
$listA = $tool->execute([], new ModuleExecutionContext(10, 'usr_team_a'), new Container([
    'db.pdo' => $db,
    'auth_user' => ['user' => ['id' => 101, 'organization_id' => 10]],
    'service.authz' => $allowAuthz,
]));
$listB = $tool->execute([], new ModuleExecutionContext(20, 'usr_team_b'), new Container([
    'db.pdo' => $db,
    'auth_user' => ['user' => ['id' => 202, 'organization_id' => 20]],
    'service.authz' => $allowAuthz,
]));
check(($listA['count'] ?? 0) === 1 && $listA['forms'][0]['public_id'] === $formA, 'MCP lists only active workspace A forms');
check(($listB['count'] ?? 0) === 1 && $listB['forms'][0]['public_id'] === $formB, 'MCP lists only active workspace B forms');

echo "Public Forms workspace CRUD, publication, and MCP checks passed\n";
