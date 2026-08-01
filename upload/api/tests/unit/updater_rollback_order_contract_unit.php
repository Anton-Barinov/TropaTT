<?php
declare(strict_types=1);

/**
 * Contract test: UpdaterKernel::rollback() must restore the database BEFORE
 * restoring files.
 *
 * Regression guard for commit c9351fe. A self-updating update package ships
 * the updater itself, so the file backup contains the PRE-update updater
 * files - including an older DatabaseBackupManager stub without restore().
 * Restoring files first would then make PHP autoload that overwritten stub
 * when the DB restore runs afterwards, fataling with
 * "Call to undefined method DatabaseBackupManager::restore()". Restoring the
 * DB first runs against the current post-update code on disk.
 */

function updaterRollbackAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $source = (string)file_get_contents(dirname(__DIR__, 3) . '/updater/src/UpdaterKernel.php');
    updaterRollbackAssert($source !== '', 'UpdaterKernel.php source must be readable');

    // Both calls must exist.
    $dbRestoreCall = 'restoreDatabaseBackup($backupId, $logger)';
    updaterRollbackAssert(
        str_contains($source, $dbRestoreCall),
        'rollback() must call restoreDatabaseBackup()'
    );
    $fileRollbackCall = 'RollbackManager($this->basePath, $this->storageDir))->rollback($backupId)';
    updaterRollbackAssert(
        str_contains($source, $fileRollbackCall),
        'rollback() must call RollbackManager->rollback()'
    );

    // DB restore must appear BEFORE the file rollback in the source.
    $dbPos = strpos($source, $dbRestoreCall);
    $filePos = strpos($source, $fileRollbackCall);
    updaterRollbackAssert(
        $dbPos !== false && $filePos !== false && $dbPos < $filePos,
        'restoreDatabaseBackup() must run before RollbackManager->rollback() in rollback()'
    );

    // The explanatory comment that documents why the order matters must stay.
    updaterRollbackAssert(
        str_contains($source, 'Restore the database BEFORE restoring files'),
        'rollback() must keep the ordering rationale comment'
    );

    echo "[OK] updater_rollback_order_contract_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] updater_rollback_order_contract_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
