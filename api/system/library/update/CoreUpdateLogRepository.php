<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreUpdateLogRepository
{
    public function __construct(private readonly string $storageDir)
    {
    }

    public function read(string $jobId): array
    {
        $file = $this->storageDir . '/jobs/' . basename($jobId) . '/log.jsonl';
        return is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [];
    }
}
