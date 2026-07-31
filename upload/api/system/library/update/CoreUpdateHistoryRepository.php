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
        rsort($states);
        return array_values(array_filter(array_map(static fn(string $file): ?array => json_decode((string)file_get_contents($file), true) ?: null, $states)));
    }
}
