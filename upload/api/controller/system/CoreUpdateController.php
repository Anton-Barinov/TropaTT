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

final class CoreUpdateController extends BaseController
{
    public function status(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', 'Forbidden', 403);
        }
        $config = CoreUpdateConfig::load();
        $client = new CoreUpdateClient($config);
        return $this->success('CORE_UPDATE_STATUS', 'Core update status', (new CoreUpdateStatusService((string)$config['storage_dir'], $client, $config))->status());
    }

    public function check(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', 'Forbidden', 403);
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
        return $this->success('CORE_UPDATE_CHECK', 'Core update check', $result);
    }

    public function changes(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', 'Forbidden', 403);
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
                return $this->success('CORE_UPDATE_CHANGES', 'Core update changes', $this->emptyChanges(is_string($from) ? $from : null, $to));
            }
        }
        return $this->success('CORE_UPDATE_CHANGES', 'Core update changes', $client->changes(is_string($from) ? $from : null, $to));
    }

    public function preflight(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', 'Forbidden', 403);
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
        return $this->success('CORE_UPDATE_PREFLIGHT', 'Core update preflight', $normalized);
    }

    public function session(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', 'Forbidden', 403);
        }
        $config = CoreUpdateConfig::load();
        $userId = (int)($this->user()['user']['id'] ?? 0);
        return $this->success('CORE_UPDATE_SESSION', 'Core update session', (new CoreUpdateSessionService((string)$config['storage_dir']))->create($userId));
    }

    public function history(): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', 'Forbidden', 403);
        }
        $config = CoreUpdateConfig::load();
        return $this->success('CORE_UPDATE_HISTORY', 'Core update history', ['items' => (new CoreUpdateHistoryRepository((string)$config['storage_dir']))->list()]);
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
            return $this->error('FORBIDDEN', 'Forbidden', 403);
        }
        $config = CoreUpdateConfig::load();
        $storageDir = (string)$config['storage_dir'];
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }
        $key = bin2hex(random_bytes(16));
        $hashFile = $storageDir . '/recovery_key.hash';
        if (@file_put_contents($hashFile, password_hash($key, PASSWORD_DEFAULT)) === false) {
            return $this->error('CORE_UPDATE_RECOVERY_KEY_WRITE_FAILED', 'Unable to write the recovery key file.', 500);
        }
        @chmod($hashFile, 0640);
        return $this->success('CORE_UPDATE_RECOVERY_KEY', 'Recovery key rotated', [
            'recovery_key' => $key,
            'note' => 'Save this key now. It will not be shown again.',
            'rescue_url' => '/updater/rescue.php',
        ]);
    }

    public function log(array $params): \Api\System\Library\Http\JsonResponse
    {
        if (!$this->allowed()) {
            return $this->error('FORBIDDEN', 'Forbidden', 403);
        }
        $config = CoreUpdateConfig::load();
        $jobId = (string)($params['job_id'] ?? '');
        return $this->success('CORE_UPDATE_LOG', 'Core update log', ['job_id' => $jobId, 'lines' => (new CoreUpdateLogRepository((string)$config['storage_dir']))->read($jobId)]);
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
        $baseUrl = $this->localUpdaterBaseUrl($config);
        $url = $baseUrl . '/updater/index.php?action=' . rawurlencode($action);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $timeout = (int)($config['timeouts']['apply_step'] ?? 60);
        $response = false;
        // cURL first (allow_url_fopen is disabled on many shared hosts);
        // stream wrappers as fallback.
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                ]);
                $response = curl_exec($ch);
                // PHP 8.0+ frees handles automatically; curl_close() is deprecated on 8.5.
                if (PHP_VERSION_ID < 80000) {
                    curl_close($ch);
                }
            }
        }
        if ($response === false || $response === '') {
            $response = @file_get_contents($url, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'ignore_errors' => true,
                    'timeout' => $timeout,
                ],
            ]));
        }
        $decoded = json_decode((string)$response, true);
        return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'invalid_updater_response'];
    }

    private function localUpdaterBaseUrl(array $config): string
    {
        $configured = trim((string)($config['local_updater_url'] ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'crm.ru'));
        $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
        $scheme = $isHttps ? 'https' : 'http';
        return $scheme . '://' . $host;
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
