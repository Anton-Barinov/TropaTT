<?php
declare(strict_types=1);

namespace Updater\Log;

final class UpdateLogger
{
    public function __construct(private readonly string $storageDir, private readonly string $jobId)
    {
    }

    public function info(string $step, string $message, array $context = []): void
    {
        $this->write('info', $step, $message, $context);
    }

    public function error(string $step, string $message, array $context = []): void
    {
        $this->write('error', $step, $message, $context);
    }

    private function write(string $level, string $step, string $message, array $context): void
    {
        $dir = $this->storageDir . '/jobs/' . basename($this->jobId);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/log.jsonl', json_encode([
            'created_at' => gmdate('c'),
            'level' => $level,
            'step' => $step,
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }
}
