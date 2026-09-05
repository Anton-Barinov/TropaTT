<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class UpdateCenterAuditService
{
    public function __construct(private readonly string $storageDir)
    {
    }

    public function read(): ?array
    {
        $file = $this->storageDir . '/update-center-audit.json';
        return is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    }

    /**
     * Write a fresh audit snapshot after a check request.
     *
     * Best-effort: returns false if the file cannot be written (e.g. read-only
     * storage) but never throws — a failed audit write must not break the
     * check response. The audit is informational: the page and the updater
     * read it only for display and as a fallback health signal. Writing it on
     * every check keeps the recorded update-center URL, checked_at, and plan
     * fresh instead of leaving a stale file pointing at an old URL.
     *
     * @param array<string, mixed> $config      update center config (api/config/update.php)
     * @param array<string, mixed> $checkResult result of CoreUpdatePlanner::check()
     */
    public function write(array $config, array $checkResult): bool
    {
        $raw = is_array($checkResult['raw'] ?? null) ? $checkResult['raw'] : [];
        $plan = is_array($checkResult['plan'] ?? null) ? $checkResult['plan'] : [];
        $package = is_array($plan['recommended_package'] ?? null) ? $plan['recommended_package'] : null;
        $centerOk = ($raw['ok'] ?? false) === true;
        $planOk = $centerOk && array_key_exists('update_available', $plan);

        $notes = [];
        if (!$centerOk) {
            $notes[] = 'Update center is unavailable';
        } elseif (!$planOk) {
            $notes[] = 'Update plan could not be loaded';
        }

        $audit = [
            'checked_at' => gmdate('c'),
            'update_center_url' => rtrim((string)($config['update_center_url'] ?? ''), '/'),
            'health_ok' => $centerOk,
            'product_ok' => $centerOk,
            'stable_channel_ok' => $centerOk,
            'update_plan_ok' => $planOk,
            'manifest_ok' => $planOk,
            'package_ok' => $planOk && ($package === null || is_array($package)),
            'cron_checked' => false,
            'detected_endpoints' => is_array($config['endpoints'] ?? null) ? $config['endpoints'] : [],
            'latest_plan' => [
                'target_build' => $plan['target_build'] ?? null,
                'recommended_package_type' => is_array($package) ? ($package['type'] ?? null) : null,
                'manifest_url' => is_array($package) ? ($package['manifest_url'] ?? null) : null,
                'package_url' => is_array($package) ? ($package['url'] ?? null) : null,
            ],
            'notes' => $notes,
        ];

        $json = json_encode($audit, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }
        $file = $this->storageDir . '/update-center-audit.json';
        return @file_put_contents($file, $json) !== false;
    }
}
