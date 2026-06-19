<?php
declare(strict_types=1);

namespace Api\Controller\Auth;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AuthzService;

final class MenuController extends BaseController
{
    private const MENU_ITEMS = [
        ['key' => 'dashboard', 'i18n' => 'nav.dashboard', 'label_key' => 'nav.dashboard', 'href' => 'index.php?route=dashboard', 'permissions' => []],
        ['key' => 'ideas', 'i18n' => 'nav.ideas', 'label_key' => 'nav.ideas', 'href' => 'index.php?route=ideas', 'permissions' => []],
        ['key' => 'tasks', 'i18n' => 'nav.tasks', 'label_key' => 'nav.tasks', 'href' => 'index.php?route=tasks', 'permissions' => ['task.manage']],
        ['key' => 'day', 'i18n' => 'nav.day', 'label_key' => 'nav.day', 'href' => 'index.php?route=my-day', 'permissions' => ['task.manage']],
        ['key' => 'week', 'i18n' => 'nav.week', 'label_key' => 'nav.week', 'href' => 'index.php?route=my-week', 'permissions' => ['task.manage']],
        ['key' => 'kanban', 'i18n' => 'nav.kanban', 'label_key' => 'nav.kanban', 'href' => 'index.php?route=kanban', 'permissions' => ['task.manage']],
        ['key' => 'gantt', 'i18n' => 'nav.gantt', 'label_key' => 'nav.gantt', 'href' => 'index.php?route=gantt', 'permissions' => ['project.manage']],
        ['key' => 'projects', 'i18n' => 'nav.projects', 'label_key' => 'nav.projects', 'href' => 'index.php?route=projects', 'permissions' => ['project.manage']],
        ['key' => 'calendar', 'i18n' => 'nav.calendar', 'label_key' => 'nav.calendar', 'href' => 'index.php?route=calendar', 'permissions' => ['task.manage']],
        ['key' => 'counterparties', 'i18n' => 'nav.counterparties', 'label_key' => 'nav.counterparties', 'href' => 'index.php?route=counterparties', 'permissions' => ['counterparty.manage']],
        ['key' => 'teams', 'i18n' => 'nav.teams', 'label_key' => 'nav.teams', 'href' => 'index.php?route=teams', 'permissions' => []],
        ['key' => 'intake', 'i18n' => 'nav.intake', 'label_key' => 'nav.intake', 'href' => 'index.php?route=intake', 'permissions' => ['intake.view']],
        ['key' => 'cycles', 'i18n' => 'nav.cycles', 'label_key' => 'nav.cycles', 'href' => 'index.php?route=cycles', 'permissions' => ['task.manage']],
        ['key' => 'knowledge', 'i18n' => 'nav.knowledge', 'label_key' => 'nav.knowledge', 'href' => 'index.php?route=knowledge', 'permissions' => ['knowledge.view']],
        ['key' => 'analytics', 'i18n' => 'nav.analytics', 'label_key' => 'nav.analytics', 'href' => 'index.php?route=analytics', 'permissions' => ['task.manage']],
        ['key' => 'notifications', 'i18n' => 'nav.notifications', 'label_key' => 'nav.notifications', 'href' => 'index.php?route=notifications', 'permissions' => []],
        ['key' => 'admin', 'i18n' => 'nav.admin', 'label_key' => 'nav.admin', 'href' => 'index.php?route=admin', 'permissions' => ['user.view', 'role.view']],
        ['key' => 'admin-estimates', 'i18n' => 'nav.admin_estimates', 'label_key' => 'nav.admin_estimates', 'href' => 'index.php?route=admin-estimates', 'permissions' => ['estimate.view']],
        ['key' => 'admin-modules', 'i18n' => 'nav.admin_modules', 'label_key' => 'nav.admin_modules', 'href' => 'index.php?route=admin-modules', 'permissions' => ['role.view']],
        ['key' => 'chat', 'i18n' => 'nav.chat', 'label_key' => 'nav.chat', 'href' => 'index.php?route=chat', 'permissions' => []],
        ['key' => 'docs', 'i18n' => 'nav.api', 'label_key' => 'nav.api', 'href' => 'index.php?route=docs', 'permissions' => []],
    ];

    private const USER_SCOPE_PREFIX = 'user:';
    private const TEAM_SCOPE_PREFIX = 'team:';
    private const ROLE_SCOPE_PREFIX = 'role:';
    private const PREF_NAME = 'menu_preferences';
    private const TEMPLATE_NAME = 'menu_template';

    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $user = $authUser['user'] ?? [];
        if (!is_array($user)) {
            $user = [];
        }

        /** @var AuthzService $authz */
        $authz = $this->container->get('service.authz');

        $availableItems = [];
        foreach (self::MENU_ITEMS as $item) {
            $permissions = $item['permissions'] ?? [];
            if (!is_array($permissions)) {
                $permissions = [];
            }

            if ($this->canAccess($authz, $user, $permissions)) {
                $availableItems[] = [
                    'key' => $item['key'],
                    'i18n' => $item['i18n'],
                    'label' => $this->t($item['label_key'] ?? $item['i18n']),
                    'href' => $item['href'],
                ];
            }
        }

        if ($this->container->has('module.service_provider_registry')) {
            $spRegistry = $this->container->get('module.service_provider_registry');
            $moduleItems = $spRegistry->getAllMenuItems();
            foreach ($moduleItems as $item) {
                $perm = $item['permission'] ?? null;
                $required = $perm !== null && $perm !== '' ? [$perm] : [];
                if (!$this->canAccess($authz, $user, $required)) {
                    continue;
                }
                $availableItems[] = [
                    'key' => $item['route'],
                    'i18n' => $item['route'],
                    'label' => $item['label'],
                    'href' => 'index.php?route=' . $item['route'],
                    'icon' => $item['icon'] ?? null,
                    'parent' => $item['parent'] ?? null,
                ];
            }
        }

        $userPublicId = (string)($user['public_id'] ?? '');
        $userInternalId = (int)($user['id'] ?? 0);
        $rolePublicIds = $user['role_public_ids'] ?? [];

        $allAvailableItems = $availableItems;

        $roleTemplate = $this->loadRoleTemplate($rolePublicIds);
        $teamTemplate = $this->loadTeamTemplate($userInternalId);
        $userPreferences = $this->loadUserPreferences($userPublicId);

        $availableItems = $this->applyRoleTemplate($availableItems, $roleTemplate);
        $availableItems = $this->applyTeamTemplate($availableItems, $teamTemplate);
        $availableItems = $this->applyUserPreferences($availableItems, $userPreferences);

        return $this->success('MENU_LIST', $this->t('auth/messages.menu_loaded'), [
            'items' => $availableItems,
            'all_available_items' => $allAvailableItems,
        ]);
    }

    public function getPreferences(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $user = $authUser['user'] ?? [];
        $userPublicId = (string)($user['public_id'] ?? '');
        $userInternalId = (int)($user['id'] ?? 0);

        $teamTemplate = $this->loadTeamTemplate($userInternalId);
        $userPreferences = $this->loadUserPreferences($userPublicId);

        return $this->success('MENU_PREFERENCES', $this->t('auth/messages.menu_preferences_loaded'), [
            'team_template' => $teamTemplate,
            'user_preferences' => $userPreferences,
        ]);
    }

    public function savePreferences(): \Api\System\Library\Http\JsonResponse
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

        $items = $this->request()->input('items');
        if (!is_array($items)) {
            return $this->error('VALIDATION_ERROR', $this->t('auth/messages.items_required'), 422);
        }

        $validated = $this->validatePreferencesItems($items);

        $scope = self::USER_SCOPE_PREFIX . $userPublicId;
        /** @var \Api\System\Library\Service\SettingService $settingService */
        $settingService = $this->container->get('service.setting');
        $settingService->set($scope, self::PREF_NAME, $validated);

        return $this->success('MENU_PREFERENCES_SAVED', $this->t('auth/messages.menu_preferences_saved'), [
            'preferences' => $validated,
        ]);
    }

    public function adminGetUserPreferences(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $targetPublicId = (string)($params['public_id'] ?? '');
        if ($targetPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.invalid_request'), 422);
        }

        $preferences = $this->loadUserPreferences($targetPublicId);

        return $this->success('MENU_PREFERENCES', $this->t('auth/messages.menu_preferences_loaded'), [
            'preferences' => $preferences,
        ]);
    }

    public function adminSaveUserPreferences(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $targetPublicId = (string)($params['public_id'] ?? '');
        if ($targetPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.invalid_request'), 422);
        }

        $items = $this->request()->input('items');
        if (!is_array($items)) {
            return $this->error('VALIDATION_ERROR', $this->t('auth/messages.items_required'), 422);
        }

        $validated = $this->validatePreferencesItems($items);

        $scope = self::USER_SCOPE_PREFIX . $targetPublicId;
        /** @var \Api\System\Library\Service\SettingService $settingService */
        $settingService = $this->container->get('service.setting');
        $settingService->set($scope, self::PREF_NAME, $validated);

        return $this->success('MENU_PREFERENCES_SAVED', $this->t('auth/messages.menu_preferences_saved'), [
            'preferences' => $validated,
        ]);
    }

    public function getTeamTemplate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $teamPublicId = (string)($params['public_id'] ?? '');
        if ($teamPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.invalid_request'), 422);
        }

        $template = $this->loadTeamTemplateByPublicId($teamPublicId);

        return $this->success('TEAM_MENU_TEMPLATE', $this->t('auth/messages.team_menu_template_loaded'), [
            'template' => $template,
        ]);
    }

    public function saveTeamTemplate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $teamPublicId = (string)($params['public_id'] ?? '');
        if ($teamPublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.invalid_request'), 422);
        }

        $items = $this->request()->input('items');
        if (!is_array($items)) {
            return $this->error('VALIDATION_ERROR', $this->t('auth/messages.items_required'), 422);
        }

        $validated = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }
            $validated[] = [
                'key' => $key,
                'visible' => (bool)($item['visible'] ?? true),
            ];
        }

        $scope = self::TEAM_SCOPE_PREFIX . $teamPublicId;
        /** @var \Api\System\Library\Service\SettingService $settingService */
        $settingService = $this->container->get('service.setting');
        $settingService->set($scope, self::TEMPLATE_NAME, $validated);

        return $this->success('TEAM_MENU_TEMPLATE_SAVED', $this->t('auth/messages.team_menu_template_saved'), [
            'template' => $validated,
        ]);
    }

    private function loadTeamTemplate(int $userId): array
    {
        if ($userId <= 0 || !$this->container->has('service.setting')) {
            return [];
        }

        try {
            $teamPublicIds = $this->findUserTeamPublicIds($userId);
            if (empty($teamPublicIds)) {
                return [];
            }

            if (count($teamPublicIds) === 1) {
                return $this->loadTeamTemplateByPublicId($teamPublicIds[0]);
            }

            return $this->mergeTeamTemplates($teamPublicIds);
        } catch (\Throwable) {
            return [];
        }
    }

    private function mergeTeamTemplates(array $teamPublicIds): array
    {
        /** @var \Api\System\Library\Service\SettingService $settingService */
        $settingService = $this->container->get('service.setting');

        $mergedVisMap = [];
        $orderFromFirst = null;

        foreach ($teamPublicIds as $teamPublicId) {
            $template = $this->loadTeamTemplateByPublicId($teamPublicId);
            if (empty($template)) {
                continue;
            }

            $templateItems = $template['items'] ?? $template;
            if (!is_array($templateItems)) {
                continue;
            }

            if ($orderFromFirst === null) {
                $orderFromFirst = [];
                foreach ($templateItems as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $key = $entry['key'] ?? '';
                    if ($key !== '') {
                        $orderFromFirst[] = $key;
                        $mergedVisMap[$key] = (bool)($entry['visible'] ?? true);
                    }
                }
            } else {
                foreach ($templateItems as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $key = $entry['key'] ?? '';
                    if ($key !== '') {
                        $visible = (bool)($entry['visible'] ?? true);
                        if (!isset($mergedVisMap[$key])) {
                            $mergedVisMap[$key] = $visible;
                            $orderFromFirst[] = $key;
                        } else {
                            $mergedVisMap[$key] = $mergedVisMap[$key] || $visible;
                        }
                    }
                }
            }
        }

        if (empty($mergedVisMap)) {
            return [];
        }

        $result = [];
        foreach ($orderFromFirst as $key) {
            $result[] = [
                'key' => $key,
                'visible' => $mergedVisMap[$key],
            ];
        }

        return $result;
    }

    private function findUserTeamPublicIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            /** @var \PDO $pdo */
            $pdo = $this->container->get('db.pdo');

            $result = [];

            $stmt = $pdo->prepare(
                'SELECT public_id FROM teams WHERE manager_user_id = :uid'
            );
            $stmt->execute([':uid' => $userId]);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $pid = (string)($row['public_id'] ?? '');
                if ($pid !== '') {
                    $result[] = $pid;
                }
            }

            $stmt = $pdo->prepare(
                'SELECT public_id FROM teams WHERE JSON_CONTAINS(member_user_ids, CAST(:uid AS JSON))'
            );
            $stmt->execute([':uid' => (string)$userId]);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $pid = (string)($row['public_id'] ?? '');
                if ($pid !== '' && !in_array($pid, $result, true)) {
                    $result[] = $pid;
                }
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadUserPreferences(string $userPublicId): array
    {
        if ($userPublicId === '' || !$this->container->has('service.setting')) {
            return [];
        }

        try {
            /** @var \Api\System\Library\Service\SettingService $settingService */
            $settingService = $this->container->get('service.setting');
            $scope = self::USER_SCOPE_PREFIX . $userPublicId;
            $setting = $settingService->get($scope, self::PREF_NAME);
            if ($setting === null) {
                return [];
            }
            $value = $setting['value'] ?? [];
            return is_array($value) ? $value : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function applyTeamTemplate(array $items, array $template): array
    {
        if (empty($template)) {
            return $items;
        }

        $templateItems = $template['items'] ?? $template;
        if (!is_array($templateItems)) {
            return $items;
        }

        $visMap = [];
        foreach ($templateItems as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = $entry['key'] ?? '';
            if ($key !== '') {
                $visMap[$key] = (bool)($entry['visible'] ?? true);
            }
        }

        if (empty($visMap)) {
            return $items;
        }

        $filtered = [];
        foreach ($items as $item) {
            $key = $item['key'] ?? '';
            if (isset($visMap[$key]) && !$visMap[$key]) {
                continue;
            }
            $filtered[] = $item;
        }

        $itemMap = [];
        foreach ($filtered as $item) {
            $itemMap[$item['key'] ?? ''] = $item;
        }

        $reordered = [];
        foreach ($templateItems as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = $entry['key'] ?? '';
            if (isset($itemMap[$key])) {
                $reordered[] = $itemMap[$key];
                unset($itemMap[$key]);
            }
        }

        foreach ($itemMap as $item) {
            $reordered[] = $item;
        }

        return $reordered;
    }

    private function applyUserPreferences(array $items, array $preferences): array
    {
        if (empty($preferences)) {
            return $items;
        }

        $prefItems = $preferences['items'] ?? $preferences;
        if (!is_array($prefItems)) {
            return $items;
        }

        $visMap = [];
        $customItems = [];
        foreach ($prefItems as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = $entry['key'] ?? '';
            if ($key !== '') {
                $visMap[$key] = (bool)($entry['visible'] ?? true);
            }
            if (str_starts_with($key, 'custom_')) {
                $customItems[] = $entry;
            }
        }

        $filtered = [];
        foreach ($items as $item) {
            $key = $item['key'] ?? '';
            if (isset($visMap[$key]) && !$visMap[$key]) {
                continue;
            }
            $filtered[] = $item;
        }

        $itemMap = [];
        foreach ($filtered as $item) {
            $itemMap[$item['key'] ?? ''] = $item;
        }

        $reordered = [];
        foreach ($prefItems as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = $entry['key'] ?? '';
            if (isset($itemMap[$key])) {
                $reordered[] = $itemMap[$key];
                unset($itemMap[$key]);
            } elseif (str_starts_with($key, 'custom_')) {
                $reordered[] = [
                    'key' => $key,
                    'label' => $entry['title'] ?? $entry['label'] ?? $key,
                    'href' => $entry['href'] ?? '#',
                    'icon' => $entry['icon'] ?? null,
                    'is_custom' => true,
                ];
            }
        }

        foreach ($itemMap as $item) {
            $reordered[] = $item;
        }

        foreach ($customItems as $entry) {
            $key = $entry['key'] ?? '';
            $alreadyAdded = false;
            foreach ($reordered as $r) {
                if (($r['key'] ?? '') === $key) {
                    $alreadyAdded = true;
                    break;
                }
            }
            if (!$alreadyAdded) {
                $reordered[] = [
                    'key' => $key,
                    'label' => $entry['title'] ?? $entry['label'] ?? $key,
                    'href' => $entry['href'] ?? '#',
                    'icon' => $entry['icon'] ?? null,
                    'is_custom' => true,
                ];
            }
        }

        return $reordered;
    }

    private function validatePreferencesItems(array $items): array
    {
        $validated = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }

            $entry = [
                'key' => $key,
                'visible' => (bool)($item['visible'] ?? true),
            ];

            if (str_starts_with($key, 'custom_')) {
                $entry['title'] = trim((string)($item['title'] ?? $item['label'] ?? ''));
                $entry['href'] = trim((string)($item['href'] ?? ''));
                $entry['icon'] = trim((string)($item['icon'] ?? ''));
                if ($entry['title'] === '' || $entry['href'] === '') {
                    continue;
                }
            }

            $validated[] = $entry;
        }

        return $validated;
    }

    private function canAccess(AuthzService $authz, array $user, array $requiredPermissions): bool
    {
        if ($requiredPermissions === []) {
            return true;
        }

        return $authz->hasPermissions($user, $requiredPermissions);
    }
}
