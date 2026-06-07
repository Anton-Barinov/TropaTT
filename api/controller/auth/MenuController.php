<?php
declare(strict_types=1);

namespace Api\Controller\Auth;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AuthzService;

final class MenuController extends BaseController
{
    private const MENU_ITEMS = [
        ['key' => 'dashboard', 'i18n' => 'nav.dashboard', 'label' => 'Главная', 'href' => 'index.php?route=dashboard', 'permissions' => []],
        ['key' => 'ideas', 'i18n' => 'nav.ideas', 'label' => 'Идеи', 'href' => 'index.php?route=ideas', 'permissions' => []],
        ['key' => 'tasks', 'i18n' => 'nav.tasks', 'label' => 'Задачи', 'href' => 'index.php?route=tasks', 'permissions' => ['task.manage']],
        ['key' => 'day', 'i18n' => 'nav.day', 'label' => 'Мой день', 'href' => 'index.php?route=my-day', 'permissions' => ['task.manage']],
        ['key' => 'week', 'i18n' => 'nav.week', 'label' => 'Моя неделя', 'href' => 'index.php?route=my-week', 'permissions' => ['task.manage']],
        ['key' => 'kanban', 'i18n' => 'nav.kanban', 'label' => 'Канбан', 'href' => 'index.php?route=kanban', 'permissions' => ['task.manage']],
        ['key' => 'gantt', 'i18n' => 'nav.gantt', 'label' => 'Гант', 'href' => 'index.php?route=gantt', 'permissions' => ['project.manage']],
        ['key' => 'projects', 'i18n' => 'nav.projects', 'label' => 'Проекты', 'href' => 'index.php?route=projects', 'permissions' => ['project.manage']],
        ['key' => 'calendar', 'i18n' => 'nav.calendar', 'label' => 'Календарь', 'href' => 'index.php?route=calendar', 'permissions' => ['task.manage']],
        ['key' => 'counterparties', 'i18n' => 'nav.counterparties', 'label' => 'Контрагенты', 'href' => 'index.php?route=counterparties', 'permissions' => ['counterparty.manage']],
        ['key' => 'teams', 'i18n' => 'nav.teams', 'label' => 'Команды', 'href' => 'index.php?route=teams', 'permissions' => []],
        ['key' => 'analytics', 'i18n' => 'nav.analytics', 'label' => 'Аналитика', 'href' => 'index.php?route=analytics', 'permissions' => ['task.manage']],
        ['key' => 'time-analytics', 'i18n' => 'nav.time_analytics', 'label' => 'Учет времени', 'href' => 'index.php?route=time-analytics', 'permissions' => ['task.manage']],
        ['key' => 'notifications', 'i18n' => 'nav.notifications', 'label' => 'Уведомления', 'href' => 'index.php?route=notifications', 'permissions' => []],
        ['key' => 'profile', 'i18n' => 'nav.profile', 'label' => 'Профиль', 'href' => 'index.php?route=profile', 'permissions' => []],
        ['key' => 'admin', 'i18n' => 'nav.admin', 'label' => 'Администрирование', 'href' => 'index.php?route=admin', 'permissions' => ['user.view', 'role.view']],
        ['key' => 'admin-modules', 'i18n' => 'nav.admin_modules', 'label' => 'Модули', 'href' => 'index.php?route=admin-modules', 'permissions' => ['role.view']],
        ['key' => 'chat', 'i18n' => 'nav.chat', 'label' => 'Чаты', 'href' => 'index.php?route=chat', 'permissions' => []],
        ['key' => 'docs', 'i18n' => 'nav.api', 'label' => 'Справка', 'href' => 'index.php?route=docs', 'permissions' => []],
    ];

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
                    'label' => $item['label'] ?? $item['key'],
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

        return $this->success('MENU_LIST', $this->t('auth/messages.menu_loaded'), [
            'items' => $availableItems,
        ]);
    }

    private function canAccess(AuthzService $authz, array $user, array $requiredPermissions): bool
    {
        if ($requiredPermissions === []) {
            return true;
        }

        return $authz->hasPermissions($user, $requiredPermissions);
    }
}
