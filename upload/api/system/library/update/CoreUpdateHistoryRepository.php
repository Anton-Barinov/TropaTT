<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreUpdateHistoryRepository
{
    public function __construct(private readonly string $storageDir)
    {
    }

    public function list(): array
    {
        $states = glob($this->storageDir . '/jobs/*/state.json') ?: [];
        // Newest first by mtime: string-sorting job ids would put old
        // prefixed ids (upd_e2e_...) above current ones forever.
        usort($states, static fn(string $a, string $b): int => @filemtime($b) <=> @filemtime($a));
        return array_values(array_filter(array_map(static fn(string $file): ?array => json_decode((string)file_get_contents($file), true) ?: null, $states)));
    }
}
