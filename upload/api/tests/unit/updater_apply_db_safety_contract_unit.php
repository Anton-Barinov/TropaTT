<?php
declare(strict_types=1);

/**
 * Contract test: UpdaterKernel::apply() must never run migrations without a
 * usable database snapshot, and must not finalize the update when migrations
 * fail or leave pending work.
 *
 * Regression guards for the database-safety hardening:
 *  - Guard 1: when the DB backup is missing/failed and pending migrations
 *    exist, apply() must abort BEFORE any schema change (a mid-way migration
 *    failure could not otherwise be undone; rollback would only restore files).
 *  - Guard 2: when migrations fail (or did not fully apply), apply() must NOT
 *    write the 'installed' local state / advance installed_core, so the update
 *    stays offered after rollback and can_rollback remains available.
 *  - Guard 3 (maintenance hold): once the file tree or the database has been
 *    mutated, a failure must leave maintenance mode ON (maintenance_held),
 *    so the CRM never comes back online with new files over an old or
 *    partially migrated database. The admin resolves it by rolling back or
 *    retrying from the updates page. Same rule applies to rollback().
 */

function updaterApplyGuardAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $source = (string)file_get_contents(dirname(__DIR__, 3) . '/updater/src/UpdaterKernel.php');
    updaterApplyGuardAssert($source !== '', 'UpdaterKernel.php source must be readable');

    // Guard 1: a failed/missing DB snapshot must block migrations when pending
    // migrations exist. The 'no pending migrations' skip is the only safe skip
    // (files-only update), so the guard must explicitly allow only that reason.
    $guard1 = 'if (!$dbBackupUsable && $this->pendingMigrations() !== []) {';
    updaterApplyGuardAssert(
        str_contains($source, $guard1),
        'apply() must abort when DB backup is unusable and migrations are pending'
    );
    updaterApplyGuardAssert(
        str_contains($source, "'no pending migrations'"),
        'apply() must treat only the "no pending migrations" skip as safe'
    );
    updaterApplyGuardAssert(
        str_contains($source, 'Apply aborted before any schema change to protect the database'),
        'apply() must keep the schema-protection rationale for the DB backup guard'
    );

    // Guard 1 must run BEFORE migrations are invoked.
    $guard1Pos = strpos($source, $guard1);
    $runMigrationsPos = strpos($source, '$migrations = $this->runMigrations($state, $logger);');
    updaterApplyGuardAssert(
        $guard1Pos !== false && $runMigrationsPos !== false && $guard1Pos < $runMigrationsPos,
        'DB backup guard must run before migrations in apply()'
    );

    // Guard 2: migration failure must block finalization. The local state write
    // ('installed') must appear AFTER the migrations guard.
    $guard2 = '($migrations[\'ok\'] ?? false) !== true || (array)($migrations[\'pending_after\'] ?? []) !== []';
    updaterApplyGuardAssert(
        str_contains($source, $guard2),
        'apply() must treat failed or partially-applied migrations as a failure'
    );
    $guard2Pos = strpos($source, $guard2);
    $installedWritePos = strpos($source, "'state' => 'installed'");
    updaterApplyGuardAssert(
        $guard2Pos !== false && $installedWritePos !== false && $guard2Pos < $installedWritePos,
        'migrations failure guard must run before the installed state is written'
    );

    // Guard 3 (apply): maintenance must NOT be unconditionally disabled on
    // failure once the file tree / DB may have been mutated. The catch block
    // must only disable maintenance when it was not held (maintenance_held
    // is true when maintenance was already on before the run OR the system
    // was mutated). Note: needles are single-quoted so $vars stay literal.
    updaterApplyGuardAssert(
        str_contains($source, '$maintenanceWasOn = is_file('),
        'apply() must remember whether maintenance was already held'
    );
    updaterApplyGuardAssert(
        str_contains($source, '$systemMutated = false;'),
        'apply() must track a $systemMutated flag (starts false)'
    );
    updaterApplyGuardAssert(
        str_contains($source, '$systemMutated = true;'),
        'apply() must set $systemMutated once the file tree can change'
    );
    $mutatedPos = strpos($source, '$systemMutated = true;');
    $applierApplyPos = strpos($source, '$applier->apply($jobId, $manifest)');
    updaterApplyGuardAssert(
        $mutatedPos !== false && $applierApplyPos !== false && $mutatedPos < $applierApplyPos,
        '$systemMutated must be set BEFORE files are applied'
    );
    updaterApplyGuardAssert(
        str_contains($source, "'maintenance_held' => \$maintenanceHeld"),
        'apply() failed state must record maintenance_held'
    );
    $catchDisable = strpos($source, 'if (!$maintenanceHeld) {', $guard2Pos);
    updaterApplyGuardAssert(
        $catchDisable !== false,
        'apply() catch must disable maintenance only when maintenance is not held'
    );
    updaterApplyGuardAssert(
        str_contains($source, 'maintenance mode stays enabled'),
        'apply() migration-failure message must state maintenance stays enabled'
    );

    // Guard 3 (rollback): a failed rollback (partially restored DB/files)
    // must also keep maintenance ON until the admin retries, using the same
    // maintenance_held computation (was-on + mutated).
    updaterApplyGuardAssert(
        str_contains($source, "'maintenance_held' => \$maintenanceHeld"),
        'rollback() failed state must record maintenance_held'
    );
    updaterApplyGuardAssert(
        substr_count($source, 'if (!$maintenanceHeld) {') >= 2,
        'both apply() and rollback() must use the maintenance_held guard'
    );

    echo "[OK] updater_apply_db_safety_contract_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] updater_apply_db_safety_contract_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
