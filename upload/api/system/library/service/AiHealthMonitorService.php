<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiProviderRepository;
use Api\Model\Common\UserRepository;
use Api\System\Library\Logger\JsonLogger;
use PDO;

/**
 * Proactive health monitoring for the configured AI providers (TROPATTCRM-623).
 *
 * Before this service, `provider_payload.health` was only refreshed when somebody
 * actually called AI or an administrator pressed the manual check. A revoked key,
 * an exhausted balance or a provider that silently changed its model was therefore
 * discovered by the first user who ran an idea analysis — which is exactly how the
 * original "AI-анализ идей не сработал ни разу" report stayed unnoticed for weeks.
 *
 * The monitor is driven by `api/scripts/ai_provider_health_check.php` (run from
 * cron every 5-10 minutes). It:
 *   1. probes every active provider with a cheap completion,
 *   2. keeps a per-provider incident state in `provider_payload.health.incident`,
 *   3. notifies administrators once per incident and once on recovery (no spam).
 *
 * Incident state lives with the provider row so that the monitor is stateless and
 * two overlapping runs cannot open the same incident twice. Every check is
 * fail-safe: a probe or metrics error is logged and skipped, never thrown, because
 * a broken monitor must not break cron.
 */
final class AiHealthMonitorService
{
    /** Feature flag that can turn the sweep off on an installation that asks for it. */
    public const FLAG_CODE = 'ai.health.monitor';

    public const INCIDENT_UNREACHABLE = 'AI_PROVIDER_UNREACHABLE';
    public const INCIDENT_INSUFFICIENT_CREDITS = 'AI_PROVIDER_INSUFFICIENT_CREDITS';
    public const INCIDENT_HIGH_ERROR_RATE = 'AI_HIGH_ERROR_RATE';

    /** A single failed probe is not an incident: providers blip on timeouts. */
    public const FAILURES_BEFORE_INCIDENT = 2;
    public const ERROR_RATE_THRESHOLD = 0.2;
    public const ERROR_RATE_MIN_SAMPLES = 5;
    public const ERROR_RATE_WINDOW_MINUTES = 60;

    public function __construct(
        private readonly AiProviderRepository $providers,
        private readonly UserRepository $users,
        private readonly ?NotificationService $notifications,
        private readonly JsonLogger $logger,
        private readonly PDO $pdo,
        private readonly ?FeatureFlagService $featureFlags = null
    ) {
    }

    /**
     * Monitoring is opt-out, not opt-in: the task that introduced it exists because
     * a default-off flag hid a broken provider for months. An installation that
     * does not want the sweep simply does not install the cron line, or flips the
     * flag.
     */
    public function isEnabled(): bool
    {
        if ($this->featureFlags === null) {
            return true;
        }

        try {
            return $this->featureFlags->isEnabled(self::FLAG_CODE, true);
        } catch (\Throwable $e) {
            // An unreadable flag table (an installation that has not run the schema
            // update yet) must not be silently read as "monitoring is off": that is
            // exactly the failure mode this monitor exists to prevent.
            $this->logger->warning('ai_health_flag_read_failed', [
                'flag' => self::FLAG_CODE,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    /** @return list<array<string,mixed>> */
    public function activeProviders(): array
    {
        [$items] = $this->providers->list(['is_active' => 1, 'limit' => 100]);

        return array_values(array_filter(
            $items,
            static fn(array $provider): bool => trim((string)($provider['public_id'] ?? '')) !== ''
        ));
    }

    /**
     * Probe every active provider and react to the result.
     *
     * @param callable(array<string,mixed>):array<string,mixed> $probe Receives the
     *        provider row, returns ['ok' => bool, 'code' => string]. The caller owns
     *        the transport (the CLI wires it to AiProviderService::testConnection()).
     * @return array<string,mixed> Run summary for the cron log.
     */
    public function runProviderChecks(callable $probe): array
    {
        $summary = [
            'enabled' => true,
            'probed' => 0,
            'failed_probes' => 0,
            'incidents' => [],
            'recoveries' => [],
            'notified' => 0,
        ];

        foreach ($this->activeProviders() as $provider) {
            $publicId = (string)$provider['public_id'];
            $summary['probed']++;

            $result = ['ok' => false, 'code' => 'AI_PROVIDER_PROBE_FAILED'];
            try {
                $result = (array)$probe($provider);
            } catch (\Throwable $e) {
                // A probe that cannot even run (network, DNS, TLS) is still a signal,
                // and it must never abort the sweep for the remaining providers.
                $this->logger->error('ai_health_probe_failed', [
                    'provider_public_id' => $publicId,
                    'error' => $e->getMessage(),
                ]);
            }

            if (!(bool)($result['ok'] ?? false)) {
                $summary['failed_probes']++;
            }

            $outcome = $this->evaluateProvider($publicId, $result);
            $action = (string)($outcome['action'] ?? 'none');
            if ($action === 'incident') {
                $summary['incidents'][] = $outcome;
            } elseif ($action === 'recovery') {
                $summary['recoveries'][] = $outcome;
            }
            $summary['notified'] += (int)($outcome['notified'] ?? 0);
        }

        return $summary;
    }

    /**
     * Decide the incident state of one provider from a probe result plus the
     * recent AI usage of that provider, persist it and notify on transitions.
     *
     * @param array<string,mixed> $probeResult
     * @return array<string,mixed>
     */
    public function evaluateProvider(string $providerPublicId, array $probeResult): array
    {
        $provider = $this->providers->findByPublicId($providerPublicId);
        if ($provider === null) {
            return ['provider_public_id' => $providerPublicId, 'action' => 'skipped', 'code' => 'AI_PROVIDER_NOT_FOUND'];
        }

        $payload = $this->decodeJson((string)($provider['provider_payload'] ?? '{}'));
        $health = is_array($payload['health'] ?? null) ? (array)$payload['health'] : [];
        $incident = is_array($health['incident'] ?? null) ? (array)$health['incident'] : [];

        $probeOk = (bool)($probeResult['ok'] ?? false);
        $errorCode = trim((string)($probeResult['code'] ?? ''));
        $rate = $this->errorRate($providerPublicId);

        $failures = $probeOk ? 0 : ((int)($incident['consecutive_failures'] ?? 0) + 1);
        $insufficientCredits = $errorCode === self::INCIDENT_INSUFFICIENT_CREDITS;
        // Two failures in a row (or an immediately recognisable billing problem)
        // open the incident; a single timeout does not.
        $unreachable = !$probeOk && ($failures >= self::FAILURES_BEFORE_INCIDENT || $insufficientCredits);
        $highErrorRate = $rate['rate'] >= self::ERROR_RATE_THRESHOLD
            && $rate['samples'] >= self::ERROR_RATE_MIN_SAMPLES;
        $isOpen = (string)($incident['state'] ?? '') === 'open';
        $openNow = $unreachable || $highErrorRate;

        $action = 'none';
        $notified = 0;

        if ($openNow) {
            $code = $unreachable
                ? ($insufficientCredits ? self::INCIDENT_INSUFFICIENT_CREDITS : self::INCIDENT_UNREACHABLE)
                : self::INCIDENT_HIGH_ERROR_RATE;

            $incident = [
                // An already open incident keeps its original code and opened_at so
                // the administrator sees when the trouble actually started.
                'state' => 'open',
                'code' => $isOpen ? (string)($incident['code'] ?? $code) : $code,
                'opened_at' => $isOpen ? (string)($incident['opened_at'] ?? gmdate('c')) : gmdate('c'),
                'notified_at' => (string)($incident['notified_at'] ?? ''),
                'consecutive_failures' => $failures,
                'last_error_code' => $errorCode,
                'error_rate' => round($rate['rate'], 4),
                'error_samples' => $rate['samples'],
                'last_seen_at' => gmdate('c'),
            ];

            if (!$isOpen) {
                $action = 'incident';
                $notified = $this->notifyAdmins($provider, true, $incident, $rate);
                if ($notified > 0) {
                    $incident['notified_at'] = gmdate('c');
                }
            }
        } else {
            if ($isOpen) {
                $action = 'recovery';
                $notified = $this->notifyAdmins($provider, false, $incident, $rate);
            }
            $incident = [
                'state' => 'closed',
                'closed_at' => gmdate('c'),
                'consecutive_failures' => $failures,
                'error_rate' => round($rate['rate'], 4),
            ];
        }

        $health['incident'] = $incident;
        $health['monitor_last_run_at'] = gmdate('c');
        $payload['health'] = $health;
        $this->persistProviderPayload($providerPublicId, $payload);

        $this->logger->audit([
            'action' => 'ai_provider_health_evaluated',
            'entity_type' => 'ai_provider',
            'entity_public_id' => $providerPublicId,
            'probe_ok' => $probeOk,
            'probe_error_code' => $errorCode,
            'incident_state' => (string)$incident['state'],
            'incident_code' => (string)($incident['code'] ?? ''),
            'error_rate' => round($rate['rate'], 4),
            'error_samples' => $rate['samples'],
            'notified' => $notified,
            'action' => $action,
        ]);

        return [
            'provider_public_id' => $providerPublicId,
            'action' => $action,
            'code' => (string)($incident['code'] ?? ''),
            'probe_ok' => $probeOk,
            'error_rate' => round($rate['rate'], 4),
            'error_samples' => $rate['samples'],
            'notified' => $notified,
        ];
    }

    /**
     * Error share of the provider's AI calls inside the window, computed from
     * ai_usage_logs. A row counts as failed when it carries an error_code or a
     * non-success status — both conventions are in use across the AI services.
     *
     * @return array{samples:int,errors:int,rate:float,window_minutes:int}
     */
    public function errorRate(string $providerPublicId, int $windowMinutes = self::ERROR_RATE_WINDOW_MINUTES): array
    {
        $windowMinutes = max(1, $windowMinutes);
        $since = gmdate('Y-m-d H:i:s', time() - ($windowMinutes * 60));
        $empty = ['samples' => 0, 'errors' => 0, 'rate' => 0.0, 'window_minutes' => $windowMinutes];

        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) AS samples, '
                . "SUM(CASE WHEN (error_code IS NOT NULL AND error_code <> '') "
                . "OR (status IS NOT NULL AND status NOT IN ('completed', 'success')) THEN 1 ELSE 0 END) AS errors "
                . 'FROM ai_usage_logs WHERE provider_public_id = :provider_public_id AND created_at >= :since'
            );
            $stmt->execute(['provider_public_id' => $providerPublicId, 'since' => $since]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // Observability tables may be absent on an installation that never ran
            // an AI call; that must not stop the provider probe.
            $this->logger->warning('ai_health_error_rate_unavailable', ['error' => $e->getMessage()]);

            return $empty;
        }

        $samples = (int)($row['samples'] ?? 0);
        $errors = (int)($row['errors'] ?? 0);

        return [
            'samples' => $samples,
            'errors' => $errors,
            'rate' => $samples > 0 ? $errors / $samples : 0.0,
            'window_minutes' => $windowMinutes,
        ];
    }

    /**
     * Rolling AI usage summary for the admin AI page: success share, latency and
     * the most frequent error codes (item 3 of TROPATTCRM-623).
     *
     * @return array<string,mixed>
     */
    public function usageSummary(int $hours = 24): array
    {
        $hours = max(1, $hours);
        $since = gmdate('Y-m-d H:i:s', time() - ($hours * 3600));
        $summary = [
            'window_hours' => $hours,
            'since' => $since,
            'total' => 0,
            'failed' => 0,
            'success_rate' => 1.0,
            'avg_latency_ms' => 0,
            'max_latency_ms' => 0,
            'top_errors' => [],
        ];

        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) AS total, '
                . "SUM(CASE WHEN (error_code IS NOT NULL AND error_code <> '') "
                . "OR (status IS NOT NULL AND status NOT IN ('completed', 'success')) THEN 1 ELSE 0 END) AS failed, "
                . 'AVG(latency_ms) AS avg_latency_ms, MAX(latency_ms) AS max_latency_ms '
                . 'FROM ai_usage_logs WHERE created_at >= :since'
            );
            $stmt->execute(['since' => $since]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $topStmt = $this->pdo->prepare(
                'SELECT error_code, COUNT(*) AS occurrences FROM ai_usage_logs '
                . "WHERE created_at >= :since AND error_code IS NOT NULL AND error_code <> '' "
                . 'GROUP BY error_code ORDER BY occurrences DESC'
            );
            $topStmt->execute(['since' => $since]);
            $topErrors = $topStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->logger->warning('ai_health_usage_summary_unavailable', ['error' => $e->getMessage()]);

            return $summary;
        }

        $total = (int)($row['total'] ?? 0);
        $failed = (int)($row['failed'] ?? 0);
        $summary['total'] = $total;
        $summary['failed'] = $failed;
        $summary['success_rate'] = $total > 0 ? round(($total - $failed) / $total, 4) : 1.0;
        $summary['avg_latency_ms'] = (int)round((float)($row['avg_latency_ms'] ?? 0));
        $summary['max_latency_ms'] = (int)($row['max_latency_ms'] ?? 0);
        $summary['top_errors'] = array_map(
            static fn(array $error): array => [
                'error_code' => (string)($error['error_code'] ?? ''),
                'occurrences' => (int)($error['occurrences'] ?? 0),
            ],
            $topErrors
        );

        return $summary;
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $incident
     * @param array<string,mixed> $rate
     */
    private function notifyAdmins(array $provider, bool $isIncident, array $incident, array $rate): int
    {
        if ($this->notifications === null) {
            return 0;
        }

        try {
            $admins = $this->users->findAdmins();
        } catch (\Throwable $e) {
            $this->logger->error('ai_health_admin_lookup_failed', ['error' => $e->getMessage()]);

            return 0;
        }

        $adminIds = [];
        foreach ($admins as $admin) {
            $adminId = (int)($admin['id'] ?? 0);
            if ($adminId > 0) {
                $adminIds[] = $adminId;
            }
        }
        if ($adminIds === []) {
            return 0;
        }

        try {
            return $isIncident
                ? $this->notifications->notifyAiProviderIncident($provider, $incident, $rate, $adminIds)
                : $this->notifications->notifyAiProviderRecovered($provider, $incident, $rate, $adminIds);
        } catch (\Throwable $e) {
            $this->logger->error('ai_health_notification_failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /** @param array<string,mixed> $payload */
    private function persistProviderPayload(string $providerPublicId, array $payload): void
    {
        try {
            $this->providers->updateByPublicId($providerPublicId, [
                'provider_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Health telemetry is diagnostic: losing one snapshot must not fail the
            // sweep, and a completion may legitimately outlive the DB connection.
            $this->logger->error('ai_health_persist_failed', [
                'provider_public_id' => $providerPublicId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string,mixed> */
    private function decodeJson(string $raw): array
    {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
