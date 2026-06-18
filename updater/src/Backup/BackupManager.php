<?php
declare(strict_types=1);

namespace Updater\Backup;

class BackupManager
{
    public function describe(): array
    {
        return ['implemented' => false, 'reason' => 'Real apply is disabled until dry-run review is complete.'];
    }
}
