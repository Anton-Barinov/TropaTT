<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/system/library/support/OpenApiSpec.php';

$spec = Api\System\Library\Support\OpenApiSpec::build();
$outDir = dirname(__DIR__) . '/docs/openapi';
$outFile = $outDir . '/openapi.v1.json';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create OpenAPI output directory\n");
    exit(1);
}
$json = json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
if (file_put_contents($outFile, $json . "\n") === false) {
    fwrite(STDERR, "Cannot write OpenAPI specification\n");
    exit(1);
}
echo "[OK] Generated {$outFile}\n";
echo "Paths: " . count($spec['paths']) . "\n";
