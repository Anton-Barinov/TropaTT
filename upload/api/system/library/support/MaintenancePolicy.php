<?php
declare(strict_types=1);

/**
 * Single source of truth for what stays reachable while core-update
 * maintenance mode is held.
 *
 * Two front controllers enforce maintenance mode (`api/index.php` and
 * `web/index.php`) and each used to carry its own inline list. They drifted:
 * the navigation badge poller requests `api/v1/chats/unread-count` next to the
 * notification counters, the API list covered notifications but not the chat
 * counter, and every poll answered 503 while an update was being applied. The
 * web controller had the mirror-image gap.
 *
 * Keep the decision here, in one place with its own unit test, so the two
 * entry points can never disagree again. The function is dependency-free
 * (no autoloader, no database) because it runs before both are bootstrapped.
 */

if (!function_exists('tropatt_maintenance_policy_allows')) {
    /**
     * Everything that stays reachable during an updater/maintenance hold.
     *
     * Login and the updates page stay reachable so a failed update can be
     * retried or rolled back from the browser without shell access — the
     * shared-hosting recovery path. The polling reads are self-scoped and
     * cheap, so the CRM chrome does not break while the update runs.
     *
     * @param string $path   routed path (web route name or api route)
     * @param bool   $strict release-pipeline snapshot: only read-only status
     */
    function tropatt_maintenance_policy_allows(string $path, string $method = 'GET', bool $strict = false): bool
    {
        $path = trim($path, '/');
        if ($path === '') {
            return false;
        }
        $method = strtoupper($method);
        if ($strict) {
            return tropatt_maintenance_policy_allows_strict($path, $method);
        }
        // Web recovery surface.
        if ($path === 'login' || $path === 'admin-updates') {
            return true;
        }
        // Update center API: login, current session, version, updates + logs.
        if ($path === 'api/v1/core/version'
            || $path === 'api/v1/auth/login'
            || $path === 'api/v1/auth/me'
            || str_starts_with($path, 'api/v1/core/updates')) {
            return true;
        }
        // Polling reads that keep the navigation badge working.
        if ($path === 'api/v1/notifications/counters'
            || $path === 'api/v1/chats/unread-count'
            || str_starts_with($path, 'api/v1/notifications')
            || str_starts_with($path, 'api/v1/telemetry')
            || $path === 'api/v1/modules') {
            return true;
        }
        return false;
    }

    /**
     * Release-pipeline snapshot: several bounded steps read the installation
     * between chunks, so only read-only recovery/status pages may answer.
     * Login and every mutating route stay closed: they could write application
     * data while the snapshot is in flight.
     */
    function tropatt_maintenance_policy_allows_strict(string $path, string $method = 'GET'): bool
    {
        if (strtoupper($method) !== 'GET') {
            return false;
        }
        return in_array($path, [
            'login',
            'admin-updates',
            'api/v1/auth/me',
            'api/v1/core/version',
            'api/v1/core/updates/status',
            'api/v1/core/updates/changes',
            'api/v1/core/updates/history',
        ], true) || preg_match('#\Aapi/v1/core/updates/log/[A-Za-z0-9._-]+\z#D', $path) === 1;
    }

    /**
     * Convenience wrapper: read the routed path and method from the request
     * and apply the policy.
     */
    function tropatt_maintenance_policy_allows_request(bool $strict = false): bool
    {
        $path = trim((string)($_GET['route'] ?? ''), '/');
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        return tropatt_maintenance_policy_allows($path, $method, $strict);
    }
}

if (!function_exists('tropatt_maintenance_auto_recover_if_abandoned')) {
    /**
     * Watchdog for abandoned updater maintenance mode on shared hosting.
     *
     * An interrupted core update (closed browser tab, 504 Gateway Timeout,
     * network drop) leaves `storage_api/maintenance.flag` and `storage_api/updates/locks/update.lock`
     * in place, stranding the CRM behind a 503 maintenance page.
     *
     * This function checks whether maintenance mode belongs to a core update apply
     * (`core_update_apply`) and has had no heartbeat or progress updates for at least
     * $abandonedThresholdSeconds (default: 300 seconds / 5 minutes). If so, it automatically:
     *   1. clears the maintenance flag;
     *   2. releases or removes the stale update lock;
     *   3. marks the corresponding update job as `failed` (`RECOVERED_INTERRUPTED`);
     *   4. supersedes any expired release coordinator lease;
     *   5. writes a diagnostic event to the recovery log.
     *
     * Returns true if an abandoned maintenance mode was detected and cleared, false otherwise.
     */
    function tropatt_maintenance_auto_recover_if_abandoned(string $projectRoot, ?int $abandonedThresholdSeconds = null): bool
    {
        $projectRoot = rtrim($projectRoot, '/');
        $maintenanceFlag = $projectRoot . '/storage_api/maintenance.flag';
        if (!is_file($maintenanceFlag)) {
            return false;
        }

        $envThreshold = getenv('TROPATT_UPDATE_ABANDON_TIMEOUT');
        $threshold = $abandonedThresholdSeconds ?? ($envThreshold !== false && is_numeric($envThreshold) ? (int)$envThreshold : 300);
        if ($threshold < 60) {
            $threshold = 60;
        }

        $now = time();
        clearstatcache(true, $maintenanceFlag);
        $flagMtime = (int)@filemtime($maintenanceFlag);
        $flagRaw = @file_get_contents($maintenanceFlag);
        $flagData = is_string($flagRaw) ? json_decode($flagRaw, true) : null;

        // If the flag file is corrupt/unparseable and older than $threshold, it is abandoned.
        $isCorruptFlag = !is_array($flagData);
        if ($isCorruptFlag) {
            if ($now - $flagMtime < $threshold) {
                return false;
            }
        } else {
            $reason = (string)($flagData['reason'] ?? '');
            // Only auto-recover core updater apply maintenance (or legacy/unspecified reason).
            // Explicit manual or deployment maintenance is left intact unless well past a long ceiling.
            if ($reason !== 'core_update_apply' && $reason !== '') {
                if ($reason === 'deployment_pipeline' && ($now - $flagMtime) >= ($threshold * 4)) {
                    // Deployment pipeline hung for over 20 minutes; safe to reclaim.
                } else {
                    return false;
                }
            }

            $enabledAt = isset($flagData['enabled_at']) ? strtotime((string)$flagData['enabled_at']) : false;
            $flagEffectiveTime = $enabledAt !== false && $enabledAt > 0 ? min($enabledAt, $flagMtime) : $flagMtime;
            if (($now - $flagEffectiveTime) < $threshold) {
                return false;
            }
        }

        // Check if an updater lock exists and is actively beating
        $lockFile = $projectRoot . '/storage_api/updates/locks/update.lock';
        $lockJobId = null;
        if (is_file($lockFile)) {
            clearstatcache(true, $lockFile);
            $lockMtime = (int)@filemtime($lockFile);
            $lockRaw = @file_get_contents($lockFile);
            $lockData = is_string($lockRaw) ? json_decode($lockRaw, true) : null;
            if (is_array($lockData)) {
                $lockJobId = (string)($lockData['job_id'] ?? '');
                $heartbeatAt = isset($lockData['heartbeat_at']) ? strtotime((string)$lockData['heartbeat_at']) : false;
                $lastActivity = $heartbeatAt !== false && $heartbeatAt > 0 ? $heartbeatAt : $lockMtime;
                $lockAge = $now - $lastActivity;
                if ($lockAge < $threshold) {
                    $pid = isset($lockData['pid']) && is_numeric($lockData['pid']) ? (int)$lockData['pid'] : null;
                    if ($pid !== null && function_exists('posix_kill') && @posix_kill($pid, 0)) {
                        return false; // Active live process
                    }
                    if ($pid === null || !function_exists('posix_kill')) {
                        return false; // Recent heartbeat and cannot confirm PID dead
                    }
                }
            } elseif (($now - $lockMtime) < $threshold) {
                return false;
            }
        }

        $jobId = (string)($flagData['job_id'] ?? ($lockJobId ?? ''));
        // Check latest job progress timestamp if job_id is known
        if ($jobId !== '' && preg_match('/\A[A-Za-z0-9._-]{1,128}\z/D', $jobId)) {
            $stateFile = $projectRoot . '/storage_api/updates/jobs/' . $jobId . '/state.json';
            if (is_file($stateFile)) {
                clearstatcache(true, $stateFile);
                $stateRaw = @file_get_contents($stateFile);
                $stateData = is_string($stateRaw) ? json_decode($stateRaw, true) : null;
                if (is_array($stateData)) {
                    $progressAt = isset($stateData['progress']['at']) ? strtotime((string)$stateData['progress']['at']) : false;
                    if ($progressAt !== false && $progressAt > 0 && ($now - $progressAt) < $threshold) {
                        return false; // Progress touched within threshold
                    }
                }
            }
        }

        // All checks indicate the update is abandoned. Clear maintenance mode.
        @unlink($maintenanceFlag);

        // Remove the abandoned lock file
        if (is_file($lockFile)) {
            @unlink($lockFile);
        }

        // Close out the abandoned job if state exists
        if (isset($stateFile) && is_file($stateFile) && isset($stateData) && is_array($stateData)) {
            $jobState = (string)($stateData['state'] ?? '');
            if (!in_array($jobState, ['applied', 'rolled_back'], true)) {
                $stateData['state'] = 'failed';
                $stateData['can_resume'] = false;
                $stateData['error'] = 'Auto-recovered from an abandoned or interrupted update (heartbeat timeout).';
                $stateData['error_code'] = 'RECOVERED_INTERRUPTED';
                $stateData['maintenance_held'] = false;
                $stateData['recovered_at'] = gmdate('c');
                @file_put_contents(
                    $stateFile,
                    json_encode($stateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
            }
            $jobLog = $projectRoot . '/storage_api/updates/jobs/' . $jobId . '/update.log';
            if (is_file($jobLog) || is_dir(dirname($jobLog))) {
                @file_put_contents(
                    $jobLog,
                    '[' . gmdate('c') . '] [error] [recovered] Auto-recovered abandoned update: heartbeat timed out, maintenance mode released.' . PHP_EOL,
                    FILE_APPEND
                );
            }
        }

        // Supersede expired release-coordinator lease if present
        $leaseFile = $projectRoot . '/storage_api/release-coordinator/host-release.lease.json';
        if (is_file($leaseFile)) {
            $leaseRaw = @file_get_contents($leaseFile);
            $leaseData = is_string($leaseRaw) ? json_decode($leaseRaw, true) : null;
            if (is_array($leaseData) && (($leaseData['expires_at'] ?? 0) < $now || ($leaseData['takeover_pending'] ?? false) === true)) {
                $leaseData['continuation_mode'] = 'manually_superseded';
                $leaseData['manually_superseded_at'] = $now;
                $leaseData['takeover_pending'] = false;
                @file_put_contents(
                    $leaseFile,
                    json_encode($leaseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
            }
        }

        // Log to watchdog log
        $logDir = $projectRoot . '/storage_api/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logEntry = sprintf(
            "[%s] AUTO-RECOVERY: Abandoned update maintenance cleared (job_id: %s, threshold: %ds)\n",
            gmdate('Y-m-d H:i:s T'),
            $jobId !== '' ? $jobId : 'unknown',
            $threshold
        );
        @file_put_contents($logDir . '/updater_watchdog.log', $logEntry, FILE_APPEND);

        return true;
    }
}
