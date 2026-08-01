<?php
declare(strict_types=1);

$storageDir = dirname(__DIR__, 2) . '/storage';
$allowed = [
    $storageDir . '/.gitignore',
    $storageDir . '/.htaccess',
    $storageDir . '/.keep',
];
$allowed = array_fill_keys(array_map(static fn(string $path): string => realpath($path) ?: $path, $allowed), true);

$found = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($storageDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    $normalized = realpath($path) ?: $path;
    if (!isset($allowed[$normalized])) {
        $found[] = substr($path, strlen(dirname(__DIR__, 2)) + 1);
    }
}

sort($found);
if ($found !== []) {
    fwrite(STDERR, '[FAIL] api/storage contains runtime artifacts: ' . implode(', ', $found) . "\n");
    exit(1);
}

echo "[OK] storage_deploy_tree_clean_smoke\n";
