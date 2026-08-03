<?php
declare(strict_types=1);

namespace Api\Controller\Dashboard;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\DashboardService;

final class DashboardController extends BaseController
{
    private const WIDGET_USER_SCOPE_PREFIX = 'user:';
    private const WIDGET_PREF_NAME = 'dashboard_widgets';

    private const WIDGET_DEFINITIONS = [
        'kpi' => ['label_key' => 'dashboard.widget_kpi', 'label' => 'KPI metrics', 'default_enabled' => true],
        'quick_actions' => ['label_key' => 'dashboard.quick_actions', 'label' => 'Quick actions', 'default_enabled' => true],
        'ai_digest' => ['label_key' => 'dashboard.ai_digest_title', 'label' => 'AI daily digest', 'default_enabled' => true],
        'today_tasks' => ['label_key' => 'dashboard.today_tasks', 'label' => 'Tasks for today', 'default_enabled' => true],
        'risks' => ['label_key' => 'dashboard.risks_title', 'label' => 'Risks', 'default_enabled' => true],
        'activity' => ['label_key' => 'dashboard.activity_title', 'label' => 'Activity', 'default_enabled' => true],
        'projects_overview' => ['label_key' => 'dashboard.projects_overview', 'label' => 'Projects overview', 'default_enabled' => true],
        'knowledge' => ['label_key' => 'dashboard.knowledge_title', 'label' => 'Knowledge base', 'default_enabled' => true],
        'cycles' => ['label_key' => 'dashboard.cycles_title', 'label' => 'Active cycles', 'default_enabled' => true],
    ];

    public function summary(): \Api\System\Library\Http\JsonResponse
    {
        $authUser = $this->user();
        if (!$authUser) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var DashboardService $service */
        $service = $this->container->get('service.dashboard');
        $summary = $service->summary($authUser['user']);

        return $this->success('DASHBOARD_SUMMARY', $this->t('dashboard/messages.summary'), ['summary' => $summary]);
    }

    public function widgets(): \Api\System\Library\Http\JsonResponse
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

        $preferences = $this->loadWidgetPreferences($userPublicId);

        return $this->success('DASHBOARD_WIDGETS', $this->t('dashboard/messages.widgets'), [
            'widgets' => $this->buildWidgetList($preferences),
        ]);
    }

    public function saveWidgets(): \Api\System\Library\Http\JsonResponse
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

        $items = $this->request()->input('widgets');
        if (!is_array($items)) {
            return $this->error('VALIDATION_ERROR', $this->t('dashboard/messages.widgets_required'), 422);
        }

        $validated = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? null;
            if (!is_string($key) || !array_key_exists($key, self::WIDGET_DEFINITIONS)) {
                continue;
            }
            $validated[$key] = (bool)($item['enabled'] ?? true);
        }

        /** @var \Api\System\Library\Service\SettingService $settingService */
        $settingService = $this->container->get('service.setting');
        $settingService->set(self::WIDGET_USER_SCOPE_PREFIX . $userPublicId, self::WIDGET_PREF_NAME, $validated);

        return $this->success('DASHBOARD_WIDGETS_SAVED', $this->t('dashboard/messages.widgets_saved'), [
            'widgets' => $this->buildWidgetList($validated),
        ]);
    }

    private function loadWidgetPreferences(string $userPublicId): array
    {
        if ($userPublicId === '' || !$this->container->has('service.setting')) {
            return [];
        }

        try {
            /** @var \Api\System\Library\Service\SettingService $settingService */
            $settingService = $this->container->get('service.setting');
            $setting = $settingService->get(self::WIDGET_USER_SCOPE_PREFIX . $userPublicId, self::WIDGET_PREF_NAME);
            if ($setting === null) {
                return [];
            }
            $value = $setting['value'] ?? [];
            return is_array($value) ? $value : [];
        } catch (\Throwable $e) {
            error_log('[DashboardController::loadWidgetPreferences] ' . $e->getMessage());
            return [];
        }
    }

    private function buildWidgetList(array $preferences): array
    {
        $list = [];
        foreach (self::WIDGET_DEFINITIONS as $key => $definition) {
            $list[] = [
                'key' => $key,
                'label_key' => $definition['label_key'],
                'label' => $definition['label'],
                'enabled' => array_key_exists($key, $preferences)
                    ? (bool)$preferences[$key]
                    : (bool)($definition['default_enabled'] ?? true),
                'default_enabled' => (bool)($definition['default_enabled'] ?? true),
            ];
        }

        return $list;
    }
}
