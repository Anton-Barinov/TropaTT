<?php
declare(strict_types=1);

namespace Api\Controller\System;

use Api\Controller\Common\BaseController;
use Api\System\Library\Update\CoreUpdateClient;
use Api\System\Library\Update\CoreUpdateConfig;
use Api\System\Library\Update\CoreUpdateHistoryRepository;
use Api\System\Library\Update\CoreUpdateLogRepository;
use Api\System\Library\Update\CoreUpdatePlanner;
use Api\System\Library\Update\CoreUpdateSessionService;
use Api\System\Library\Update\CoreUpdateStatusService;
use Api\System\Library\Update\CoreVersion;
use Api\System\Library\Update\UpdateCenterAuditService;
use Api\System\Library\Update\UpdaterBridge;

final class CoreUpdateController extends BaseController
{
    public function status(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        $config = CoreUpdateConfig::load();
        $client = new CoreUpdateClient($config);
        $version = new CoreVersion((string)$config['storage_dir'], dirname(__DIR__, 3));
        return $this->success('CORE_UPDATE_STATUS', $this->t('system/messages.status'), (new CoreUpdateStatusService((string)$config['storage_dir'], $client, $config, $version))->status());
    }

    public function check(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        $config = CoreUpdateConfig::load();
        $client = new CoreUpdateClient($config);
        $version = new CoreVersion((string)$config['storage_dir'], dirname(__DIR__, 3));
        $result = (new CoreUpdatePlanner($client, $version))->check();
        // Refresh the update-center audit snapshot so it never shows a stale
        // URL or checked_at. Best-effort: the check response must not fail if
        // the audit file cannot be written (e.g. read-only storage).
        try {
            (new UpdateCenterAuditService((string)$config['storage_dir']))->write($config, $result);
        } catch (\Throwable $e) {
            // Ignore audit write errors; the check result is authoritative.
        }
        return $this->success('CORE_UPDATE_CHECK', $this->t('system/messages.check'), $result);
    }

    public function changes(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        $config = CoreUpdateConfig::load();
        $client = new CoreUpdateClient($config);
        $from = $this->request()->input('from', null);
        $to = (string)$this->request()->input('to', '');
        if ($to === '') {
            $check = (new CoreUpdatePlanner($client, new CoreVersion((string)$config['storage_dir'], dirname(__DIR__, 3))))->check();
            $plan = is_array($check['plan'] ?? null) ? $check['plan'] : [];
            $current = is_array($check['current'] ?? null) ? $check['current'] : [];
            $to = (string)($plan['target_build'] ?? '');
            $from = $current['core_build'] ?? ($plan['current_build'] ?? $from);
            if ($to === '' || (($plan['update_available'] ?? null) === false && (string)$from === $to)) {
                return $this->success('CORE_UPDATE_CHANGES', $this->t('system/messages.changes'), $this->emptyChanges(is_string($from) ? $from : null, $to));
            }
        }
        return $this->success('CORE_UPDATE_CHANGES', $this->t('system/messages.changes'), $client->changes(is_string($from) ? $from : null, $to));
    }

    public function preflight(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        $config = CoreUpdateConfig::load();
        $payload = $this->request()->allInput();
        $payload['dry_run'] = true;
        $result = $this->callUpdater('preflight', $payload, $config);
        $normalized = $this->normalizeUpdaterResult($result);
        if (($normalized['success'] ?? false) !== true || empty($normalized['job_id'])) {
            $message = (string)($normalized['message'] ?? $normalized['code'] ?? 'Updater preflight failed.');
            return $this->error('CORE_UPDATE_PREFLIGHT_FAILED', $message, 502, [], ['updater' => $normalized]);
        }
        return $this->success('CORE_UPDATE_PREFLIGHT', $this->t('system/messages.preflight'), $normalized);
    }

    public function session(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        $config = CoreUpdateConfig::load();
        $userId = (int)($this->user()['user']['id'] ?? 0);
        return $this->success('CORE_UPDATE_SESSION', $this->t('system/messages.session'), (new CoreUpdateSessionService((string)$config['storage_dir']))->create($userId));
    }

    public function history(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        $config = CoreUpdateConfig::load();
        return $this->success('CORE_UPDATE_HISTORY', $this->t('system/messages.history'), ['items' => (new CoreUpdateHistoryRepository((string)$config['storage_dir']))->list()]);
    }

    /**
     * Rotate the updater recovery key and return the new value exactly once.
     *
     * The recovery key unlocks /updater/rescue.php (last-resort recovery while
     * maintenance mode holds). It is generated at installation, but an admin
     * may need a fresh copy - e.g. after a failed update the page still works,
     * while the key shown during install has been lost. The endpoint is
     * authenticated (settings.manage) and CSRF-protected like every other
     * state-changing API call. The returned key is displayed once and never
     * logged or stored in plain text (only its password hash is kept).
     */
    public function recoveryKey(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        $config = CoreUpdateConfig::load();
        $storageDir = (string)$config['storage_dir'];
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }
        $key = bin2hex(random_bytes(16));
        $hashFile = $storageDir . '/recovery_key.hash';
        if (@file_put_contents($hashFile, password_hash($key, PASSWORD_DEFAULT)) === false) {
            return $this->error('CORE_UPDATE_RECOVERY_KEY_WRITE_FAILED', $this->t('system/messages.recovery_key_write_failed'), 500);
        }
        @chmod($hashFile, 0640);
        // Also write plaintext key to a sidecar file for SSH/file-manager recovery.
        @file_put_contents($storageDir . '/recovery_key.txt', $key);
        @chmod($storageDir . '/recovery_key.txt', 0640);
        return $this->success('CORE_UPDATE_RECOVERY_KEY', $this->t('system/messages.recovery_key'), [
            'recovery_key' => $key,
            'note' => 'Save this key now. It will not be shown again.',
            'rescue_url' => '/updater/rescue.php',
        ]);
    }

    public function log(array $params): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }
        $config = CoreUpdateConfig::load();
        $jobId = (string)($params['job_id'] ?? '');
        return $this->success('CORE_UPDATE_LOG', $this->t('system/messages.log'), ['job_id' => $jobId, 'lines' => (new CoreUpdateLogRepository((string)$config['storage_dir']))->read($jobId)]);
    }

    private function allowed(): bool
    {
        $user = $this->user()['user'] ?? null;
        if (!is_array($user)) {
            return false;
        }
        if ((bool)($user['is_root'] ?? false)) {
            return true;
        }
        $permissions = is_array($user['permission_codes'] ?? null) ? $user['permission_codes'] : [];
        if (in_array('*', $permissions, true) || in_array('system.update', $permissions, true) || in_array('settings.manage', $permissions, true)) {
            return true;
        }
        $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
        $normalizedRoles = array_values(array_unique(array_filter(array_map(
            static function (mixed $role): string {
                if (is_array($role)) {
                    $role = $role['code'] ?? $role['public_id'] ?? $role['name'] ?? '';
                }
                return strtolower(str_replace('-', '_', trim(is_scalar($role) ? (string)$role : '')));
            },
            $roles
        ), static fn(string $role): bool => $role !== '')));
        if (array_intersect($normalizedRoles, ['admin', 'administrator', 'super_admin', 'super_administrator', 'root']) !== []) {
            return true;
        }

        return strtolower(trim((string)($user['login'] ?? ''))) === 'admin';
    }

    private function callUpdater(string $action, array $payload, array $config): array
    {
        $basePath = dirname(__DIR__, 3);

        // The updater is part of this installation. Run it in-process first so
        // shared hosting never has to hairpin through its own public hostname.
        // This avoids DNS, TLS, WAF, reverse-proxy and single-worker deadlocks.
        try {
            return UpdaterBridge::dispatch($basePath, $action, $payload);
        } catch (\Throwable $e) {
            error_log('[CoreUpdateController::callUpdater] in-process updater failed: ' . $e->getMessage());
        }

        // Compatibility fallback for unusual deployments where updater/src is
        // absent or cannot be loaded. This path retains the old behavior but
        // uses the proxy-aware scheme resolver and bounded timeouts.
        return UpdaterBridge::dispatchHttpFallback($action, $payload, $config);
    }

    private function normalizeUpdaterResult(array $result): array
    {
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        return [
            'success' => (bool)($result['success'] ?? false),
            'code' => $result['code'] ?? $result['error'] ?? null,
            'message' => $result['message'] ?? $result['error'] ?? null,
            'job_id' => $data['job_id'] ?? null,
            'preflight' => $data['preflight'] ?? null,
            'updater' => $result,
        ];
    }

    private function emptyChanges(?string $from, string $to): array
    {
        return [
            'ok' => true,
            'status' => 204,
            'data' => [
                'summary' => [
                    'commits' => 0,
                    'files' => 0,
                    'risk_level' => 'none',
                    'from' => $from,
                    'to' => $to !== '' ? $to : null,
                ],
                'commits' => [],
                'files' => [],
                'changes' => [
                    'added' => [],
                    'modified' => [],
                    'deleted' => [],
                    'renamed' => [],
                ],
                'message' => 'No target build is available, so there are no update changes to show.',
            ],
        ];
    }
}
