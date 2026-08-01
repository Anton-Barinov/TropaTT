<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = dirname(__DIR__, 3);
    $textUtils = (string)file_get_contents($root . '/web/assets/js/text-utils.js');
    $errorUtils = (string)file_get_contents($root . '/web/assets/js/error-utils.js');
    $listUtils = (string)file_get_contents($root . '/web/assets/js/list-utils.js');
    $footer = (string)file_get_contents($root . '/web/view/template/common/footer.php');
    $br1 = (string)file_get_contents($root . '/web/assets/js/br1.js');
    $bindings = (string)file_get_contents($root . '/web/assets/js/page-api-bindings.js');

    assertTrue(str_contains($textUtils, 'window.CRM.text'), 'text-utils.js must expose window.CRM.text compatibility module');
    assertTrue(str_contains($errorUtils, 'window.CRM.errors'), 'error-utils.js must expose window.CRM.errors module');
    assertTrue(str_contains($listUtils, 'window.CRM.lists'), 'list-utils.js must expose window.CRM.lists module');
    assertTrue(str_contains($footer, 'assets/js/text-utils.js'), 'footer must load text-utils.js');
    assertTrue(str_contains($footer, 'assets/js/error-utils.js'), 'footer must load error-utils.js');
    assertTrue(str_contains($footer, 'assets/js/list-utils.js'), 'footer must load list-utils.js');
    assertTrue(strpos($footer, 'assets/js/text-utils.js') < strpos($footer, 'assets/js/br1.js'), 'text-utils.js must load before br1.js');
    assertTrue(strpos($footer, 'assets/js/text-utils.js') < strpos($footer, 'assets/js/page-api-bindings.js'), 'text-utils.js must load before page-api-bindings.js');
    assertTrue(strpos($footer, 'assets/js/error-utils.js') < strpos($footer, 'assets/js/page-api-bindings.js'), 'error-utils.js must load before page-api-bindings.js');
    assertTrue(strpos($footer, 'assets/js/list-utils.js') < strpos($footer, 'assets/js/page-api-bindings.js'), 'list-utils.js must load before page-api-bindings.js');
    assertTrue(str_contains($br1, 'window.CRM.text.escapeHtml'), 'br1 escapeHtml must delegate to shared text helper');
    assertTrue(str_contains($bindings, 'window.CRM.text.safeText'), 'page-api-bindings safeText must delegate to shared text helper');
    assertTrue(str_contains($bindings, 'window.CRM.text.pluralRu'), 'page-api-bindings pluralRu must delegate to shared text helper');
    assertTrue(str_contains($bindings, 'window.CRM.errors.toUiResult'), 'page-api-bindings tryRequest must delegate to shared error helper');
    assertTrue(str_contains($bindings, 'window.CRM.lists.tableBody'), 'page-api-bindings table body target lookup must delegate to shared list helper');

    echo "OK\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
