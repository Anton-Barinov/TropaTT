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
