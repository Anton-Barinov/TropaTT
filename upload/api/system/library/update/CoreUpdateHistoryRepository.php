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
        UpdaterBridge::registerUpdaterAutoloader(dirname($this->storageDir, 2));
        $states = glob($this->storageDir . '/jobs/*/state.json') ?: [];
        usort($states, static function (string $a, string $b): int {
            $diff = \Updater\State\JobState::resolveJobTimestamp($b) <=> \Updater\State\JobState::resolveJobTimestamp($a);
            if ($diff !== 0) {
                return $diff;
            }
            return @filemtime($b) <=> @filemtime($a);
        });
        return array_values(array_filter(array_map(static fn(string $file): ?array => json_decode((string)file_get_contents($file), true) ?: null, $states)));
    }
}
