<?php
declare(strict_types=1);

// SEC-002: Block direct web access
if (PHP_SAPI !== 'cli' && ($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}


return [
    'base_prefix' => '/api',
    'version_prefix' => '/api/v1',
    'internal_prefix' => '/internal',
];
