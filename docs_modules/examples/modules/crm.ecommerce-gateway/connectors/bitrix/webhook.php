<?php
/**
 * Public webhook entry point: `/tropatt/v1/webhook`.
 *
 * The module's install routine adds a URL rewrite rule that routes this path to
 * this file, so the CRM can push status changes without any component.
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Tropatt\Crm\WebhookHandler;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-LiteSpeed-Cache-Control: no-cache');

if (!Loader::includeModule('tropatt.crm')) {
    http_response_code(500);
    echo json_encode(array('error' => 'Module tropatt.crm is not installed'));

    return;
}

$rawBody = file_get_contents('php://input');

list($status, $payload) = WebhookHandler::handle($_SERVER, (string)$rawBody);

http_response_code($status);
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
