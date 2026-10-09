<?php
declare(strict_types=1);

// CLI-only one-request protocol. The SSH command is fixed by the owner adapter;
// no path, shell command, or URL is accepted on stdin.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Updater\\';
    if (!str_starts_with($class, $prefix)) { return; }
    $relative = substr($class, strlen($prefix));
    $path = $root . '/updater/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) { require_once $path; }
});
require_once $root . '/updater/src/State/DurableHostReleaseLease.php';

$line = fgets(STDIN, 8194);
if (!is_string($line) || strlen($line) > 8192 || !str_ends_with($line, "\n")) {
    fwrite(STDOUT, "{\"ok\":false,\"error\":\"REQUEST_INVALID\"}\n");
    exit(2);
}
try {
    $request = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($request) || array_is_list($request)) {
        throw new InvalidArgumentException('REQUEST_INVALID');
    }
    $allowed = match ($request['action'] ?? null) {
        'claim' => ['action', 'run_id', 'token', 'claim_id', 'target_sha', 'purpose', 'ttl', 'manifest_sha256', 'bootstrap_intent_token'],
        'status' => ['action'],
        'renew', 'release' => ['action', 'run_id', 'token', 'generation', 'ttl'],
        'reconcile' => ['action', 'run_id', 'token', 'generation', 'expected_readback_sha256', 'decision'],
        'bind_manifest' => ['action', 'run_id', 'token', 'generation', 'job_id', 'target_sha', 'manifest_sha256'],
        default => throw new InvalidArgumentException('HOST_LEASE_ACTION_INVALID'),
    };
    if (array_diff(array_keys($request), $allowed) !== []) {
        throw new InvalidArgumentException('REQUEST_FIELDS_INVALID');
    }
    $config = require $root . '/api/config/update.php';
    $storageDir = $config['storage_dir'] ?? null;
    if (!is_string($storageDir) || $storageDir === '') {
        throw new RuntimeException('HOST_LEASE_STORAGE_CONFIG_INVALID');
    }
    $result = (new \Updater\State\DurableHostReleaseLease($root, null, $storageDir))->handle($request);
    fwrite(STDOUT, json_encode(['ok' => true, 'result' => $result],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    exit(0);
} catch (Throwable $error) {
    // Error classes/messages are machine-readable stable codes; never echo
    // request data (especially the bearer-like host lease token).
    $code = preg_match('/\A[A-Z0-9_]+\z/D', $error->getMessage()) === 1
        ? $error->getMessage() : 'HOST_LEASE_REQUEST_FAILED';
    fwrite(STDOUT, json_encode(['ok' => false, 'error' => $code], JSON_THROW_ON_ERROR) . "\n");
    exit(2);
}
