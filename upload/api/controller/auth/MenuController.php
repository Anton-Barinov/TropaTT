<?php
declare(strict_types=1);

namespace Api\Controller\Auth;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AuthzService;

final class MenuController extends BaseController
{
    private const MENU_ITEMS = [
        ['key' => 'dashboard', 'i18n' => 'nav.dashboard', 'label_key' => 'nav.dashboard', 'href' => 'index.php?route=dashboard', 'permissions' => []],
        ['key' => 'ideas', 'i18n' => 'nav.ideas', 'label_key' => 'nav.ideas', 'href' => 'index.php?route=ideas', 'permissions' => ['idea.view']],
        ['key' => 'tasks', 'i18n' => 'nav.tasks', 'label_key' => 'nav.tasks', 'href' => 'index.php?route=tasks', 'permissions' => ['task.manage']],
        ['key' => 'day', 'i18n' => 'nav.day', 'label_key' => 'nav.day', 'href' => 'index.php?route=my-day', 'permissions' => ['task.manage']],
        ['key' => 'week', 'i18n' => 'nav.week', 'label_key' => 'nav.week', 'href' => 'index.php?route=my-week', 'permissions' => ['task.manage'], 'default_hidden' => true],
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
        ['key' => 'my-earnings', 'i18n' => 'nav.my_earnings', 'label_key' => 'nav.my_earnings', 'href' => 'index.php?route=my-earnings', 'permissions' => ['finance.rate.view_own_payout'], 'default_hidden' => true],
        ['key' => 'notifications', 'i18n' => 'nav.notifications', 'label_key' => 'nav.notifications', 'href' => 'index.php?route=notifications', 'permissions' => []],
        // Admin entries mirror the server-side route gate in web/index.php
        // ($adminRoutePermissions): the same permission set that lets a user
        // load a page shell must also show its menu item, and vice versa.
        ['key' => 'admin', 'i18n' => 'nav.admin', 'label_key' => 'nav.admin', 'href' => 'index.php?route=admin', 'permissions' => ['user.view', 'role.view', 'logs.view', 'api_client.view']],
        // admin-estimates is gated by project.manage — there is no estimate.*
        // permission code (see web/index.php adminRoutePermissions comment).
        ['key' => 'admin-estimates', 'i18n' => 'nav.admin_estimates', 'label_key' => 'nav.admin_estimates', 'href' => 'index.php?route=admin-estimates', 'permissions' => ['project.manage']],
        ['key' => 'admin-modules', 'i18n' => 'nav.admin_modules', 'label_key' => 'nav.admin_modules', 'href' => 'index.php?route=admin-modules', 'permissions' => ['settings.manage']],
        ['key' => 'rate-cards', 'i18n' => 'nav.rate_cards', 'label_key' => 'nav.rate_cards', 'href' => 'index.php?route=rate-cards', 'permissions' => ['finance.ratecard.manage'], 'default_hidden' => true],
        ['key' => 'chat', 'i18n' => 'nav.chat', 'label_key' => 'nav.chat', 'href' => 'index.php?route=chat', 'permissions' => ['chat.use']],
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
                $entry = [
                    'key' => $item['key'],
                    'i18n' => $item['i18n'],
                    'label' => $this->t($item['label_key'] ?? $item['i18n']),
                    'href' => $item['href'],
                ];
                if (!empty($item['default_hidden'])) {
                    $entry['default_hidden'] = true;
                }
                $availableItems[] = $entry;
            }
        }

        // External guest users (client portal) get a hard nav allowlist. The
        // permission-based filter above already hid most internal-only items,
        // but 'dashboard'/'teams'/'notifications' carry no permission
        // requirement at all and would otherwise show for every authenticated
        // actor, and task.manage/project.manage (granted to external_guest so
        // RLS-scoped API calls work — see ExternalUsersMigration) would also
        // unlock kanban/gantt/calendar/day/week/cycles/analytics here. Module
        // nav items are skipped entirely for external actors since modules
        // are not guaranteed to be RLS-aware.
        // TZ 15.1: the "My earnings" item appears only when the actor has payout
        // data (available = true). Compute once so the external hard allowlist and
        // the internal permission filter agree on the same visibility.
        $myEarningsAvailable = $this->myEarningsAvailable($user);

        $isExternalActor = !empty((int)($user['is_external'] ?? 0));
        if ($isExternalActor) {
            $externalAllowedKeys = ['projects', 'tasks', 'notifications'];
            if ($myEarningsAvailable) {
                $externalAllowedKeys[] = 'my-earnings';
            }
            $availableItems = array_values(array_filter(
                $availableItems,
                static fn(array $item): bool => in_array($item['key'], $externalAllowedKeys, true)
            ));
        } elseif ($this->container->has('module.service_provider_registry')) {
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

        if (!$myEarningsAvailable) {
            $availableItems = array_values(array_filter(
                $availableItems,
                static fn(array $item): bool => $item['key'] !== 'my-earnings'
            ));
        }

        $userPublicId = (string)($user['public_id'] ?? '');
        $userInternalId = (int)($user['id'] ?? 0);
        $roleCodes = $user['roles'] ?? [];
        $rolePublicIds = $this->resolveRolePublicIds($roleCodes);

        $allAvailableItems = $availableItems;

        // Items marked default_hidden are available in the menu editor but
        // not shown in the default sidebar until the user adds them.
        $availableItems = array_values(array_filter($availableItems, static function (array $item): bool {
            return empty($item['default_hidden']);
        }));

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

    private function loadTeamTemplateByPublicId(string $teamPublicId): array
    {
        if ($teamPublicId === '' || !$this->container->has('service.setting')) {
            return [];
        }

        try {
            /** @var \Api\System\Library\Service\SettingService $settingService */
            $settingService = $this->container->get('service.setting');
            $scope = self::TEAM_SCOPE_PREFIX . $teamPublicId;
            $setting = $settingService->get($scope, self::TEMPLATE_NAME);
            if ($setting === null) {
                return [];
            }
            $value = $setting['value'] ?? [];
            return is_array($value) ? $value : [];
        } catch (\Throwable $e) {
            error_log('[MenuController::loadTeamTemplateByPublicId] ' . $e->getMessage());
            return [];
        }
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
        } catch (\Throwable $e) {
            error_log('[MenuController::loadTeamTemplate] ' . $e->getMessage());
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

            // CAST(... AS JSON) is MySQL-only; MariaDB rejects it with a syntax
            // error. JSON_CONTAINS accepts a JSON fragment literal, so bind the
            // encoded id directly - portable across MySQL and MariaDB.
            $uidJson = json_encode((int)$userId);
            $stmt = $pdo->prepare(
                'SELECT public_id FROM teams WHERE JSON_CONTAINS(member_user_ids, :uid_json)'
            );
            $stmt->execute([':uid_json' => $uidJson]);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $pid = (string)($row['public_id'] ?? '');
                if ($pid !== '' && !in_array($pid, $result, true)) {
                    $result[] = $pid;
                }
            }

            return $result;
        } catch (\Throwable $e) {
            error_log('[MenuController::findUserTeamPublicIds] ' . $e->getMessage());
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
        } catch (\Throwable $e) {
            error_log('[MenuController::loadUserPreferences] ' . $e->getMessage());
            return [];
        }
    }

    private function loadRoleTemplate(array $rolePublicIds): array
    {
        if (empty($rolePublicIds) || !$this->container->has('service.setting')) {
            return [];
        }

        try {
            /** @var \Api\System\Library\Service\SettingService $settingService */
            $settingService = $this->container->get('service.setting');

            $mergedVisMap = [];
            $orderFromFirst = null;

            foreach ($rolePublicIds as $rolePublicId) {
                $scope = self::ROLE_SCOPE_PREFIX . $rolePublicId;
                $setting = $settingService->get($scope, self::TEMPLATE_NAME);
                if ($setting === null) {
                    continue;
                }
                $template = $setting['value'] ?? [];
                if (!is_array($template)) {
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
                                $mergedVisMap[$key] = $mergedVisMap[$key] && $visible;
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
        } catch (\Throwable $e) {
            error_log('[MenuController::loadRoleTemplate] ' . $e->getMessage());
            return [];
        }
    }

    public function getRoleTemplate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $rolePublicId = (string)($params['public_id'] ?? '');
        if ($rolePublicId === '') {
            return $this->error('VALIDATION_ERROR', $this->t('common/messages.invalid_request'), 422);
        }

        if (!$this->container->has('service.setting')) {
            return $this->success('ROLE_MENU_TEMPLATE', $this->t('auth/messages.role_menu_template_loaded'), [
                'template' => [],
            ]);
        }

        try {
            /** @var \Api\System\Library\Service\SettingService $settingService */
            $settingService = $this->container->get('service.setting');
            $scope = self::ROLE_SCOPE_PREFIX . $rolePublicId;
            $setting = $settingService->get($scope, self::TEMPLATE_NAME);
            $template = $setting !== null ? ($setting['value'] ?? []) : [];
            if (!is_array($template)) {
                $template = [];
            }
        } catch (\Throwable $e) {
            error_log('[MenuController::getRoleTemplate] ' . $e->getMessage());
            $template = [];
        }

        return $this->success('ROLE_MENU_TEMPLATE', $this->t('auth/messages.role_menu_template_loaded'), [
            'template' => $template,
        ]);
    }

    public function saveRoleTemplate(array $params): \Api\System\Library\Http\JsonResponse
    {
        $rolePublicId = (string)($params['public_id'] ?? '');
        if ($rolePublicId === '') {
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

        $scope = self::ROLE_SCOPE_PREFIX . $rolePublicId;
        /** @var \Api\System\Library\Service\SettingService $settingService */
        $settingService = $this->container->get('service.setting');
        $settingService->set($scope, self::TEMPLATE_NAME, $validated);

        return $this->success('ROLE_MENU_TEMPLATE_SAVED', $this->t('auth/messages.role_menu_template_saved'), [
            'template' => $validated,
        ]);
    }

    private function applyRoleTemplate(array $items, array $template): array
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
                if ($entry['title'] === '' || !$this->isSafeMenuHref($entry['href'])) {
                    continue;
                }
            }

            $validated[] = $entry;
        }

        return $validated;
    }

    private function isSafeMenuHref(string $href): bool
    {
        $href = trim($href);
        if ($href === '' || preg_match('/[\x00-\x1F\x7F]/', $href) === 1) {
            return false;
        }

        $decoded = $href;
        for ($i = 0; $i < 2; $i++) {
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) {
            return false;
        }

        if (str_starts_with($decoded, '//') || str_starts_with($decoded, '\\\\')) {
            return false;
        }

        $scheme = parse_url($decoded, PHP_URL_SCHEME);
        return $scheme === null || in_array(strtolower((string)$scheme), ['http', 'https', 'mailto', 'tel'], true);
    }

    private function canAccess(AuthzService $authz, array $user, array $requiredPermissions): bool
    {
        // Any-of semantics: matches the web page-shell gate
        // (crmWebApiCheckAnyPermission in web/index.php) so a menu item shows
        // exactly when its page shell can be loaded.
        return $authz->hasAnyPermissions($user, $requiredPermissions);
    }

    /**
     * Lightweight "My earnings" availability check (TZ 7.2 / 15.1): true when the
     * actor has at least one worklog with a payout snapshot or a user-level
     * payout_rate. Strictly self-scoped; reveals no values, only a boolean.
     */
    private function myEarningsAvailable(array $user): bool
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        try {
            /** @var \PDO $pdo */
            $pdo = $this->container->get('db.pdo');

            $stmt = $pdo->prepare(
                'SELECT 1 FROM work_logs WHERE user_id = :uid AND payout_rate_snapshot IS NOT NULL LIMIT 1'
            );
            $stmt->execute([':uid' => $userId]);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }

            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = :uid AND payout_rate IS NOT NULL LIMIT 1');
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            error_log('[MenuController::myEarningsAvailable] ' . $e->getMessage());
            return false;
        }
    }

    private function resolveRolePublicIds(array $roleCodes): array
    {
        if (empty($roleCodes)) {
            return [];
        }

        try {
            /** @var \PDO $pdo */
            $pdo = $this->container->get('db.pdo');
            $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
            $stmt = $pdo->prepare("SELECT public_id FROM roles WHERE code IN ($placeholders)");
            $stmt->execute($roleCodes);
            $result = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $pid = (string)($row['public_id'] ?? '');
                if ($pid !== '') {
                    $result[] = $pid;
                }
            }
            return $result;
        } catch (\Throwable $e) {
            error_log('[MenuController::resolveRolePublicIds] ' . $e->getMessage());
            return [];
        }
    }
}
