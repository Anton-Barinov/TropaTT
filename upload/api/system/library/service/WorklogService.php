<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Task\TaskRepository;
use Api\Model\Team\TeamRepository;
use Api\Model\User\UserManagementRepository;
use Api\Model\Worklog\WorklogRepository;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\TimeOverlapMath;
use Api\System\Library\Support\Ulid;
use Api\System\Library\Service\ExternalUserService;
use Api\System\Library\Service\RateResolutionService;
use Api\Model\Rate\RateCardRepository;

final class WorklogService
{
    public function __construct(
        private readonly WorklogRepository $worklogs,
        private readonly TaskRepository $tasks,
        private readonly UserManagementRepository $userManagement,
        private readonly TeamRepository $teamRepo,
        private readonly JsonLogger $logger,
        private readonly ?ExternalUserService $externalUsers = null,
        private ?RateResolutionService $rateResolver = null,
    ) {
    }

    private function getRateResolver(): RateResolutionService
    {
        if ($this->rateResolver === null) {
            $repo = new RateCardRepository($this->worklogs->getPdo());
            $this->rateResolver = new RateResolutionService($repo);
        }
        return $this->rateResolver;
    }

    /**
     * Get the list of user IDs that the actor can see worklogs for.
     * Includes the actor, all users created by them (recursively),
     * and all members of teams where the actor is the manager.
     * Root users get an empty array — no user-level filter is applied.
     */
    /** Resolve the internal numeric user ID from the actor array. */
    private function resolveActorId(array $actor): int
    {
        if (!empty($actor['id'])) {
            return (int)$actor['id'];
        }
        $publicId = (string)($actor['public_id'] ?? '');
        if ($publicId !== '') {
            $user = $this->worklogs->findUserByPublicId($publicId);
            if ($user && isset($user['id'])) {
                return (int)$user['id'];
            }
        }
        return 0;
    }

    private function getVisibleUserIds(array $actor): array
    {
        $actorId = $this->resolveActorId($actor);
        $isRoot = (bool)($actor['is_root'] ?? false);

        if ($isRoot) {
            return [];
        }

        // Sentinel: unresolvable non-root actor must not bypass the visibility filter.
        // Returning [-1] ensures whereIn() is applied but matches nothing.
        if ($actorId <= 0) {
            return [-1];
        }

        // Hierarchy: actor + all descendants (users created by them recursively)
        $hierarchyIds = $this->userManagement->descendantIds($actorId);

        // Teams: members of teams where the actor is the manager
        $teamMemberIds = $this->teamRepo->findMemberIdsByManager($actorId);

        // Merge, deduplicate, return
        return array_values(array_unique(array_merge($hierarchyIds, $teamMemberIds)));
    }

    public function list(array $filters, array $actor): array
    {
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $isRoot = (bool)($actor['is_root'] ?? false);
        [$items, $total, $page, $limit] = $this->worklogs->list(
            $filters,
            $visibleUserIds,
            $isRoot
        );

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function create(array $input, array $actor)
    {
        $taskId = null;
        if (!empty($input['task_public_id'])) {
            $task = $this->tasks->findByPublicId((string)$input['task_public_id']);
            if (!$task) {
                return 'TASK_NOT_FOUND';
            }
            if (!$this->canAccessTask($task, $actor)) {
                return 'FORBIDDEN';
            }
            $taskId = (int)$task['id'];
        }

        $publicId = Ulid::generate('wlg');
        $now = gmdate('Y-m-d H:i:s');
        $userId = $this->resolveActorId($actor);
        if (!empty($input['user_public_id']) && (bool)($actor['is_root'] ?? false)) {
            $targetUser = $this->worklogs->findUserByPublicId((string)$input['user_public_id']);
            if ($targetUser) {
                $userId = (int)$targetUser['id'];
            }
        }
        $this->worklogs->create([
            'public_id' => $publicId,
            'user_id' => $userId,
            'task_id' => $taskId,
            'minutes_spent' => (int)$input['minutes_spent'],
            'note' => trim((string)($input['note'] ?? '')),
            'logged_at' => (string)($input['logged_at'] ?? $now),
            'started_at' => $this->parseIntervalTime($input['started_at'] ?? null),
            'ended_at' => $this->parseIntervalTime($input['ended_at'] ?? null),
            'activity_code' => $input['activity_code'] ?? null,
            'created_at' => $now,
        ]);

        // --- Rate snapshot (TZ 5.1) ---
        try {
            $resolution = $this->getRateResolver()->resolve(
                $userId,
                $taskId,
                gmdate('Y-m-d', strtotime((string)($input['logged_at'] ?? $now))),
                $input['activity_code'] ?? null
            );
            $snapshot = [
                'cost_rate_snapshot' => $resolution['cost']['rate'] ?? null,
                'bill_rate_snapshot' => $resolution['bill']['rate'] ?? null,
                'payout_rate_snapshot' => $resolution['payout']['rate'] ?? null,
                'currency_code' => $resolution['currency_code'] ?? null,
                'cost_source_type' => $resolution['cost']['source_type'] ?? null,
                'cost_source_ref' => $resolution['cost']['source_ref'] ?? null,
                'bill_source_type' => $resolution['bill']['source_type'] ?? null,
                'bill_source_ref' => $resolution['bill']['source_ref'] ?? null,
                'payout_source_type' => $resolution['payout']['source_type'] ?? null,
                'payout_source_ref' => $resolution['payout']['source_ref'] ?? null,
                'rate_resolved_at' => $now,
                'rate_ambiguous' => $resolution['ambiguous'] ? 1 : 0,
                'client_public_id' => $resolution['client_public_id'] ?? null,
                'project_public_id' => $resolution['project_public_id'] ?? null,
            ];
            $this->worklogs->updateByPublicId($publicId, $snapshot);
        } catch (\Throwable $e) {
            error_log('[WorklogService::create] Rate resolution failed: ' . $e->getMessage());
            // Record is saved with NULL snapshots — can be fixed by recalculate.
        }

        $this->logger->audit([
            'action' => 'worklog_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'worklog',
            'entity_public_id' => $publicId,
        ]);

        return $this->worklogs->findByPublicId($publicId);
    }

    public function get(string $publicId, array $actor): ?array
    {
        $item = $this->worklogs->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        if (!$this->canAccessWorklog($item, $actor)) {
            return null;
        }

        return $item;
    }

    public function update(string $publicId, array $input, array $actor)
    {
        $existing = $this->worklogs->findByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        if (!$this->canAccessWorklog($existing, $actor)) {
            return 'FORBIDDEN';
        }

        $set = [];
        if (array_key_exists('minutes_spent', $input)) {
            $set['minutes_spent'] = (int)$input['minutes_spent'];
        }
        if (array_key_exists('note', $input)) {
            $set['note'] = trim((string)$input['note']);
        }
        if (array_key_exists('logged_at', $input)) {
            $set['logged_at'] = (string)$input['logged_at'];
        }
        if (array_key_exists('started_at', $input) || array_key_exists('ended_at', $input)) {
            $set['started_at'] = $this->parseIntervalTime($input['started_at'] ?? null);
            $set['ended_at'] = $this->parseIntervalTime($input['ended_at'] ?? null);
        }
        if (array_key_exists('activity_code', $input)) {
            $set['activity_code'] = $input['activity_code'] !== null && $input['activity_code'] !== ''
                ? (string)$input['activity_code']
                : null;
        }
        if (array_key_exists('task_public_id', $input)) {
            if ($input['task_public_id'] === null || $input['task_public_id'] === '') {
                $set['task_id'] = null;
            } else {
                $task = $this->tasks->findByPublicId((string)$input['task_public_id']);
                if (!$task) {
                    return 'TASK_NOT_FOUND';
                }
                if (!$this->canAccessTask($task, $actor)) {
                    return 'FORBIDDEN';
                }
                $set['task_id'] = (int)$task['id'];
            }
        }

        if ($set !== []) {
            $needsSnapshot = array_key_exists('task_id', $set)
                || array_key_exists('logged_at', $set)
                || array_key_exists('activity_code', $set);

            // Check rate_locked_at before re-snapshotting (TZ 5.1)
            if ($needsSnapshot && !empty($existing['rate_locked_at'])) {
                return 'RATE_PERIOD_LOCKED';
            }

            $this->worklogs->updateByPublicId($publicId, $set);

            // Re-resolve rate snapshot if relevant fields changed
            if ($needsSnapshot) {
                try {
                    $currentTaskId = array_key_exists('task_id', $set)
                        ? $set['task_id']
                        : ($existing['task_id'] ?? null);
                    $currentLoggedAt = array_key_exists('logged_at', $set)
                        ? $set['logged_at']
                        : ($existing['logged_at'] ?? gmdate('Y-m-d H:i:s'));
                    $currentActivityCode = array_key_exists('activity_code', $set)
                        ? $set['activity_code']
                        : ($existing['activity_code'] ?? null);

                    $resolution = $this->getRateResolver()->resolve(
                        (int)($existing['user_id'] ?? 0),
                        $currentTaskId ? (int)$currentTaskId : null,
                        gmdate('Y-m-d', strtotime((string)$currentLoggedAt)),
                        $currentActivityCode
                    );
                    $snapshot = [
                        'cost_rate_snapshot' => $resolution['cost']['rate'] ?? null,
                        'bill_rate_snapshot' => $resolution['bill']['rate'] ?? null,
                        'payout_rate_snapshot' => $resolution['payout']['rate'] ?? null,
                        'currency_code' => $resolution['currency_code'] ?? null,
                        'cost_source_type' => $resolution['cost']['source_type'] ?? null,
                        'cost_source_ref' => $resolution['cost']['source_ref'] ?? null,
                        'bill_source_type' => $resolution['bill']['source_type'] ?? null,
                        'bill_source_ref' => $resolution['bill']['source_ref'] ?? null,
                        'payout_source_type' => $resolution['payout']['source_type'] ?? null,
                        'payout_source_ref' => $resolution['payout']['source_ref'] ?? null,
                        'rate_resolved_at' => gmdate('Y-m-d H:i:s'),
                        'rate_ambiguous' => $resolution['ambiguous'] ? 1 : 0,
                        'client_public_id' => $resolution['client_public_id'] ?? null,
                        'project_public_id' => $resolution['project_public_id'] ?? null,
                    ];
                    $this->worklogs->updateByPublicId($publicId, $snapshot);
                } catch (\Throwable $e) {
                    error_log('[WorklogService::update] Rate resolution failed: ' . $e->getMessage());
                }
            }
        }

        $this->logger->audit([
            'action' => 'worklog_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'worklog',
            'entity_public_id' => $publicId,
            'changes' => $set,
        ]);

        return $this->worklogs->findByPublicId($publicId);
    }

    public function delete(string $publicId, array $actor): bool|string
    {
        $existing = $this->worklogs->findByPublicId($publicId);
        if (!$existing) {
            return false;
        }
        if (!$this->canAccessWorklog($existing, $actor)) {
            return 'FORBIDDEN';
        }

        $ok = $this->worklogs->deleteByPublicId($publicId);
        if ($ok) {
            $this->logger->audit([
                'action' => 'worklog_deleted',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'worklog',
                'entity_public_id' => $publicId,
            ]);
        }

        return $ok;
    }

    private function canAccessWorklog(array $worklog, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $visibleUserIds = $this->getVisibleUserIds($actor);
        return in_array((int)($worklog['user_id'] ?? 0), $visibleUserIds, true);
    }

    private function canAccessTask(array $task, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $actorId = $this->resolveActorId($actor);
        if ($actorId <= 0) {
            return false;
        }

        // RLS: external executors can only create worklogs for tasks in their granted projects
        if (!empty((int)($actor['is_external'] ?? 0))) {
            if ($this->externalUsers) {
                $isExecutor = $this->externalUsers->getExternalRole($actorId) === ExternalUserService::ROLE_EXECUTOR;
                if ($isExecutor) {
                    return $this->externalUsers->hasExecutorProjectAccess($actorId, (int)($task['project_id'] ?? 0));
                }
            }
            return false;
        }

        return (int)($task['creator_user_id'] ?? 0) === $actorId
            || (int)($task['assignee_user_id'] ?? 0) === $actorId
            || (int)($task['project_creator_user_id'] ?? 0) === $actorId
            || (int)($task['project_manager_user_id'] ?? 0) === $actorId
            || (int)($task['project_team_manager_user_id'] ?? 0) === $actorId
            || in_array($actorId, $this->decodeTeamMemberIds($task['project_team_member_user_ids'] ?? null), true);
    }

    /** @return int[] */
    private function decodeTeamMemberIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $value): bool => $value > 0)));
    }

    /**
     * @param mixed $val
     */
    private function nullableFloat(mixed $val): ?float
    {
        if ($val === null || $val === '' || $val === false) {
            return null;
        }
        return (float)$val;
    }

    /**
     * Parse an ISO-8601 / MySQL timestamp into a normalized UTC string
     * ('Y-m-d H:i:s'). Returns null for empty values.
     */
    private function parseIntervalTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime((string)$value);
        if ($ts === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $ts);
    }

    /** Convert a stored UTC 'Y-m-d H:i:s' value to Unix seconds. */
    private function toEpoch(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/[zZ]$/', $raw) || preg_match('/[+-]\d{2}:?\d{2}$/', $raw)) {
            $ts = strtotime($raw);
        } else {
            $ts = strtotime($raw . ' UTC');
        }

        return $ts === false ? null : $ts;
    }

    /**
     * Aggregate raw worklog rows into per user+day recorded/unique/overlap
     * minutes. Entries without a usable interval (legacy timers and manual
     * entries) cannot be de-duplicated and simply count as both recorded and
     * unique.
     *
     * @param array<int, array> $rows rows with user_public_id, day, minutes_spent, started_at, ended_at
     * @return array<string, array{recorded: int, unique: int, overlap: int, has_intervals: bool}> keyed by "user_public_id|day"
     */
    private function aggregateIntervals(array $rows): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            $key = (string)($row['user_public_id'] ?? '') . '|' . (string)($row['day'] ?? '');
            $minutes = max(0, (int)($row['minutes_spent'] ?? 0));
            $start = $this->toEpoch($row['started_at'] ?? null);
            $end = $this->toEpoch($row['ended_at'] ?? null);
            if (!isset($byKey[$key])) {
                $byKey[$key] = ['recorded' => 0, 'unique' => 0, 'overlap' => 0, 'has_intervals' => false, 'intervals' => []];
            }
            $byKey[$key]['recorded'] += $minutes;
            if ($start !== null && $end !== null && $end > $start) {
                $byKey[$key]['has_intervals'] = true;
                $byKey[$key]['intervals'][] = ['start' => $start, 'end' => $end];
            } else {
                $byKey[$key]['unique'] += $minutes;
            }
        }

        $result = [];
        foreach ($byKey as $key => $data) {
            $analysis = $data['intervals'] !== []
                ? TimeOverlapMath::analyze($data['intervals'])
                : ['union_seconds' => 0, 'overlap_seconds' => 0];
            $result[$key] = [
                'recorded' => $data['recorded'],
                'unique' => $data['unique'] + (int)round($analysis['union_seconds'] / 60),
                'overlap' => (int)round($analysis['overlap_seconds'] / 60),
                'has_intervals' => $data['has_intervals'],
            ];
        }

        return $result;
    }

    /**
     * Merge the overlap-aware aggregates into SQL aggregate rows. Rows without
     * any interval data keep unique == recorded (legacy behaviour preserved).
     */
    private function mergeIntervalAggregation(array $sqlRows, array $aggregates): array
    {
        foreach ($sqlRows as &$row) {
            $key = (string)($row['user_public_id'] ?? '') . '|' . (string)($row['day'] ?? '');
            $row['recorded_minutes'] = (int)($row['total_minutes'] ?? 0);
            $over = $aggregates[$key] ?? null;
            if ($over !== null) {
                $row['unique_minutes'] = $over['unique'];
                $row['overlap_minutes'] = $over['overlap'];
                $row['has_intervals'] = $over['has_intervals'];
            } else {
                $row['unique_minutes'] = (int)($row['total_minutes'] ?? 0);
                $row['overlap_minutes'] = 0;
                $row['has_intervals'] = false;
            }
        }
        unset($row);

        return $sqlRows;
    }

    public function summary(array $filters, array $actor): array
    {
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $teamPublicId = (string)($filters['team_public_id'] ?? '');
        $rows = $this->worklogs->summaryByDay($filters, $visibleUserIds, $actorIsRoot, $teamPublicId ?: null);
        $aggregates = $this->aggregateIntervals(
            $this->worklogs->rowsForPeriod($filters, $visibleUserIds, $actorIsRoot, $teamPublicId ?: null)
        );

        return ['items' => $this->mergeIntervalAggregation($rows, $aggregates)];
    }

    public function earnings(array $filters, array $actor): array
    {
        if (!empty($filters['expanded'])) {
            return $this->earningsExpanded($filters, $actor);
        }

        $visibleUserIds = $this->getVisibleUserIds($actor);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $teamPublicId = (string)($filters['team_public_id'] ?? '');

        // Fetch per-row data with snapshot rates (TZ 2.9)
        $rows = $this->worklogs->earningsRowsForPeriod($filters, $visibleUserIds, $actorIsRoot, $teamPublicId ?: null);

        // Group by user + day for overlap distribution
        $byUserDay = [];
        foreach ($rows as $row) {
            $key = ($row['user_public_id'] ?? '') . '|' . ($row['day'] ?? '');
            if (!isset($byUserDay[$key])) {
                $byUserDay[$key] = [
                    'user_public_id' => $row['user_public_id'],
                    'user_login' => $row['user_login'],
                    'user_full_name' => $row['user_full_name'],
                    'day' => $row['day'],
                    'rows' => [],
                ];
            }
            $byUserDay[$key]['rows'][] = $row;
        }

        // Compute per-row billable minutes and amounts (TZ 2.9)
        $result = [];
        foreach ($byUserDay as $group) {
            $groupRows = $group['rows'];

            // Separate rows with intervals from legacy (no interval) rows
            $intervalEntries = [];
            $legacyMinutes = 0;
            $totalRecorded = 0;

            foreach ($groupRows as $idx => $r) {
                $m = max(0, (int)($r['minutes_spent'] ?? 0));
                $totalRecorded += $m;
                $start = $this->toEpoch($r['started_at'] ?? null);
                $end = $this->toEpoch($r['ended_at'] ?? null);
                if ($start !== null && $end !== null && $end > $start) {
                    $intervalEntries[] = ['key' => (string)$idx, 'start' => $start, 'end' => $end];
                } else {
                    $legacyMinutes += $m;
                }
            }

            // Compute overlap analysis
            $analysis = $intervalEntries !== []
                ? TimeOverlapMath::analyze($intervalEntries)
                : ['union_seconds' => 0, 'overlap_seconds' => 0, 'segments' => []];

            $totalUnionSeconds = $analysis['union_seconds'];
            $totalOverlapSeconds = $analysis['overlap_seconds'];

            // Distribute union seconds to rows proportionally (TZ 2.9: equal split per segment)
            $rowBillableSeconds = [];
            foreach ($groupRows as $idx => $r) {
                $rowBillableSeconds[(string)$idx] = 0;
                $start = $this->toEpoch($r['started_at'] ?? null);
                $end = $this->toEpoch($r['ended_at'] ?? null);
                if ($start === null || $end === null || $end <= $start) {
                    // Legacy rows keep their full minutes
                    $rowBillableSeconds[(string)$idx] = max(0, (int)($r['minutes_spent'] ?? 0)) * 60;
                }
            }

            foreach (($analysis['segments'] ?? []) as $seg) {
                $entries = $seg['entries'] ?? [];
                $count = count($entries);
                if ($count === 0) continue;
                $share = $seg['seconds'] / $count;
                foreach ($entries as $entryIdx) {
                    $rowBillableSeconds[(string)$entryIdx] += $share;
                }
            }

            // Compute daily totals from per-row amounts
            $dailyCostAmount = 0.0;
            $dailyBillAmount = 0.0;
            $dailyPayoutAmount = 0.0;
            $dailyCostRate = null;
            $dailyBillRate = null;
            $dailyPayoutRate = null;
            $costRates = [];
            $billRates = [];
            $payoutRates = [];
            $currency = null;
            $anyLocked = false;
            $anyAmbiguous = false;

            foreach ($groupRows as $idx => $r) {
                $seconds = $rowBillableSeconds[(string)$idx] ?? 0;
                $hours = $seconds / 3600;
                if (($r['rate_locked_at'] ?? null) !== null) $anyLocked = true;
                if (!empty($r['rate_ambiguous'])) $anyAmbiguous = true;
                // Snapshot first; historical rows without a snapshot
                // (rate_resolved_at IS NULL) fall back to the live user rate
                // so pre-migration data still reports money (TZ 5.1).
                $costSnap = $this->nullableFloat($r['cost_rate_snapshot'] ?? null)
                    ?? $this->nullableFloat($r['cost_rate'] ?? null);
                $billSnap = $this->nullableFloat($r['bill_rate_snapshot'] ?? null)
                    ?? $this->nullableFloat($r['bill_rate'] ?? null);
                $payoutSnap = $this->nullableFloat($r['payout_rate_snapshot'] ?? null)
                    ?? $this->nullableFloat($r['payout_rate'] ?? null);

                if ($costSnap !== null) {
                    $dailyCostAmount += round($hours * $costSnap, 2);
                    $costRates[] = $costSnap;
                }
                if ($billSnap !== null) {
                    $dailyBillAmount += round($hours * $billSnap, 2);
                    $billRates[] = $billSnap;
                }
                if ($payoutSnap !== null) {
                    $dailyPayoutAmount += round($hours * $payoutSnap, 2);
                    $payoutRates[] = $payoutSnap;
                }
                if ($currency === null && ($r['currency_code'] ?? null) !== null) {
                    $currency = (string)$r['currency_code'];
                }
            }

            $dailyCostRate = $costRates !== [] ? round(array_sum($costRates) / count($costRates), 2) : null;
            $dailyBillRate = $billRates !== [] ? round(array_sum($billRates) / count($billRates), 2) : null;
            $dailyPayoutRate = $payoutRates !== [] ? round(array_sum($payoutRates) / count($payoutRates), 2) : null;

            $uniqueMinutes = (int)round($totalUnionSeconds / 60) + $legacyMinutes;
            $overlapMinutes = (int)round($totalOverlapSeconds / 60);

            $result[] = [
                'user_public_id' => $group['user_public_id'],
                'user_login' => $group['user_login'],
                'user_full_name' => $group['user_full_name'],
                'day' => $group['day'],
                'total_minutes' => $totalRecorded,
                'recorded_minutes' => $totalRecorded,
                'unique_minutes' => $uniqueMinutes,
                'overlap_minutes' => $overlapMinutes,
                'cost_rate' => $dailyCostRate,
                'bill_rate' => $dailyBillRate,
                'payout_rate' => $dailyPayoutRate,
                'payout_rate_snapshot' => $dailyPayoutRate,
                'cost_amount' => round($dailyCostAmount, 2),
                'bill_amount' => round($dailyBillAmount, 2),
                'payout_amount' => round($dailyPayoutAmount, 2),
                'currency_code' => $currency,
                'rate_ambiguous' => $anyAmbiguous ? 1 : 0,
                'period_locked' => $anyLocked ? 1 : 0,
            ];
        }

        // Sort by day DESC, full_name ASC
        usort($result, static function (array $a, array $b): int {
            $dayCmp = $b['day'] <=> $a['day'];
            if ($dayCmp !== 0) return $dayCmp;
            return ($a['user_full_name'] ?? '') <=> ($b['user_full_name'] ?? '');
        });

        return ['items' => $result];
    }

    /**
     * Expanded earnings breakdown by user + day + client + project + activity (TZ 7.4, 8.7).
     *
     * Reuses the same overlap distribution as earnings() (TZ 2.9): billable
     * seconds are computed per row, then aggregated into one slice per
     * (user, day, client, project, activity) so the report can be expanded
     * without double-counting overlapping timers.
     */
    public function earningsExpanded(array $filters, array $actor): array
    {
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $teamPublicId = (string)($filters['team_public_id'] ?? '');

        $rows = $this->worklogs->earningsRowsForPeriod($filters, $visibleUserIds, $actorIsRoot, $teamPublicId ?: null);

        // Group by user + day so overlap distribution runs per user-day.
        $byUserDay = [];
        foreach ($rows as $row) {
            $key = ($row['user_public_id'] ?? '') . '|' . ($row['day'] ?? '');
            $byUserDay[$key]['rows'][] = $row;
        }

        // Compute per-row billable seconds (TZ 2.9).
        $items = [];
        foreach ($byUserDay as $group) {
            $groupRows = $group['rows'];
            $intervalEntries = [];
            foreach ($groupRows as $idx => $r) {
                $start = $this->toEpoch($r['started_at'] ?? null);
                $end = $this->toEpoch($r['ended_at'] ?? null);
                if ($start !== null && $end !== null && $end > $start) {
                    $intervalEntries[] = ['key' => (string)$idx, 'start' => $start, 'end' => $end];
                }
            }
            $analysis = $intervalEntries !== [] ? TimeOverlapMath::analyze($intervalEntries) : ['segments' => []];

            $billable = [];
            foreach ($groupRows as $idx => $r) {
                $m = max(0, (int)($r['minutes_spent'] ?? 0));
                $start = $this->toEpoch($r['started_at'] ?? null);
                $end = $this->toEpoch($r['ended_at'] ?? null);
                $billable[(string)$idx] = ($start === null || $end === null || $end <= $start) ? $m * 60 : 0;
            }
            foreach (($analysis['segments'] ?? []) as $seg) {
                $entries = $seg['entries'] ?? [];
                $count = count($entries);
                if ($count === 0) continue;
                $share = $seg['seconds'] / $count;
                foreach ($entries as $entryIdx) {
                    $billable[(string)$entryIdx] += $share;
                }
            }
            foreach ($groupRows as $idx => $r) {
                $items[] = ['row' => $r, 'billable_seconds' => $billable[(string)$idx] ?? 0];
            }
        }

        // Aggregate into slices.
        $slices = [];
        foreach ($items as $item) {
            $r = $item['row'];
            $key = ($r['user_public_id'] ?? '') . '|' . ($r['day'] ?? '')
                . '|' . ($r['client_public_id'] ?? '') . '|' . ($r['project_public_id'] ?? '') . '|' . ($r['activity_code'] ?? '');
            if (!isset($slices[$key])) {
                $slices[$key] = [
                    'user_public_id' => $r['user_public_id'],
                    'user_login' => $r['user_login'],
                    'user_full_name' => $r['user_full_name'],
                    'day' => $r['day'],
                    'client_public_id' => $r['client_public_id'] ?? null,
                    'client_title' => $r['client_title'] ?? null,
                    'project_public_id' => $r['project_public_id'] ?? null,
                    'project_title' => $r['project_title'] ?? null,
                    'activity_code' => $r['activity_code'] ?? null,
                    'rows' => [],
                ];
            }
            $slices[$key]['rows'][] = $item;
        }

        $result = [];
        foreach ($slices as $slice) {
            $recorded = 0;
            $billableSeconds = 0;
            $costAmount = 0.0;
            $billAmount = 0.0;
            $payoutAmount = 0.0;
            $costRates = [];
            $billRates = [];
            $payoutRates = [];
            $costSource = null;
            $billSource = null;
            $payoutSource = null;
            $costSourceRef = null;
            $billSourceRef = null;
            $payoutSourceRef = null;
            $ambiguous = false;
            $locked = null;
            $snapshotMissing = false;
            $currency = null;

            foreach ($slice['rows'] as $item) {
                $r = $item['row'];
                $recorded += max(0, (int)($r['minutes_spent'] ?? 0));
                $billableSeconds += $item['billable_seconds'];
                $hours = $item['billable_seconds'] / 3600;

                $costSnap = $this->nullableFloat($r['cost_rate_snapshot'] ?? null) ?? $this->nullableFloat($r['cost_rate'] ?? null);
                $billSnap = $this->nullableFloat($r['bill_rate_snapshot'] ?? null) ?? $this->nullableFloat($r['bill_rate'] ?? null);
                $payoutSnap = $this->nullableFloat($r['payout_rate_snapshot'] ?? null) ?? $this->nullableFloat($r['payout_rate'] ?? null);

                if ($costSnap !== null) {
                    $costAmount += round($hours * $costSnap, 2);
                    $costRates[] = $costSnap;
                }
                if ($billSnap !== null) {
                    $billAmount += round($hours * $billSnap, 2);
                    $billRates[] = $billSnap;
                }
                if ($payoutSnap !== null) {
                    $payoutAmount += round($hours * $payoutSnap, 2);
                    $payoutRates[] = $payoutSnap;
                }

                if ($costSource === null && ($r['cost_source_type'] ?? null) !== null) {
                    $costSource = $r['cost_source_type'];
                    $costSourceRef = $r['cost_source_ref'] ?? null;
                }
                if ($billSource === null && ($r['bill_source_type'] ?? null) !== null) {
                    $billSource = $r['bill_source_type'];
                    $billSourceRef = $r['bill_source_ref'] ?? null;
                }
                if ($payoutSource === null && ($r['payout_source_type'] ?? null) !== null) {
                    $payoutSource = $r['payout_source_type'];
                    $payoutSourceRef = $r['payout_source_ref'] ?? null;
                }

                if (!empty($r['rate_ambiguous'])) $ambiguous = true;
                if (($r['rate_locked_at'] ?? null) !== null) $locked = $r['rate_locked_at'];
                if (($r['rate_resolved_at'] ?? null) === null) $snapshotMissing = true;
                if ($currency === null && ($r['currency_code'] ?? null) !== null) $currency = (string)$r['currency_code'];
            }

            $result[] = [
                'user_public_id' => $slice['user_public_id'],
                'user_login' => $slice['user_login'],
                'user_full_name' => $slice['user_full_name'],
                'day' => $slice['day'],
                'client_public_id' => $slice['client_public_id'],
                'client_title' => $slice['client_title'],
                'project_public_id' => $slice['project_public_id'],
                'project_title' => $slice['project_title'],
                'activity_code' => $slice['activity_code'],
                'total_minutes' => $recorded,
                'recorded_minutes' => $recorded,
                'unique_minutes' => (int)round($billableSeconds / 60),
                'overlap_minutes' => 0,
                'cost_rate' => $costRates !== [] ? round(array_sum($costRates) / count($costRates), 2) : null,
                'bill_rate' => $billRates !== [] ? round(array_sum($billRates) / count($billRates), 2) : null,
                'payout_rate' => $payoutRates !== [] ? round(array_sum($payoutRates) / count($payoutRates), 2) : null,
                'cost_amount' => round($costAmount, 2),
                'bill_amount' => round($billAmount, 2),
                'payout_amount' => round($payoutAmount, 2),
                'cost_source_type' => $costSource,
                'cost_source_ref' => $costSourceRef,
                'bill_source_type' => $billSource,
                'bill_source_ref' => $billSourceRef,
                'payout_source_type' => $payoutSource,
                'payout_source_ref' => $payoutSourceRef,
                'rate_ambiguous' => $ambiguous ? 1 : 0,
                'rate_locked_at' => $locked,
                'period_locked' => $locked !== null ? 1 : 0,
                'snapshot_missing' => $snapshotMissing ? 1 : 0,
                'currency_code' => $currency,
            ];
        }

        usort($result, static function (array $a, array $b): int {
            $dayCmp = $b['day'] <=> $a['day'];
            if ($dayCmp !== 0) return $dayCmp;
            $nameCmp = ($a['user_full_name'] ?? '') <=> ($b['user_full_name'] ?? '');
            if ($nameCmp !== 0) return $nameCmp;
            return ($a['client_title'] ?? '') <=> ($b['client_title'] ?? '');
        });

        return ['items' => $result];
    }

    public function taskSummaryByUser(string $taskPublicId, array $actor): ?array
    {
        $task = $this->tasks->findByPublicId($taskPublicId);
        if (!$task) {
            return null;
        }
        if (!$this->canAccessTask($task, $actor)) {
            return null;
        }
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        return $this->worklogs->taskSummary($taskPublicId, $visibleUserIds, $actorIsRoot);
    }

    public function matrix(array $filters, array $actor): array
    {
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $from = (string)($filters['from'] ?? '');
        $to = (string)($filters['to'] ?? '');
        $userPublicId = (string)($filters['user_public_id'] ?? '');
        $teamPublicId = (string)($filters['team_public_id'] ?? '');
        $projectPublicId = (string)($filters['project_public_id'] ?? '');

        // Resolve team members to public IDs before the matrix query
        $teamUserPublicIds = null;
        if (!empty($teamPublicId)) {
            $team = (new QueryBuilder($this->worklogs->getPdo()))
                ->from('teams')
                ->select(['member_user_ids'])
                ->where('public_id', '=', $teamPublicId)
                ->first();
            if ($team && isset($team['member_user_ids'])) {
                $raw = $team['member_user_ids'];
                $ids = [];
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $v) {
                            $iv = (int)$v;
                            if ($iv > 0) $ids[] = $iv;
                        }
                    }
                } elseif (is_array($raw)) {
                    foreach ($raw as $v) {
                        $iv = (int)$v;
                        if ($iv > 0) $ids[] = $iv;
                    }
                }
                if ($ids !== []) {
                    $ids = array_values(array_unique($ids));
                    $pubRows = (new QueryBuilder($this->worklogs->getPdo()))
                        ->from('users')
                        ->select(['public_id'])
                        ->whereIn('id', $ids)
                        ->get();
                    $teamUserPublicIds = array_map(static fn(array $row): string => (string)$row['public_id'], $pubRows);
                }
            }
        }

        $rows = $this->worklogs->matrixForPeriod($from, $to, $userPublicId ?: null, $projectPublicId ?: null, $teamUserPublicIds, $visibleUserIds, $actorIsRoot);

        // Build matrix: [day][user_public_id] = total_minutes
        $matrix = [];
        $userSet = [];
        $dayTotals = [];
        foreach ($rows as $row) {
            $day = $row['day'];
            $uid = $row['user_public_id'];
            $mins = (int)$row['total_minutes'];
            if (!isset($matrix[$day])) $matrix[$day] = [];
            $matrix[$day][$uid] = $mins;
            $userSet[$uid] = true;
            $dayTotals[$day] = ($dayTotals[$day] ?? 0) + $mins;
        }
        $userSetKeys = array_keys($userSet);

        // Overlap-aware unique time: replace each cell with the union of the
        // user's exact timer intervals for that day. Entries without an
        // interval (legacy timers, manual entries) keep their recorded minutes
        // — they cannot be de-duplicated.
        $aggregates = $this->aggregateIntervals($this->worklogs->rowsForMatrixPeriod(
            $from,
            $to,
            $userPublicId ?: null,
            $projectPublicId ?: null,
            $teamUserPublicIds,
            $visibleUserIds,
            $actorIsRoot
        ));
        foreach ($matrix as $day => $dayData) {
            foreach ($dayData as $uid => $mins) {
                $key = $uid . '|' . $day;
                if (isset($aggregates[$key])) {
                    $matrix[$day][$uid] = $aggregates[$key]['unique'];
                }
            }
        }
        $dayTotals = [];
        foreach ($matrix as $day => $dayData) {
            $total = 0;
            foreach ($dayData as $mins) {
                $total += $mins;
            }
            $dayTotals[$day] = $total;
        }

        // Generate date range
        $dates = [];
        if ($from && $to) {
            $start = new \DateTime($from);
            $end = new \DateTime($to);
            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($start, $interval, $end->modify('+1 day'));
            foreach ($period as $dt) {
                $dates[] = $dt->format('Y-m-d');
            }
            $end->modify('-1 day');
        }

        // Determine user list
        $users = [];
        if ($userPublicId) {
            $user = $this->worklogs->findUserByPublicId($userPublicId);
            if ($user) {
                $users[] = [
                    'id' => (int)($user['id'] ?? 0),
                    'public_id' => $user['public_id'],
                    'login' => $user['login'],
                    'full_name' => $user['full_name'],
                ];
            }
        } else {
            $allUsers = $this->worklogs->activeUsers();
            foreach ($allUsers as $u) {
                $pid = $u['public_id'];
                // Filter by team if needed
                if ($teamUserPublicIds !== null) {
                    if (!in_array($pid, $teamUserPublicIds)) {
                        continue;
                    }
                }
                // If project is selected, only include users with time in that project
                if ($projectPublicId && !in_array($pid, $userSetKeys)) {
                    continue;
                }
                $users[] = [
                    'id' => (int)$u['id'],
                    'public_id' => $pid,
                    'login' => $u['login'],
                    'full_name' => $u['full_name'],
                ];
            }
        }

        // Visibility: non-root users should only see users from their visible set
        if (!$actorIsRoot && $visibleUserIds !== []) {
            $users = array_values(array_filter($users, static fn(array $u): bool =>
                in_array($u['id'], $visibleUserIds, true)
            ));
        }

        $userTotals = [];
        foreach ($users as $u) {
            $userTotals[$u['public_id']] = 0;
        }
        foreach ($matrix as $day => $dayData) {
            foreach ($dayData as $uid => $mins) {
                if (isset($userTotals[$uid])) {
                    $userTotals[$uid] += $mins;
                }
            }
        }

        // Scope the team/project option lists to the actor's access so the
        // matrix response never exposes org-wide names to non-root users.
        $actorId = $this->resolveActorId($actor);
        $accessibleTeamPublicIds = $actorIsRoot
            ? []
            : $this->teamRepo->listAccessiblePublicIdsForUser($actorId);
        $teams = $this->worklogs->listTeams($actorIsRoot, $accessibleTeamPublicIds);
        $projects = $this->worklogs->listProjects($actorIsRoot, $actorId, $accessibleTeamPublicIds);

        // Filter teams based on active context (user/project selections)
        if (!empty($userPublicId) || !empty($projectPublicId) || $userSetKeys !== []) {
            $filteredTeams = [];
            $userIdsForTeams = $userSetKeys;
            if (!empty($userPublicId)) {
                $userIdsForTeams = [$userPublicId];
            }
            // Get teams that contain at least one active user
            foreach ($teams as $team) {
                $teamRow = (new QueryBuilder($this->worklogs->getPdo()))
                    ->from('teams')
                    ->select(['member_user_ids'])
                    ->where('public_id', '=', $team['public_id'])
                    ->first();
                if ($teamRow && !empty($teamRow['member_user_ids'])) {
                    $raw = $teamRow['member_user_ids'];
                    $memberIds = [];
                    if (is_string($raw) && $raw !== '') {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $memberIds = $decoded;
                        }
                    } elseif (is_array($raw)) {
                        $memberIds = $raw;
                    }
                    $memberPubIds = [];
                    if ($memberIds !== []) {
                        $idList = array_values(array_unique(array_filter(array_map('intval', $memberIds), static fn(int $v): bool => $v > 0)));
                        if ($idList !== []) {
                            $pubRows = (new QueryBuilder($this->worklogs->getPdo()))
                                ->from('users')
                                ->select(['public_id'])
                                ->whereIn('id', $idList)
                                ->get();
                            $memberPubIds = array_map(static fn(array $r): string => (string)$r['public_id'], $pubRows);
                        }
                    }
                    $found = false;
                    foreach ($userIdsForTeams as $uid) {
                        if (in_array($uid, $memberPubIds, true)) {
                            $found = true;
                            break;
                        }
                    }
                    if ($found) $filteredTeams[] = $team;
                }
            }
            $teams = $filteredTeams;
        }

        // Filter projects based on active context
        if (!empty($userPublicId) || !empty($teamPublicId) || $userSetKeys !== []) {
            $projectIdsInMatrix = [];
            if ($rows !== []) {
                // Get unique projects from the worklog data
                $projectQb = (new QueryBuilder($this->worklogs->getPdo()))
                    ->from('work_logs w')
                    ->join('tasks t', 't.id', '=', 'w.task_id')
                    ->select(['DISTINCT p.public_id', 'p.title'])
                    ->join('projects p', 'p.id', '=', 't.project_id');
                if (!empty($filters['from'])) $projectQb->where('w.logged_at', '>=', (string)$filters['from']);
                if (!empty($filters['to'])) $projectQb->where('w.logged_at', '<=', (string)$filters['to']);
                if (!empty($userPublicId)) {
                    $projectQb->join('users u', 'u.id', '=', 'w.user_id')->where('u.public_id', '=', $userPublicId);
                }
                if (!empty($teamPublicId)) {
                    $memberPubIds = $teamUserPublicIds ?? [];
                    if ($memberPubIds) $projectQb->join('users u2', 'u2.id', '=', 'w.user_id')->whereIn('u2.public_id', $memberPubIds);
                }
                // Object-level authorization: never re-derive projects from
                // worklogs of users the actor cannot see. The minutes rows are
                // already visibility-filtered; apply the same scope here.
                if (!$actorIsRoot && $visibleUserIds !== []) {
                    $projectQb->join('users u_vis', 'u_vis.id', '=', 'w.user_id')->whereIn('u_vis.id', $visibleUserIds);
                }
                $projectQb->orderBy('p.title', 'ASC');
                $projects = $projectQb->get();
            } else {
                $projects = [];
            }
        }

        return [
            'dates' => $dates,
            'users' => $users,
            'matrix' => $matrix,
            'day_totals' => $dayTotals,
            'user_totals' => $userTotals,
            'teams' => $teams,
            'projects' => $projects,
        ];
    }

    public function detail(string $day, string $userPublicId, ?string $projectPublicId, array $actor): array
    {
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $rows = $this->worklogs->detailByDayUser($day, $userPublicId, $projectPublicId, $visibleUserIds, $actorIsRoot);

        $recorded = 0;
        $legacy = 0;
        $intervals = [];
        $entryTitles = [];
        foreach ($rows as $index => $row) {
            $minutes = max(0, (int)$row['minutes_spent']);
            $recorded += $minutes;
            $start = $this->toEpoch($row['started_at'] ?? null);
            $end = $this->toEpoch($row['ended_at'] ?? null);
            if ($start !== null && $end !== null && $end > $start) {
                $entryTitles[(string)$index] = (string)($row['task_title'] ?? '');
                $intervals[] = ['key' => (string)$index, 'start' => $start, 'end' => $end];
            } else {
                $legacy += $minutes;
            }
        }

        $analysis = $intervals !== []
            ? TimeOverlapMath::analyze($intervals)
            : ['union_seconds' => 0, 'overlap_seconds' => 0, 'segments' => []];
        $unique = $legacy + (int)round($analysis['union_seconds'] / 60);
        $overlap = (int)round($analysis['overlap_seconds'] / 60);

        $segments = [];
        foreach ($analysis['segments'] as $segment) {
            // Only genuinely overlapping slices are reported (count > 1).
            if (($segment['count'] ?? 0) <= 1) {
                continue;
            }
            $tasks = [];
            foreach ($segment['entries'] ?? [] as $entryIndex) {
                $title = $entryTitles[$entryIndex] ?? '';
                if ($title !== '' && !in_array($title, $tasks, true)) {
                    $tasks[] = $title;
                }
            }
            $segments[] = [
                'from' => gmdate('Y-m-d H:i:s', (int)$segment['from']),
                'to' => gmdate('Y-m-d H:i:s', (int)$segment['to']),
                'seconds' => (int)$segment['seconds'],
                'count' => (int)$segment['count'],
                'tasks' => $tasks,
            ];
        }

        return [
            'items' => $rows,
            'total_minutes' => $recorded,
            'recorded_minutes' => $recorded,
            'unique_minutes' => $unique,
            'overlap_minutes' => $overlap,
            'has_intervals' => $intervals !== [],
            'segments' => $segments,
            'day' => $day,
            'user_public_id' => $userPublicId,
        ];
    }
}
