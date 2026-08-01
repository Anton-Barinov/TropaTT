<?php
declare(strict_types=1);
http_response_code(500);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'code' => 'TEST_HTTP_500']);
