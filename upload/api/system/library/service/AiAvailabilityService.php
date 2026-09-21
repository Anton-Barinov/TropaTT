<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiProviderRepository;

final class AiAvailabilityService
{
    public function __construct(
        private readonly AiIntentSettingService $intentSettings,
        private readonly AiProviderRepository $providers,
        private readonly FeatureFlagService $featureFlags
    ) {
    }

    /**
     * @param array<string,mixed> $actor
     * @param list<string> $requestedIntents
     * @return array<string,mixed>
     */
    public function getAvailability(array $actor, array $requestedIntents = []): array
    {
        $isRoot = (bool)($actor['is_root'] ?? false);
        $permissions = $this->normalizePermissions($actor);
        $roles = $this->normalizeRoles($actor);

        $canUseAi = $isRoot || in_array('*', $permissions, true) || in_array('ai.use', $permissions, true);
        $canManageAi = $isRoot || in_array('admin', $roles, true) || in_array('ai.admin', $permissions, true);

        $aiEnabled = $this->isFeatureEnabledForActor('ai.enabled', $actor, false);
        $provider = $this->providers->findDefaultActive() ?? $this->providers->findAnyActive();
        $providerConfigured = false;
        $providerPublicId = '';
        if (is_array($provider)) {
            $providerPublicId = trim((string)($provider['public_id'] ?? ''));
            $providerConfigured = $providerPublicId !== '' && $this->providers->hasSecret((int)($provider['id'] ?? 0));
        }

        $intentRows = (array)($this->intentSettings->list([])['items'] ?? []);
        $intents = [];
        foreach ($intentRows as $item) {
            if (!is_array($item)) {
                continue;
            }

            $intentCode = trim((string)($item['intent_code'] ?? ''));
            if ($intentCode === '') {
                continue;
            }
            if ($requestedIntents !== [] && !in_array($intentCode, $requestedIntents, true)) {
                continue;
            }

            $requiredPermission = trim((string)($item['required_permission'] ?? 'ai.use'));
            if ($requiredPermission === '') {
                $requiredPermission = 'ai.use';
            }

            $featureFlag = trim((string)($item['feature_flag'] ?? ''));
            $intentEnabled = (bool)($item['is_enabled'] ?? false);
            $hasRequiredPermission = $isRoot
                || in_array('*', $permissions, true)
                || in_array($requiredPermission, $permissions, true);

            $enabled = false;
            $reason = 'permission_required';
            if ($hasRequiredPermission || ($canManageAi && str_starts_with($intentCode, 'admin_'))) {
                if (!$aiEnabled) {
                    $reason = 'ai_disabled';
                } elseif (!$providerConfigured) {
                    $reason = 'provider_missing';
                } elseif (!$intentEnabled) {
                    $reason = 'intent_disabled';
                } elseif ($featureFlag !== '' && !$this->isFeatureEnabledForActor($featureFlag, $actor, false)) {
                    $reason = 'feature_disabled';
                } else {
                    $enabled = true;
                    $reason = 'enabled';
                }
            }

            $intents[$intentCode] = [
                'intent_code' => $intentCode,
                'enabled' => $enabled,
                'reason' => $reason,
                'required_permission' => $requiredPermission,
                'feature_flag' => $featureFlag,
            ];
        }

        // The last scheduled health check (TROPATTCRM-623) already knows whether the
        // provider is answering. Surfaces that start a long AI run read `unavailable_reason`
        // and can say so instead of letting the user wait minutes for the failure.
        $health = self::providerHealthSnapshot($provider);
        $unavailableReason = '';
        if (!$aiEnabled) {
            $unavailableReason = 'ai_disabled';
        } elseif (!$providerConfigured) {
            $unavailableReason = 'provider_missing';
        } elseif ($health['unhealthy']) {
            $unavailableReason = 'provider_unhealthy';
        }

        return [
            'ai' => [
                'enabled' => $aiEnabled,
                'provider_configured' => $providerConfigured,
                'provider_public_id' => $providerPublicId,
                'health' => $health,
                'unavailable_reason' => $unavailableReason,
            ],
            'actor' => [
                'can_use_ai' => $canUseAi,
                'can_manage_ai' => $canManageAi,
            ],
            'intents' => $intents,
        ];
    }

    /**
     * Read the health block the monitor writes into `provider_payload.health`.
     *
     * `unhealthy` is true for an open incident or for the last check that failed:
     * that provider is not going to answer the next call either, so callers must
     * warn before a long AI run rather than after it. A provider that was never
     * checked is reported as `unknown` and is deliberately not treated as down.
     *
     * @param array<string,mixed>|null $provider
     * @return array<string,mixed>
     */
    public static function providerHealthSnapshot(?array $provider): array
    {
        $raw = $provider['provider_payload'] ?? null;
        $payload = is_array($raw) ? $raw : json_decode((string)$raw, true);
        $payload = is_array($payload) ? $payload : [];
        $health = is_array($payload['health'] ?? null) ? (array)$payload['health'] : [];
        $incident = is_array($health['incident'] ?? null) ? (array)$health['incident'] : [];

        $incidentState = (string)($incident['state'] ?? '');
        $status = trim((string)($health['status'] ?? ''));
        if ($status === '') {
            $status = $health === [] ? 'unknown' : 'ok';
        }

        return [
            'status' => $status,
            'last_checked_at' => (string)($health['last_checked_at'] ?? ''),
            'last_error_at' => (string)($health['last_error_at'] ?? ''),
            'last_error_code' => (string)($health['last_error_code'] ?? ''),
            'incident_state' => $incidentState,
            'incident_code' => (string)($incident['code'] ?? ''),
            'incident_opened_at' => (string)($incident['opened_at'] ?? ''),
            'unhealthy' => $incidentState === 'open' || $status === 'error',
        ];
    }

    /** @param array<string,mixed> $actor */
    private function normalizePermissions(array $actor): array
    {
        $permissions = is_array($actor['permission_codes'] ?? null) ? (array)$actor['permission_codes'] : [];

        return array_values(array_unique(array_filter(array_map(static function (mixed $item): string {
            return trim((string)$item);
        }, $permissions))));
    }

    /** @param array<string,mixed> $actor */
    private function normalizeRoles(array $actor): array
    {
        $roles = is_array($actor['roles'] ?? null) ? (array)$actor['roles'] : [];

        return array_values(array_unique(array_filter(array_map(static function (mixed $item): string {
            return strtolower(trim((string)$item));
        }, $roles))));
    }

    /** @param array<string,mixed> $actor */
    private function isFeatureEnabledForActor(string $flagCode, array $actor, bool $default): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        return $this->featureFlags->isEnabled($flagCode, $default);
    }
}
