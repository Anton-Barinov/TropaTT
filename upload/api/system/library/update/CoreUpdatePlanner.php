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
            $planData['center_channel'] = $this->centerChannelDiagnosis($planData);
        }
        return [
            'current' => $current,
            'plan' => $planData,
            'raw' => $plan,
            'unknown_local_core' => ($current['state'] ?? '') === 'unknown_local_core',
        ];
    }

    /**
     * Read the channel state behind an empty plan.
     *
     * An empty plan (no recommended package, target == current) is legitimate
     * when this installation really is on the channel head. It is ALSO exactly
     * what an update center returns to every installation when nothing is
     * published to that channel — "scan/build/publish" stopped, a channel was
     * reset, or the deployment serving the request has no channel state at all.
     * Both look the same on the updates page ("Обновлений нет", green), so an
     * admin cannot tell "you are up to date" from "the center has nothing to
     * offer anyone": installations silently stop receiving builds while the
     * page keeps reporting a healthy state.
     *
     * Best-effort: an unreachable channel endpoint degrades to 'unknown' and
     * never fails the update check itself.
     *
     * @param array<string,mixed> $planData plan payload as returned by the center
     * @return array<string,mixed>
     */
    private function centerChannelDiagnosis(array $planData): array
    {
        return $this->channelStateFromResponses($planData, $this->client->channel());
    }

    /**
     * Pure mapping from the plan + channel envelope to a diagnosable state.
     *
     * 'ok'            — the plan carries a package, or the channel has a
     *                   published build, so an empty plan means "up to date".
     * 'empty_channel' — no package and no published build in that channel: the
     *                   center cannot offer anything to anyone.
     * 'unknown'       — the channel request itself failed.
     *
     * @param array<string,mixed> $planData
     * @param array<string,mixed> $channelResponse
     * @return array<string,mixed>
     */
    private function channelStateFromResponses(array $planData, array $channelResponse): array
    {
        $channel = trim((string)($planData['channel'] ?? ''));
        if (($channelResponse['ok'] ?? false) !== true) {
            return [
                'state' => 'unknown',
                'channel' => $channel,
                'latest_build' => null,
                'error' => (string)($channelResponse['error'] ?? 'update_center_unavailable'),
            ];
        }
        $data = is_array($channelResponse['data'] ?? null) ? $channelResponse['data'] : [];
        $latest = trim((string)($data['latest_build'] ?? ''));
        if ($channel === '') {
            $channel = trim((string)($data['channel'] ?? ''));
        }
        $hasPackage = is_array($planData['recommended_package'] ?? null);
        return [
            'state' => (!$hasPackage && $latest === '') ? 'empty_channel' : 'ok',
            'channel' => $channel,
            'latest_build' => $latest !== '' ? $latest : null,
            'plan_has_package' => $hasPackage,
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
        // Same date+sequence — compare suffixes.
        //
        // A "-bridge" build is special: it carries the same tree WITHOUT
        // modules/** and exists only to move legacy installations (those still
        // protecting modules/**) past their stale protection. So the order
        // between a bridge and its own base build is inverted with respect to
        // the legacy "empty < any suffix" rule:
        //  - a bridge target is never an upgrade (over its base build it would
        //    merely withhold modules/** that the install already has, and over
        //    the bridge itself it is the same build);
        //  - the base build IS an upgrade over the bridge, because the base is
        //    the very build that ships the modules. Without this the check
        //    right after a bridge reported "no updates" and the installation
        //    stayed without modules until an unrelated new build number
        //    appeared, while the updates page claimed it was up to date.
        if ($tgtParts['suffix'] === 'bridge') {
            return false;
        }
        if ($curParts['suffix'] === 'bridge' && $tgtParts['suffix'] === '') {
            return true;
        }
        // Legacy rule for every other suffix pair (empty < any suffix).
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
