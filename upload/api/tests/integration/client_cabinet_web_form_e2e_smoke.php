<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $webIndex = dirname(__DIR__, 2) . '/../web/index.php';
    assertTrue(is_file($webIndex), 'Web index.php must exist');

    $_GET = ['route' => 'client-cabinet'];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/index.php?route=client-cabinet',
        'SCRIPT_NAME' => '/index.php',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'crm-web-e2e-smoke/1.0',
    ];

    ob_start();
    require $webIndex;
    $html = (string)ob_get_clean();

    assertTrue($html !== '', 'Rendered client-cabinet html must not be empty');
    assertTrue(str_contains($html, 'id="clientCabinetFilterSearch"'), 'Filter search input must be rendered');
    assertTrue(str_contains($html, 'id="clientCabinetFilterType"'), 'Filter type selector must be rendered');
    assertTrue(str_contains($html, 'id="clientCabinetFilterStatus"'), 'Filter status input must be rendered');
    assertTrue(str_contains($html, 'id="clientCabinetCompactMode"'), 'Compact mode toggle must be rendered');
    assertTrue(str_contains($html, 'name="client_type"'), 'Client type field must be rendered');
    assertTrue(str_contains($html, 'data-client-type-group="sole_proprietor,legal_entity"'), 'Type-dependent fields must be rendered');
    assertTrue(str_contains($html, 'name="tax_inn"'), 'Tax INN field must be rendered');
    assertTrue(str_contains($html, 'name="bank_account"'), 'Bank account field must be rendered');
    assertTrue(str_contains($html, 'name="extra_attributes_text"'), 'extra_attributes text field must be rendered');
    assertTrue(str_contains($html, 'data-form-error-summary'), 'Inline form error summary container must be rendered');

    $bindingsJs = dirname(__DIR__, 2) . '/../web/assets/js/page-api-bindings.js';
    assertTrue(is_file($bindingsJs), 'page-api-bindings.js must exist');
    $js = (string)file_get_contents($bindingsJs);
    assertTrue(str_contains($js, 'function validateClientPayload(payload, mode)'), 'Client-side payload validation function must exist');
    assertTrue(str_contains($js, 'renderFormErrors(form, errors)'), 'Inline form errors renderer must exist');
    assertTrue(str_contains($js, 'filter_search'), 'Client cabinet filter query sync must exist');
    assertTrue(str_contains($js, 'crm-compact-table'), 'Compact mode behavior must exist');

    echo "[OK] Client cabinet web form e2e smoke passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Client cabinet web form e2e smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
