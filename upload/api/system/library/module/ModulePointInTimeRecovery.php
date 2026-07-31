<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModulePointInTimeRecovery
{
    /** @var array<int, array{timestamp: string, path: string}> */
    private array $recoveryPoints = [];

    public function __construct(
        private readonly ModuleBackupManager $backupManager,
        private readonly ModuleUninstallDataHandler $dataHandler,
    ) {}

    public function restoreToTime(string $moduleName, string $timestamp): bool
    {
        return $this->dataHandler->restoreFromArchive($moduleName);
    }

    /** @return array<int, array{timestamp: string, path: string}> */
    public function getRecoveryPoints(string $moduleName): array
    {
        $allBackups = $this->backupManager->listBackups();
        $points = [];

        foreach ($allBackups as $backup) {
            if (str_starts_with($backup['name'], $moduleName . '_')) {
                $points[] = [
                    'timestamp' => date('c', $backup['created']),
                    'path' => $backup['name'],
                ];
            }
        }

        return $points;
    }

    /** @return array{files: array<string>, sql: array<string>} */
    public function dryRun(string $moduleName, string $timestamp): array
    {
        $points = $this->getRecoveryPoints($moduleName);
        $result = ['files' => [], 'sql' => []];

        foreach ($points as $point) {
            if ($point['timestamp'] <= $timestamp) {
                $result['files'][] = $point['path'];
            }
        }

        return $result;
    }
}
