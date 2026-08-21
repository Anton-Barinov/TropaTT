<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Setting\SettingRepository;
use Api\System\Library\Config;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Database\ConnectionManager;
use PDO;

/**
 * Auto-close financial periods (TZ 15.5).
 *
 * No-arg constructor — follows the same contract as KnowledgeCronTaskHandler
 * and CycleSnapshotCronHandler: the scheduler instantiates the handler with no
 * arguments, and the handler resolves its own dependencies lazily.
 *
 * Runs daily via the scheduler (schedule '0 2 * * *'). It reads the
 * finance.auto_close.mode / finance.auto_close.lag_days settings, determines
 * which periods should be closed, and sets rate_locked_at on affected
 * work_logs rows. Idempotent — it never unlocks, only locks. Unlocking is a
 * manual, audited operation only (TZ 5.4).
 */
final class FinanceCronTaskHandler
{
    private ?PDO $pdo = null;
    private ?SettingService $settings = null;

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $basePath = dirname(__DIR__, 3);
            $config = new Config();
            $config->load($basePath . '/config/database.php', 'database');
            $connectionManager = new ConnectionManager($config);
            $this->pdo = $connectionManager->connect();
        }
        return $this->pdo;
    }

    private function getSettings(): SettingService
    {
        if ($this->settings === null) {
            $this->settings = new SettingService(new SettingRepository($this->getPdo()));
        }
        return $this->settings;
    }

    /**
     * Read a system-scope setting value (the decoded 'value' field).
     *
     * @return mixed
     */
    private function settingValue(string $name, mixed $default = null): mixed
    {
        $item = $this->getSettings()->get('system', $name);
        return $item['value'] ?? $default;
    }

    /**
     * Called by cron: finance.periods.auto_close.
     */
    public function autoClosePeriods(): void
    {
        $mode = (string)($this->settingValue('finance.auto_close.mode', 'off'));
        if ($mode === 'off') {
            return;
        }

        $lagDays = (int)($this->settingValue('finance.auto_close.lag_days', 5));
        $now = gmdate('Y-m-d');
        $threshold = gmdate('Y-m-d', strtotime("{$now} - {$lagDays} days"));

        if ($mode === 'weekly') {
            // Close the most recent complete calendar week ending on/​before the threshold.
            $lastSunday = gmdate('Y-m-d', strtotime("{$threshold} last sunday"));
            $from = gmdate('Y-m-d', strtotime("{$lastSunday} -6 days"));
            $to = $lastSunday;
        } elseif ($mode === 'monthly') {
            // Close the most recent complete calendar month ending before the threshold.
            $from = gmdate('Y-m-01', strtotime("{$threshold} first day of last month"));
            $to = gmdate('Y-m-t', strtotime($from));
        } else {
            // Unknown mode: fail-closed, never lock anything.
            error_log("[FinanceCronTaskHandler] unknown auto-close mode '{$mode}', skipping");
            return;
        }

        $lockTime = gmdate('Y-m-d H:i:s');
        $pdo = $this->getPdo();

        $updated = (new QueryBuilder($pdo))
            ->from('work_logs')
            ->where('logged_at', '>=', $from)
            ->where('logged_at', '<=', $to . ' 23:59:59')
            ->where('rate_locked_at', 'IS', null)
            ->update(['rate_locked_at' => $lockTime]);

        $this->getSettings()->set('system', 'finance.auto_close.last_run', [
            'timestamp' => $lockTime,
            'mode' => $mode,
            'from' => $from,
            'to' => $to,
            'locked_rows' => $updated,
        ]);

        if ($updated > 0) {
            error_log("[FinanceCronTaskHandler] auto-close: {$mode} period {$from}–{$to}, locked {$updated} rows");
        }
    }
}
