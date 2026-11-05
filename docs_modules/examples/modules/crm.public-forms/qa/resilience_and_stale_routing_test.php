<?php

declare(strict_types=1);

use function PublicFormsQa\{check, countRows, database, fields, service, submission};

require_once __DIR__ . '/test_support.php';

$db = database();
$db->exec("INSERT INTO projects (public_id, organization_id, title) VALUES ('prj_active', 10, 'Alpha Project')");
$db->exec("INSERT INTO users (id, public_id, full_name, is_active) VALUES (1, 'usr_active', 'Alice Active', 1)");
$db->exec("INSERT INTO organization_memberships (organization_id, user_id) VALUES (10, 1)");

$intakeItemsCreated = [];
$fakeIntakeCreator = static function (array $input, array $actor) use (&$intakeItemsCreated): array {
    $publicId = 'iin_resilience_' . bin2hex(random_bytes(6));
    $intakeItemsCreated[] = [
        'public_id' => $publicId,
        'input' => $input,
        'actor' => $actor,
    ];
    return ['public_id' => $publicId];
};

$forms = service($db, $fakeIntakeCreator);

// Test 1: Save form with valid project and assignee
$formId = $forms->saveForm(
    10,
    'resilient-form',
    'Resilient Test Form',
    'intake',
    fields(),
    'Consent text',
    'prj_active',
    'usr_active'
);
check($forms->setFormPublished(10, $formId, true), 'Form should be published');

// Now simulate that the project was deleted and assignee was deactivated
$db->exec("UPDATE projects SET deleted_at = '2026-11-05 00:00:00' WHERE public_id = 'prj_active'");
$db->exec("UPDATE users SET is_active = 0 WHERE public_id = 'usr_active'");

// Submit form - it MUST NOT fail with 503 error; it must gracefully degrade
$subData = submission('Charlie Customer', 'idemp_key_resilient_001');
$result = $forms->submit('resilient-form', $subData, '192.168.1.50');

check($result['ok'] === true, 'Submission must succeed despite stale routing targets');
check(!empty($result['intake_public_id']), 'Intake record must be linked');
check(count($intakeItemsCreated) === 1, 'One intake record created');

$createdItem = $intakeItemsCreated[0];
check(!isset($createdItem['input']['project_public_id']), 'Stale project must be omitted from intake input');
check(!isset($createdItem['input']['assignee_user_id']), 'Stale assignee must be omitted from intake input');
check(str_contains($createdItem['input']['description'], 'prj_active недоступен'), 'Notice about unavailable project must be in description');
check(str_contains($createdItem['input']['description'], 'usr_active недоступен'), 'Notice about unavailable assignee must be in description');

// Test 2: Delete form
check(!$forms->deleteForm(20, $formId), 'Workspace 20 must NOT be able to delete workspace 10 form');
check($forms->deleteForm(10, $formId), 'Workspace 10 must be able to delete its own form');
check(countRows($db, 'crm_public_forms', 'public_id = ?', [$formId]) === 0, 'Form must be deleted from database');
check(countRows($db, 'crm_public_form_submissions', 'form_id = 1') === 0, 'Form submissions must be deleted from database');

// Test 3: Check routes
$webRoutes = require __DIR__ . '/../web/config/routes.php';
check(isset($webRoutes['module-public-forms']), 'module-public-forms route must exist');
check(isset($webRoutes['public-forms']), 'public-forms route must exist');
check(isset($webRoutes['public-form']), 'public-form route must exist');

$apiRoutes = require __DIR__ . '/../api/config/routes.php';
$hasDeleteRoute = false;
foreach ($apiRoutes as $r) {
    if (in_array('DELETE', $r['methods'] ?? [], true) && ($r['route'] ?? '') === '/manage/forms/{public_id}') {
        $hasDeleteRoute = true;
        break;
    }
}
check($hasDeleteRoute, 'DELETE /manage/forms/{public_id} route must exist');

// Test 4: Manifest category
$manifest = json_decode(file_get_contents(__DIR__ . '/../manifest.json'), true);
check(($manifest['category'] ?? '') === 'integration', 'Category must be integration');
check(isset($manifest['assets']['js_routes']['public-forms']), 'js_routes must include public-forms');

echo "Public Forms resilience, stale routing fallback, deletion, and route contract checks passed\n";
