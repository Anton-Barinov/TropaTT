<?php
declare(strict_types=1);

namespace Api\Controller\Task;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;

/**
 * Task detail right-column blocks.
 *
 * Mirrors the dashboard widget system: the right column of the task card is made
 * of configurable blocks (core blocks rendered by the template + blocks injected
 * by modules through the "task.detail.sidebar" position). Each user can hide,
 * re-add and reorder blocks; the layout is stored per user via SettingService.
 *
 * Block keys MUST stay in sync with the web renderer:
 * - core blocks use the literal keys below (matched by data-task-sidebar-block);
 * - module blocks use the manifest "positions" entry "key", falling back to the
 *   snake_case short class name of the renderer (same derivation as
 *   Web\System\Module\PositionRegistry::deriveBlockKey()).
 */
final class TaskSidebarController extends BaseController
{
    private const PREF_SCOPE_PREFIX = 'user:';
    private const PREF_NAME = 'task_sidebar_blocks';

    private const SIDEBAR_POSITION = 'task.detail.sidebar';

    private const CORE_BLOCKS = [
        'estimates' => [
            'label_key' => 'task_detail.estimates_title',
            'label' => 'Оценки',
            'description_key' => 'task_detail.sidebar_estimates_desc',
            'description' => 'Оценки трудоёмкости задачи.',
            'permissions' => [],
            'default_enabled' => true,
        ],
        'timer' => [
            'label_key' => 'task_detail.timer_title',
            'label' => 'Таймер задачи',
            'description_key' => 'task_detail.sidebar_timer_desc',
            'description' => 'Таймер учёта времени по задаче.',
            'permissions' => [],
            'default_enabled' => true,
        ],
        'ai_assistant' => [
            'label_key' => 'task_detail.ai_title',
            'label' => 'AI-действия по задаче',
            'description_key' => 'task_detail.sidebar_ai_desc',
            'description' => 'AI-сводка и действия по задаче.',
            'permissions' => ['ai.use'],
            'default_enabled' => true,
        ],
        'summary' => [
            'label_key' => 'task_detail.summary_title',
            'label' => 'Сводка',
            'description_key' => 'task_detail.sidebar_summary_desc',
            'description' => 'Быстрая навигация: ключ, автор, исполнитель, сроки, проект.',
            'permissions' => [],
            'default_enabled' => true,
        ],
    ];

    public function get(): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $user = $authUser['user'] ?? [];
        $userPublicId = (string)($user['public_id'] ?? '');
        if ($userPublicId === '') {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $active = $this->resolveActive($this->loadPreference($userPublicId), $user);

        return $this->success('TASK_SIDEBAR', $this->t('task/messages.sidebar'), [
            'catalog' => $this->buildCatalog($user),
            'active' => $active,
        ]);
    }

    public function save(): JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $user = $authUser['user'] ?? [];
        $userPublicId = (string)($user['public_id'] ?? '');
        if ($userPublicId === '') {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $input = $this->request()->allInput();
        $activeRaw = $input['active'] ?? null;
        if (!is_array($activeRaw)) {
            return $this->error('VALIDATION_ERROR', $this->t('task/messages.sidebar_required'), 422);
        }

        $active = $this->resolveActive($this->normalizeActive($activeRaw), $user);

        /** @var \Api\System\Library\Service\SettingService $settingService */
        $settingService = $this->container->get('service.setting');
        $settingService->set(self::PREF_SCOPE_PREFIX . $userPublicId, self::PREF_NAME, $active);

        return $this->success('TASK_SIDEBAR_SAVED', $this->t('task/messages.sidebar_saved'), [
            'catalog' => $this->buildCatalog($user),
            'active' => $active,
        ]);
    }

    private function loadPreference(string $userPublicId): array
    {
        if ($userPublicId === '' || !$this->container->has('service.setting')) {
            return [];
        }

        try {
            /** @var \Api\System\Library\Service\SettingService $settingService */
            $settingService = $this->container->get('service.setting');
            $setting = $settingService->get(self::PREF_SCOPE_PREFIX . $userPublicId, self::PREF_NAME);
            if ($setting === null) {
                return [];
            }
            $value = $setting['value'] ?? [];
            return is_array($value) ? $value : [];
        } catch (\Throwable $e) {
            error_log('[TaskSidebarController::loadPreference] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array<int, mixed> $raw
     * @return array<int, string>
     */
    private function normalizeActive(array $raw): array
    {
        $known = $this->knownKeys();
        $seen = [];
        $active = [];
        foreach ($raw as $key) {
            if (!is_string($key) || !isset($known[$key])) {
                continue;
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $active[] = $key;
        }

        return $active;
    }

    /**
     * @return array<string, bool>
     */
    private function knownKeys(): array
    {
        $known = [];
        foreach (array_keys(self::CORE_BLOCKS) as $key) {
            $known[$key] = true;
        }
        foreach ($this->moduleBlockDefinitions() as $key => $definition) {
            $known[$key] = true;
        }

        return $known;
    }

    /**
     * @param array<int, string> $stored
     * @param array<string, mixed> $user
     * @return array<int, string>
     */
    private function resolveActive(array $stored, array $user): array
    {
        $candidate = [];
        if ($stored === []) {
            foreach (self::CORE_BLOCKS as $key => $definition) {
                if ((bool)($definition['default_enabled'] ?? false)) {
                    $candidate[] = $key;
                }
            }
            // Module blocks are appended after core blocks on first use.
            foreach (array_keys($this->moduleBlockDefinitions()) as $key) {
                $candidate[] = $key;
            }
        } else {
            $candidate = $this->normalizeActive($stored);
        }

        return array_values(array_filter($candidate, fn (string $key): bool => $this->blockAllowed($key, $user)));
    }

    private function blockAllowed(string $key, array $user): bool
    {
        $definition = self::CORE_BLOCKS[$key] ?? null;
        if (!is_array($definition)) {
            // Module blocks are gated by their renderer; treat as allowed here.
            return true;
        }

        $required = array_values(array_filter(array_map('strval', (array)($definition['permissions'] ?? []))));
        if ($required === []) {
            return true;
        }

        if ((bool)($user['is_root'] ?? false)) {
            return true;
        }

        if (!$this->container->has('service.authz')) {
            error_log('[TaskSidebarController::blockAllowed] Authorization service is unavailable');
            return false;
        }

        try {
            /** @var \Api\System\Library\Service\AuthzService $authz */
            $authz = $this->container->get('service.authz');
            return $authz->hasPermissions($user, $required);
        } catch (\Throwable $e) {
            error_log('[TaskSidebarController::blockAllowed] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return array<int, array<string, mixed>>
     */
    private function buildCatalog(array $user): array
    {
        $catalog = [];
        foreach (self::CORE_BLOCKS as $key => $definition) {
            if (!$this->blockAllowed($key, $user)) {
                continue;
            }
            $catalog[] = [
                'key' => $key,
                'label_key' => $definition['label_key'],
                'label' => $definition['label'],
                'description_key' => $definition['description_key'],
                'description' => $definition['description'],
                'permissions' => $definition['permissions'] ?? [],
                'default_enabled' => (bool)($definition['default_enabled'] ?? false),
            ];
        }

        foreach ($this->moduleBlockDefinitions() as $key => $definition) {
            $catalog[] = [
                'key' => $key,
                'label_key' => $definition['label_key'],
                'label' => $definition['label'],
                'description_key' => $definition['description_key'],
                'description' => $definition['description'],
                'permissions' => [],
                'default_enabled' => true,
            ];
        }

        return $catalog;
    }

    /**
     * Discover module blocks registered for the "task.detail.sidebar" position.
     *
     * @return array<string, array{label_key: string, label: string, description_key: string, description: string}>
     */
    private function moduleBlockDefinitions(): array
    {
        $definitions = [];
        if (!$this->container->has('plugin.manager')) {
            return $definitions;
        }

        try {
            /** @var \Api\System\Library\Module\PluginManager $pluginManager */
            $pluginManager = $this->container->get('plugin.manager');
            foreach ($pluginManager->getActive() as $manifest) {
                $entries = $manifest->positions[self::SIDEBAR_POSITION] ?? [];
                if (!is_array($entries)) {
                    continue;
                }
                foreach ($entries as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $renderer = (string)($entry['renderer'] ?? '');
                    if ($renderer === '') {
                        continue;
                    }
                    $key = (string)($entry['key'] ?? '');
                    if ($key === '') {
                        $key = self::deriveBlockKey($renderer);
                    }
                    if ($key === '' || isset($definitions[$key])) {
                        continue;
                    }
                    $label = (string)($entry['label'] ?? '');
                    if ($label === '') {
                        $label = ucfirst(str_replace('_', ' ', $key));
                    }
                    $definitions[$key] = [
                        'label_key' => (string)($entry['label_key'] ?? ''),
                        'label' => $label,
                        'description_key' => (string)($entry['description_key'] ?? ''),
                        'description' => (string)($entry['description'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('[TaskSidebarController::moduleBlockDefinitions] ' . $e->getMessage());
        }

        return $definitions;
    }

    /**
     * Derive a stable block key from a renderer "Class::method" string.
     * MUST match Web\System\Module\PositionRegistry::deriveBlockKey().
     */
    private static function deriveBlockKey(string $renderer): string
    {
        $class = str_contains($renderer, '::') ? explode('::', $renderer, 2)[0] : $renderer;
        $parts = explode('\\', $class);
        $short = end($parts);
        $short = is_string($short) ? $short : '';
        $snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        $snake = (string)preg_replace('/[^a-z0-9_]+/', '_', $snake);

        return trim($snake, '_');
    }
}
