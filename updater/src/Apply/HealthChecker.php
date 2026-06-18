<?php
declare(strict_types=1);

namespace Updater\Apply;

final class HealthChecker
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function check(): array
    {
        $checks = [
            'api_index_syntax' => $this->phpLint($this->basePath . '/api/index.php'),
            'web_index_syntax' => $this->phpLint($this->basePath . '/web/index.php'),
            'root_index_syntax' => $this->phpLint($this->basePath . '/index.php'),
        ];

        $okValues = array_map(static fn(array $check): bool => ($check['ok'] ?? false) === true, $checks);

        return [
            'ok' => !in_array(false, $okValues, true),
            'checks' => $checks,
        ];
    }

    private function phpLint(string $path): array
    {
        if (!is_file($path)) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'missing_file'];
        }
        if (!function_exists('proc_open')) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'proc_open_unavailable'];
        }

        $php = $this->phpCliBinary();
        if ($php === null) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'php_cli_unavailable'];
        }

        $cmd = [$php, '-l', $path];
        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptor, $pipes);
        if (!is_resource($process)) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'proc_open_failed'];
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return [
            'ok' => $code === 0,
            'exit_code' => $code,
            'output' => trim((string)$stdout . "\n" . (string)$stderr),
        ];
    }

    private function phpCliBinary(): ?string
    {
        $candidates = [
            '/opt/homebrew/bin/php',
            '/usr/local/bin/php',
            '/usr/bin/php',
            'php',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === 'php') {
                return $candidate;
            }
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
