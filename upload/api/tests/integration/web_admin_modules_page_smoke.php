<?php declare(strict_types=1);

$root = dirname(__DIR__, 3);
$templatePath = $root . '/web/view/template/page/admin_modules.php';

function failAdminModulesSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_admin_modules_page_smoke: {$message}\n");
    exit(1);
}

$template = is_file($templatePath) ? file_get_contents($templatePath) : false;
if (!is_string($template)) {
    failAdminModulesSmoke('template file not found');
}

foreach ([
    'data-page="admin-modules"',
    'crm-admin-modules-page',
    'crm-admin-modules-table-card',
    'id="moduleTableBody"',
    'confirmModuleAction',
    "getElementById('crmConfirmModal')",
    'aria-label="Установить модуль ',
    'aria-label="Активировать модуль ',
    'aria-label="Удалить модуль ',
] as $needle) {
    if (strpos($template, $needle) === false) {
        failAdminModulesSmoke('missing expected marker: ' . $needle);
    }
}

if (strpos($template, 'confirm(') !== false) {
    failAdminModulesSmoke('native confirm() must not be used on admin modules page');
}

fwrite(STDOUT, "[OK] web_admin_modules_page_smoke\n");
