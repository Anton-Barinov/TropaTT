<?php
declare(strict_types=1);

namespace Api\Controller\Rate;

use Api\Controller\Common\BaseController;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Security\FinancialFieldPolicy;
use Api\System\Library\Service\RateResolutionService;
use Api\Model\Rate\RateCardRepository;

/**
 * Rate management: preview, recalculate, lock/unlock (TZ 5.3-5.4, 7.3).
 */
final class RateController extends BaseController
{
    /**
     * GET /api/v1/rates/preview — diagnostic trace (TZ 7.3).
     */
    public function preview(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        $actor = $auth['user'];

        $hasFinance = $this->hasAnyFinancePerm($actor);
        if (!$hasFinance) return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);

        $input = $this->request()->allInput();
        $userPublicId = (string)($input['user_public_id'] ?? '');
        $taskPublicId = (string)($input['task_public_id'] ?? '');
        $projectPublicId = (string)($input['project_public_id'] ?? '');
        $clientPublicId = (string)($input['client_public_id'] ?? '');
        $date = (string)($input['date'] ?? gmdate('Y-m-d'));
        $activityCode = $input['activity_code'] ?? null;

        $pdo = $this->container->get('db.pdo');
        $user = (new QueryBuilder($pdo))->from('users')->where('public_id', '=', $userPublicId)->first();
        if (!$user) return $this->error('USER_NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $task = null;
        $taskId = null;
        if ($taskPublicId !== '') {
            $task = (new QueryBuilder($pdo))->from('tasks')->where('public_id', '=', $taskPublicId)->first();
            if (!$task) return $this->error('TASK_NOT_FOUND', $this->t('common/messages.not_found'), 404);
            $taskId = (int)$task['id'];
        } elseif ($projectPublicId === '' && $clientPublicId === '') {
            // TZ 7.3: either a task, or a project+client scope is required.
            return $this->error('VALIDATION', $this->t('rate/messages.preview_scope_required'), 422);
        }

        $resolver = new RateResolutionService(new RateCardRepository($pdo));
        $result = $resolver->resolve(
            (int)$user['id'],
            $taskId,
            $date,
            $activityCode,
            $projectPublicId !== '' ? $projectPublicId : null,
            $clientPublicId !== '' ? $clientPublicId : null
        );

        $policy = new FinancialFieldPolicy();
        $filtered = $policy->filterRow($result, $actor, 'rates.preview');

        // The trace carries per-kind rate values; strip any kind whose top-level
        // result the policy removed, so a view_cost-only actor cannot recover
        // bill rates (and vice versa) from the diagnostic trace (TZ 6.2/7.3).
        if (isset($filtered['trace']) && is_array($filtered['trace'])) {
            foreach (['cost', 'bill', 'payout'] as $kind) {
                if (!array_key_exists($kind, $filtered)) {
                    unset($filtered['trace'][$kind]);
                }
            }
        }

        return $this->success('RATES_PREVIEW', '', ['preview' => $filtered]);
    }

    /**
     * POST /api/v1/rates/recalculate (TZ 5.3).
     */
    public function recalculate(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $from = (string)($input['date_from'] ?? '');
        $to = (string)($input['date_to'] ?? '');
        $dryRun = !empty($input['dry_run'] ?? false);
        $userPublicId = (string)($input['user_public_id'] ?? '');

        if ($from === '' || $to === '') {
            return $this->error('VALIDATION', $this->t('rate/messages.date_range_required'), 422);
        }
        if (strtotime($to) - strtotime($from) > 366 * 86400) {
            return $this->error('VALIDATION', $this->t('rate/messages.range_too_large'), 422);
        }

        $pdo = $this->container->get('db.pdo');
        $resolver = new RateResolutionService(new RateCardRepository($pdo));

        // Count affected rows
        $qb = (new QueryBuilder($pdo))->from('work_logs')
            ->where('logged_at', '>=', $from)
            ->where('logged_at', '<=', $to . ' 23:59:59')
            ->where('rate_locked_at', 'IS', null);
        if ($userPublicId !== '') {
            $user = (new QueryBuilder($pdo))->from('users')->where('public_id', '=', $userPublicId)->first();
            if ($user) $qb->where('user_id', '=', (int)$user['id']);
        }
        $totalRows = $qb->count();
        $lockedRows = (new QueryBuilder($pdo))->from('work_logs')
            ->where('logged_at', '>=', $from)
            ->where('logged_at', '<=', $to . ' 23:59:59')
            ->where('rate_locked_at', 'IS NOT', null)
            ->count();

        if ($dryRun) {
            return $this->success('RECALCULATE_DRY_RUN', '', [
                'total_rows' => $totalRows,
                'locked_rows' => $lockedRows,
                'affected_rows' => $totalRows,
                'dry_run' => true,
            ]);
        }

        // Batch re-resolve (500 rows at a time)
        $batchSize = 500;
        $processed = 0;
        $now = gmdate('Y-m-d H:i:s');

        $batchQb = (new QueryBuilder($pdo))->from('work_logs')
            ->select(['id', 'user_id', 'task_id', 'logged_at', 'activity_code', 'public_id'])
            ->where('logged_at', '>=', $from)
            ->where('logged_at', '<=', $to . ' 23:59:59')
            ->where('rate_locked_at', 'IS', null);
        if ($userPublicId !== '') {
            $user = (new QueryBuilder($pdo))->from('users')->where('public_id', '=', $userPublicId)->first();
            if ($user) $batchQb->where('user_id', '=', (int)$user['id']);
        }
        $batchQb->limit($batchSize);

        $offset = 0;
        do {
            $rows = (clone $batchQb)->offset($offset)->get();
            if ($rows === []) break;

            foreach ($rows as $row) {
                try {
                    $d = gmdate('Y-m-d', strtotime((string)$row['logged_at']));
                    $r = $resolver->resolve((int)$row['user_id'], $row['task_id'] ? (int)$row['task_id'] : null, $d, $row['activity_code'] ?? null);
                    (new QueryBuilder($pdo))->from('work_logs')->where('id', '=', (int)$row['id'])->update([
                        'cost_rate_snapshot' => $r['cost']['rate'] ?? null,
                        'bill_rate_snapshot' => $r['bill']['rate'] ?? null,
                        'payout_rate_snapshot' => $r['payout']['rate'] ?? null,
                        'currency_code' => $r['currency_code'] ?? null,
                        'cost_source_type' => $r['cost']['source_type'] ?? null,
                        'cost_source_ref' => $r['cost']['source_ref'] ?? null,
                        'bill_source_type' => $r['bill']['source_type'] ?? null,
                        'bill_source_ref' => $r['bill']['source_ref'] ?? null,
                        'payout_source_type' => $r['payout']['source_type'] ?? null,
                        'payout_source_ref' => $r['payout']['source_ref'] ?? null,
                        'rate_resolved_at' => $now,
                        'rate_ambiguous' => $r['ambiguous'] ? 1 : 0,
                    ]);
                    $processed++;
                } catch (\Throwable $e) {
                    error_log('[RateController::recalculate] row ' . $row['public_id'] . ': ' . $e->getMessage());
                }
            }
            $offset += $batchSize;
        } while (true);

        $this->invalidateWorklogCache();
        $this->logAudit('recaculate', ['from' => $from, 'to' => $to, 'rows' => $processed]);

        return $this->success('RECALCULATED', '', [
            'processed_rows' => $processed,
            'locked_rows' => $lockedRows,
        ]);
    }

    /**
     * POST /api/v1/rates/lock (TZ 5.4).
     */
    public function lock(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $from = (string)($input['date_from'] ?? '');
        $to = (string)($input['date_to'] ?? '');
        if ($from === '' || $to === '') {
            return $this->error('VALIDATION', $this->t('rate/messages.date_range_required'), 422);
        }

        $pdo = $this->container->get('db.pdo');
        $now = gmdate('Y-m-d H:i:s');
        $updated = (new QueryBuilder($pdo))->from('work_logs')
            ->where('logged_at', '>=', $from)
            ->where('logged_at', '<=', $to . ' 23:59:59')
            ->where('rate_locked_at', 'IS', null)
            ->update(['rate_locked_at' => $now]);

        // Store locked period so new worklogs in this range are blocked too (TZ 5.4).
        $this->addLockedPeriod($pdo, $from, $to);

        $this->invalidateWorklogCache();
        $this->logAudit('rate_lock', ['from' => $from, 'to' => $to, 'locked' => $updated]);

        return $this->success('RATE_LOCKED', '', ['locked_rows' => $updated]);
    }

    /**
     * POST /api/v1/rates/unlock (TZ 5.4).
     */
    public function unlock(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $from = (string)($input['date_from'] ?? '');
        $to = (string)($input['date_to'] ?? '');
        if ($from === '' || $to === '') {
            return $this->error('VALIDATION', $this->t('rate/messages.date_range_required'), 422);
        }

        $pdo = $this->container->get('db.pdo');
        $updated = (new QueryBuilder($pdo))->from('work_logs')
            ->where('logged_at', '>=', $from)
            ->where('logged_at', '<=', $to . ' 23:59:59')
            ->where('rate_locked_at', 'IS NOT', null)
            ->update(['rate_locked_at' => null]);

        // Remove from locked periods registry.
        $this->removeLockedPeriod($pdo, $from, $to);

        $this->invalidateWorklogCache();
        $this->logAudit('rate_unlock', ['from' => $from, 'to' => $to, 'unlocked' => $updated]);

        return $this->success('RATE_UNLOCKED', '', ['unlocked_rows' => $updated]);
    }

    /**
     * GET /api/v1/rates/locks — list locked periods + auto-close status (TZ 8.10).
     */
    public function listLocks(): \Api\System\Library\Http\JsonResponse
    {
        $pdo = $this->container->get('db.pdo');

        // Auto-close settings (3.8)
        $mode = (string)($this->settingScalar($pdo, 'finance.auto_close.mode') ?? 'off');
        $lagDays = (int)($this->settingScalar($pdo, 'finance.auto_close.lag_days') ?? 5);

        // Last scheduler run of the auto-close task (visible so a missing cron
        // on the server is noticeable rather than silently breaking 15.5).
        $lastRunAt = null;
        $lastStatus = null;
        try {
            $task = (new QueryBuilder($pdo))->from('module_scheduled_tasks')
                ->select(['last_run_at', 'last_status'])
                ->where('module_name', '=', 'finance')
                ->where('task_name', '=', 'periods.auto_close')
                ->first();
            if ($task) {
                $lastRunAt = $task['last_run_at'] ?? null;
                $lastStatus = $task['last_status'] ?? null;
            }
        } catch (\Throwable) {
            // module_scheduled_tasks may not exist on fresh installs — non-fatal.
        }

        // Locked work-log days grouped into contiguous periods.
        $rows = (new QueryBuilder($pdo))
            ->from('work_logs w')
            ->select(['DATE(w.logged_at) AS d', 'COUNT(*) AS cnt'])
            ->where('w.rate_locked_at', 'IS NOT', null)
            ->groupBy('DATE(w.logged_at)')
            ->orderBy('d', 'ASC')
            ->get();

        $periods = [];
        $cur = null;
        $prev = null;
        foreach ($rows as $r) {
            $d = (string)($r['d'] ?? '');
            $cnt = (int)($r['cnt'] ?? 0);
            if ($d === '') continue;
            if ($cur === null) {
                $cur = ['from' => $d, 'to' => $d, 'row_count' => $cnt];
            } elseif ($prev !== null && (strtotime($d) - strtotime($prev)) <= 86400) {
                $cur['to'] = $d;
                $cur['row_count'] += $cnt;
            } else {
                $periods[] = $cur;
                $cur = ['from' => $d, 'to' => $d, 'row_count' => $cnt];
            }
            $prev = $d;
        }
        if ($cur !== null) {
            $periods[] = $cur;
        }

        return $this->success('RATE_LOCKS', '', [
            'locked_periods' => $periods,
            'auto_close' => ['mode' => $mode, 'lag_days' => $lagDays],
            'last_run_at' => $lastRunAt,
            'last_status' => $lastStatus,
        ]);
    }

    /**
     * Read a system-scope setting scalar value (settings.value is JSON).
     */
    private function addLockedPeriod(\PDO $pdo, string $from, string $to): void
    {
        $existing = (array)$this->settingScalar($pdo, 'finance.rate_locked_periods');
        $existing[] = ['from' => $from, 'to' => $to];
        $this->upsertSetting($pdo, 'finance.rate_locked_periods', $existing);
    }

    private function removeLockedPeriod(\PDO $pdo, string $from, string $to): void
    {
        $existing = (array)$this->settingScalar($pdo, 'finance.rate_locked_periods');
        $existing = array_values(array_filter($existing, fn($p) => $p['from'] !== $from || $p['to'] !== $to));
        $this->upsertSetting($pdo, 'finance.rate_locked_periods', $existing);
    }

    private function upsertSetting(\PDO $pdo, string $name, mixed $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        $now = gmdate('Y-m-d H:i:s');
        $exists = (new QueryBuilder($pdo))->from('settings')->where('scope', '=', 'system')->where('name', '=', $name)->first();
        if ($exists) {
            (new QueryBuilder($pdo))->from('settings')->where('scope', '=', 'system')->where('name', '=', $name)->update(['value' => $json, 'updated_at' => $now]);
        } else {
            (new QueryBuilder($pdo))->from('settings')->insert(['scope' => 'system', 'name' => $name, 'value' => $json, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /**
     * Check if a given date falls within any locked period stored in settings.
     */
    public static function isDateLocked(\PDO $pdo, string $date): bool
    {
        $row = (new QueryBuilder($pdo))->from('settings')
            ->select(['value'])
            ->where('scope', '=', 'system')
            ->where('name', '=', 'finance.rate_locked_periods')
            ->first();
        if (!$row || empty($row['value'])) return false;
        $periods = json_decode((string)$row['value'], true);
        if (!is_array($periods)) return false;
        foreach ($periods as $p) {
            if ($date >= $p['from'] && $date <= $p['to']) return true;
        }
        return false;
    }

    private function settingScalar(\PDO $pdo, string $name): mixed
    {
        $row = (new QueryBuilder($pdo))
            ->from('settings')
            ->select(['value'])
            ->where('scope', '=', 'system')
            ->where('name', '=', $name)
            ->first();
        if (!$row || ($row['value'] ?? '') === '' || $row['value'] === null) {
            return null;
        }
        $decoded = json_decode((string)$row['value'], true);
        return $decoded;
    }

    private function hasAnyFinancePerm(array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) return true;
        $perms = (array)($actor['permission_codes'] ?? []);
        $financePerms = ['finance.rate.view_own_payout', 'finance.rate.view_own_cost',
            'finance.rate.view_cost', 'finance.rate.view_bill'];
        foreach ($financePerms as $p) {
            if (in_array($p, $perms, true)) return true;
        }
        return false;
    }

    private function invalidateWorklogCache(): void
    {
        $this->invalidateCache('worklog');
        $this->invalidateCache('setting');
    }

    private function logAudit(string $action, array $ctx): void
    {
        try {
            $logger = $this->container->has('logger.json') ? $this->container->get('logger.json') : null;
            if ($logger) {
                $logger->audit(['action' => $action, 'actor_public_id' => $this->user()['user']['public_id'] ?? null] + $ctx);
            }
        } catch (\Throwable) {}
    }
}