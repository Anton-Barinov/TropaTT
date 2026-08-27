<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreUpdatePlanner
{
    public function __construct(private readonly CoreUpdateClient $client, private readonly CoreVersion $version)
    {
    }

    public function check(): array
    {
        $current = $this->version->current();
        $currentBuild = (string)($current['core_build'] ?? '0');
        if ($currentBuild === '') {
            $currentBuild = '0';
        }
        $plan = $this->client->plan($currentBuild);
        if (($plan['ok'] ?? false) !== true) {
            return [
                'current' => $current,
                'plan' => [
                    'update_available' => null,
                    'current_build' => $currentBuild,
                    'target_build' => null,
                    'recommended_package' => null,
                    'summary' => null,
                    'update_center' => $this->updateCenterError($plan),
                    'error' => (string)($plan['error'] ?? 'update_center_unavailable'),
                    'message' => (string)($plan['message'] ?? 'Update center is unavailable'),
                ],
                'raw' => $plan,
                'unknown_local_core' => ($current['state'] ?? '') === 'unknown_local_core',
            ];
        }
        $planData = $plan['data'] ?? null;
        // Compute update_available: true when target build is newer than current.
        // Build format: YYYYMMDD.NNN[-suffix]. Bridge builds (-bridge, -bootstrap)
        // are created AFTER the base build and are considered newer. When the
        // current build has a suffix the base build must NOT trigger an update.
        if (is_array($planData)) {
            $targetBuild = (string)($planData['target_build'] ?? '');
            $planData['update_available'] = $this->isNewerBuild($currentBuild, $targetBuild);
        }
        return [
            'current' => $current,
            'plan' => $planData,
            'raw' => $plan,
            'unknown_local_core' => ($current['state'] ?? '') === 'unknown_local_core',
        ];
    }

    private function updateCenterError(array $response): array
    {
        return [
            'url' => (string)($response['url'] ?? ''),
            'ok' => false,
            'status' => (int)($response['status'] ?? 0),
            'error' => (string)($response['error'] ?? 'update_center_unavailable'),
            'message' => (string)($response['message'] ?? 'Update center is unavailable'),
        ];
    }

    /**
     * Determine if $target is a newer build than $current.
     *
     * Build format: YYYYMMDD.NNN[-suffix]
     * - Compare date portion (YYYYMMDD) first
     * - Then compare sequence number (NNN)
     * - If date+sequence are equal, suffix matters:
     *   empty suffix < any suffix (e.g. 20260827.002 < 20260827.002-bridge)
     * - Bridge/bootstrap builds are created AFTER the base build
     */
    private function isNewerBuild(string $current, string $target): bool
    {
        if ($target === '' || $target === '0') {
            return false;
        }
        if ($target === $current) {
            return false;
        }
        // Extract date.sequence and suffix
        $curParts = $this->parseBuild($current);
        $tgtParts = $this->parseBuild($target);
        // Compare date
        if ($tgtParts['date'] !== $curParts['date']) {
            return $tgtParts['date'] > $curParts['date'];
        }
        // Same date — compare sequence number
        if ($tgtParts['seq'] !== $curParts['seq']) {
            return $tgtParts['seq'] > $curParts['seq'];
        }
        // Same date+sequence — compare suffix (empty < any suffix)
        return $curParts['suffix'] === '' && $tgtParts['suffix'] !== '';
    }

    private function parseBuild(string $build): array
    {
        // 20260827.002-bridge => date=20260827, seq=2, suffix=bridge
        // 20260827.002        => date=20260827, seq=2, suffix=''
        if (preg_match('/^(\d{8})\.(\d+)(?:-(.+))?$/', $build, $m)) {
            return [
                'date' => (int)$m[1],
                'seq' => (int)$m[2],
                'suffix' => $m[3] ?? '',
            ];
        }
        return ['date' => 0, 'seq' => 0, 'suffix' => $build];
    }
}
