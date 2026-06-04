<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiIntentSettingRepository;
use Api\System\Library\Config;

final class AiActionTypeService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly Config $config,
        private readonly AiIntentSettingRepository $intentSettings
    ) {
    }

    /** @return list<string> */
    public function allowlist(): array
    {
        $setting = $this->settings->get('ai_actions', 'allowlist');
        $fromSettings = is_array($setting['value'] ?? null) ? (array)$setting['value'] : [];
        $fromConfig = (array)$this->config->get('ai.actions.allowlist', []);
        $list = array_merge($fromConfig, $fromSettings, [
            'idea_analyze',
        ]);

        $normalized = [];
        foreach ($list as $item) {
            $value = trim((string)$item);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /** @return list<string> */
    public function enabledAllowlist(): array
    {
        $enabled = [];
        foreach ($this->allowlist() as $actionType) {
            if ($this->isEnabled($actionType)) {
                $enabled[] = $actionType;
            }
        }

        return $enabled;
    }

    public function isAllowed(string $actionType): bool
    {
        return in_array(trim($actionType), $this->allowlist(), true);
    }

    public function isEnabled(string $actionType): bool
    {
        $normalized = trim($actionType);
        if ($normalized === '' || !$this->isAllowed($normalized)) {
            return false;
        }

        $intent = $this->intentSettings->findByIntentCode($normalized);
        if (!is_array($intent)) {
            return true;
        }

        return (bool)($intent['is_enabled'] ?? true);
    }
}

