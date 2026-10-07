<?php
declare(strict_types=1);

// Persistent bounded protocol for an SSH supervisor. This helper is CLI-only;
// it accepts no paths or shell commands from stdin and exposes no credentials.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/src/State/HostReleaseLock.php';

/** @return int process exit code */
$run = static function (): int {
    $lock = new \Updater\State\HostReleaseLock(dirname(__DIR__, 2));
    try {
        if (!$lock->acquire()) {
            fwrite(STDERR, "HOST_RELEASE_BUSY\n");
            return 1;
        }
        $write = static function (array $response): void {
            fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            fflush(STDOUT);
        };
        $write(['held' => true, 'protocol' => 1]);

        while (($line = fgets(STDIN, 4098)) !== false) {
            if (strlen($line) > 4096 || !str_ends_with($line, "\n")) {
                return 2;
            }
            try {
                $request = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return 2;
            }
            if (!is_array($request) || array_is_list($request)
                || count($request) !== 2 || !isset($request['action'], $request['request_id'])
                || !is_string($request['action']) || !is_string($request['request_id'])
                || preg_match('/\A[a-f0-9]{32}\z/D', $request['request_id']) !== 1) {
                return 2;
            }
            if ($request['action'] === 'ping') {
                $write(['held' => true, 'protocol' => 1, 'request_id' => $request['request_id']]);
                continue;
            }
            if ($request['action'] === 'release') {
                $write(['held' => true, 'protocol' => 1, 'request_id' => $request['request_id']]);
                return 0;
            }
            return 2;
        }
        return 0;
    } catch (\Throwable) {
        fwrite(STDERR, "HOST_RELEASE_GUARD_UNAVAILABLE\n");
        return 2;
    } finally {
        $lock->release();
    }
};

exit($run());
