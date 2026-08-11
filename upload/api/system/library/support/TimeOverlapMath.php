<?php
declare(strict_types=1);

namespace Api\System\Library\Support;

/**
 * Pure interval math for parallel time-tracker entries.
 *
 * A user may run several timers on different tasks at the same wall-clock
 * time. Each timer writes a worklog with an exact [started_at, ended_at]
 * interval. Summing the intervals double-counts the shared portions, so the
 * analytics must know:
 *   - union_seconds   — how long the user was actually working (no overlap),
 *   - overlap_seconds — how much of the recorded time was double-counted,
 *   - segments        — the concrete overlapping slices (for the UI
 *                       breakdown: which tasks covered each slice).
 *
 * All methods are pure (timestamps are Unix seconds), so the class is fully
 * unit-testable without a database.
 */
final class TimeOverlapMath
{
    /**
     * Analyze a set of intervals.
     *
     * @param array<int, array{key?: string, start: int, end: int}> $intervals
     *        key is an arbitrary identifier of the owning entry/task.
     *
     * @return array{
     *     union_seconds: int,
     *     overlap_seconds: int,
     *     segments: array<int, array{from: int, to: int, seconds: int, count: int, entries: array<int, string>}>
     * }
     */
    public static function analyze(array $intervals): array
    {
        $events = [];
        foreach ($intervals as $idx => $interval) {
            $start = (int)($interval['start'] ?? 0);
            $end = (int)($interval['end'] ?? 0);
            if ($end <= $start) {
                continue;
            }
            $events[] = ['t' => $start, 'delta' => 1, 'idx' => $idx];
            $events[] = ['t' => $end, 'delta' => -1, 'idx' => $idx];
        }

        if ($events === []) {
            return ['union_seconds' => 0, 'overlap_seconds' => 0, 'segments' => []];
        }

        // Sort by timestamp; on ties removals (-1) run before additions (+1)
        // so an interval ending exactly when another starts does not overlap.
        usort($events, static function (array $a, array $b): int {
            if ($a['t'] === $b['t']) {
                return $a['delta'] <=> $b['delta'];
            }
            return $a['t'] <=> $b['t'];
        });

        $union = 0;
        $overlap = 0;
        $segments = [];
        $active = [];
        $prevT = null;

        foreach ($events as $event) {
            $t = $event['t'];
            if ($prevT !== null && $t > $prevT && $active !== []) {
                $length = $t - $prevT;
                $union += $length;
                $entryKeys = array_values(array_map(
                    static fn(int $i): string => (string)($intervals[$i]['key'] ?? (string)$i),
                    array_keys($active)
                ));
                $segments[] = [
                    'from' => $prevT,
                    'to' => $t,
                    'seconds' => $length,
                    'count' => count($entryKeys),
                    'entries' => $entryKeys,
                ];
                if (count($active) > 1) {
                    // The overlap is the *excess* recorded time: every extra
                    // parallel interval double-counts this slice, so a slice
                    // with 3 running timers contributes 2x its length.
                    // This keeps the invariant overlap == recorded - union.
                    $overlap += (count($active) - 1) * $length;
                }
            }

            if ($event['delta'] === 1) {
                $active[$event['idx']] = true;
            } else {
                unset($active[$event['idx']]);
            }
            $prevT = $t;
        }

        return [
            'union_seconds' => $union,
            'overlap_seconds' => $overlap,
            'segments' => $segments,
        ];
    }

    /**
     * Total recorded length of the intervals (the naive sum — what the raw
     * worklog minutes would show before deduplication).
     *
     * @param array<int, array{start: int, end: int}> $intervals
     */
    public static function recordedSeconds(array $intervals): int
    {
        $total = 0;
        foreach ($intervals as $interval) {
            $start = (int)($interval['start'] ?? 0);
            $end = (int)($interval['end'] ?? 0);
            if ($end > $start) {
                $total += $end - $start;
            }
        }
        return $total;
    }
}
