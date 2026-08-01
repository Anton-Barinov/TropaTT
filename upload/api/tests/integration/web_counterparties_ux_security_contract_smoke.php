<?php
declare(strict_types=1);

function failCounterpartiesUx(string $message): void
{
    fwrite(STDERR, "[FAIL] web_counterparties_ux_security_contract_smoke: {$message}\n");
    exit(1);
}

function readCounterpartiesUxFile(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        failCounterpartiesUx('Cannot read file: ' . $path);
    }
    return $content;
}

function assertCounterpartiesContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        failCounterpartiesUx($message . ': ' . $needle);
    }
}

$root = dirname(__DIR__, 3);
$template = readCounterpartiesUxFile($root . '/web/view/template/page/counterparties.php');
$detailTemplate = readCounterpartiesUxFile($root . '/web/view/template/page/counterparty_detail.php');
$bindings = readCounterpartiesUxFile($root . '/web/assets/js/page-api-bindings.js');
$styles = readCounterpartiesUxFile($root . '/web/assets/css/pages.css');

foreach ([
    'id="counterpartiesSaveViewModal"',
    'id="counterpartiesSaveViewForm" novalidate',
    'id="counterpartiesCreateForm" novalidate',
    'id="counterpartiesEditForm" novalidate',
    'id="counterpartiesSaveViewTitle"',
    'id="counterpartiesDeleteViewBtn" class="btn crm-btn-secondary" type="button" disabled',
    'aria-label="Поиск по контрагентам"',
    'aria-label="Тип контрагента"',
    'id="counterpartiesBulkDeleteBtn" data-confirm-delete disabled',
    'value="legal_entity">Юрлицо',
] as $needle) {
    assertCounterpartiesContains($template, $needle, 'Counterparties template missing UX contract');
}

foreach ([
    'id="counterpartyDetailEditForm" novalidate',
    'id="counterpartyProfileEditForm" novalidate',
    'id="counterpartyContactForm" novalidate',
    'id="counterpartyRequisitesEditForm" novalidate',
    'id="counterpartyExtraEditForm" novalidate',
] as $needle) {
    assertCounterpartiesContains($detailTemplate, $needle, 'Counterparty detail template missing UX contract');
}

foreach ([
    "legal_entity: 'Юрлицо'",
    "type === 'organization' || type === 'sole_proprietor' || type === 'legal_entity'",
    "type === 'organization' || type === 'legal_entity'",
    'function confirmCounterpartiesAction(options)',
    'counterpartiesSaveViewModal',
    'counterpartiesSaveViewForm',
    'visibleCounterpartyBulkInputs',
    'node.getClientRects().length > 0',
    'updateCounterpartiesSavedViewUi',
    'Удалить выбранных контрагентов?',
    'Удалить сохраненный вид?',
    'Удалить контрагента?',
    'function confirmCounterpartyDetailAction(options)',
    'Контакт будет удален из карточки контрагента',
    "counterparty_public_id: cpPublicId",
    'task.counterparty_public_id',
    'task.company_public_id',
    'task.client_public_id',
] as $needle) {
    assertCounterpartiesContains($bindings, $needle, 'Counterparties bindings missing UX/security contract');
}

$start = strpos($bindings, 'async function renderCounterpartiesPage()');
$end = strpos($bindings, 'async function renderClientDetailPage()', $start ?: 0);
if ($start === false || $end === false || $end <= $start) {
    failCounterpartiesUx('Cannot isolate renderCounterpartiesPage');
}
$counterpartiesSource = substr($bindings, $start, $end - $start);
foreach (['window.prompt', 'window.confirm', 'crm-btn-danger-soft-soft'] as $forbidden) {
    if (str_contains($counterpartiesSource, $forbidden)) {
        failCounterpartiesUx('Counterparties page still contains forbidden pattern: ' . $forbidden);
    }
}

$detailStart = strpos($bindings, 'async function renderCounterpartyDetailPage()');
$detailEnd = strpos($bindings, 'async function renderCompaniesPage()', $detailStart ?: 0);
if ($detailStart === false || $detailEnd === false || $detailEnd <= $detailStart) {
    failCounterpartiesUx('Cannot isolate renderCounterpartyDetailPage');
}
$counterpartyDetailSource = substr($bindings, $detailStart, $detailEnd - $detailStart);
foreach (['window.prompt', 'window.confirm', 'confirm('] as $forbidden) {
    if (str_contains($counterpartyDetailSource, $forbidden)) {
        failCounterpartiesUx('Counterparty detail still contains forbidden confirm/prompt pattern: ' . $forbidden);
    }
}

foreach ([
    '.crm-counterparties-mobile-list',
    '.crm-counterparty-mobile-card',
    '.crm-counterparties-page #counterpartiesMobileList',
    '.crm-counterparties-page .crm-counterparties-table-wrap',
] as $needle) {
    assertCounterpartiesContains($styles, $needle, 'Counterparties styles missing responsive contract');
}

fwrite(STDOUT, "[OK] web_counterparties_ux_security_contract_smoke\n");
