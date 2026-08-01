<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$templatePath = $root . '/web/view/template/page/profile.php';
$bindingsPath = $root . '/web/assets/js/page-api-bindings.js';

function failProfilePageSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_profile_page_smoke: {$message}\n");
    exit(1);
}

$template = is_file($templatePath) ? file_get_contents($templatePath) : false;
$bindings = is_file($bindingsPath) ? file_get_contents($bindingsPath) : false;
if (!is_string($template) || !is_string($bindings)) {
    failProfilePageSmoke('required files not found');
}

foreach ([
    'id="profilePasswordModal"',
    'id="profileTwoFactorModal"',
    'id="profilePasswordForm"',
    'id="profileTwoFactorForm"',
    'autocomplete="current-password"',
    'autocomplete="new-password"',
] as $needle) {
    if (strpos($template, $needle) === false) {
        failProfilePageSmoke('missing profile template marker: ' . $needle);
    }
}

$profileSectionPos = strpos($bindings, 'async function renderProfilePage()');
if ($profileSectionPos === false) {
    failProfilePageSmoke('renderProfilePage not found');
}
$profileSection = substr($bindings, $profileSectionPos, 18000);
foreach (['window.prompt', 'window.alert', 'prompt(', 'alert('] as $forbidden) {
    if (strpos($profileSection, $forbidden) !== false) {
        failProfilePageSmoke('profile bindings must not use native dialog: ' . $forbidden);
    }
}

foreach ([
    'openProfileModal',
    'confirmProfileAction',
    'profilePasswordForm',
    'profileTwoFactorForm',
    'api/v1/security/sessions/revoke-others',
] as $needle) {
    if (strpos($profileSection, $needle) === false) {
        failProfilePageSmoke('missing profile bindings marker: ' . $needle);
    }
}

fwrite(STDOUT, "[OK] web_profile_page_smoke\n");
