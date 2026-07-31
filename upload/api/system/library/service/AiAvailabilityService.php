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

        return [
            'ai' => [
                'enabled' => $aiEnabled,
                'provider_configured' => $providerConfigured,
                'provider_public_id' => $providerPublicId,
            ],
            'actor' => [
                'can_use_ai' => $canUseAi,
                'can_manage_ai' => $canManageAi,
            ],
            'intents' => $intents,
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
