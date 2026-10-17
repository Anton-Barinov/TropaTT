<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\StarterKits\Service\StarterKitService;

final class StarterKitsApiController extends BaseController
{
    private function service(): StarterKitService
    {
        $events = $this->container->has('module.domain_event')
            ? $this->container->get('module.domain_event')
            : null;
        return new StarterKitService($this->container->get('db.pdo'), null, $events);
    }

    private function actorContext(): array
    {
        $actor = $this->user()['user'] ?? [];
        return [
            'organization_id' => (int)($actor['organization_id'] ?? 0),
            'user_id' => (int)($actor['id'] ?? 0),
            'is_root' => (bool)($actor['is_root'] ?? false),
        ];
    }

    private function locale(): string
    {
        $locale = $this->request()->input('locale');
        if (!is_string($locale) || trim($locale) === '') {
            $locale = $this->container->get('lang')->locale();
        }
        return substr(trim((string)$locale), 0, 35);
    }

    public function listKits(): JsonResponse
    {
        try {
            $kits = array_map(static function (array $kit): array {
                $kit['kit_code'] = (string)($kit['id'] ?? '');
                unset($kit['id']);
                return $kit;
            }, $this->service()->listKits($this->locale()));
            return $this->success('STARTER_KITS_LISTED', 'Список стартовых наборов загружен.', [
                'kits' => $kits,
            ]);
        } catch (\Throwable $e) {
            $this->logError('Starter kit catalog could not be loaded.', ['error' => $e->getMessage()]);
            return $this->error('STARTER_KITS_UNAVAILABLE', 'Не удалось загрузить список стартовых наборов.', 500);
        }
    }

    public function preview(): JsonResponse
    {
        $input = $this->request()->allInput();
        $kitId = is_string($input['kit_id'] ?? null) ? trim($input['kit_id']) : '';
        if ($kitId === '' || strlen($kitId) > 80) {
            return $this->error('VALIDATION_ERROR', 'Укажите корректный идентификатор набора.', 422);
        }
        $organizationId = $this->actorContext()['organization_id'];
        if ($organizationId <= 0) {
            return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Не выбрано рабочее пространство.', 409);
        }

        try {
            $actor = $this->actorContext();
            $preview = $this->service()->preview($kitId, $organizationId, $this->locale());
            $preview['can_manage_global_roles'] = $actor['is_root'];
            return $this->success('STARTER_KIT_PREVIEW_READY', 'Предпросмотр готов.', [
                'preview' => $preview,
            ]);
        } catch (\RuntimeException $e) {
            if (preg_match("/^Starter kit '[^']{1,80}' not found\\.$/", $e->getMessage()) === 1) {
                return $this->error('STARTER_KIT_NOT_FOUND', 'Стартовый набор не найден.', 404);
            }
            $this->logError('Starter kit preview failed.', ['error' => $e->getMessage()]);
            return $this->error('STARTER_KIT_PREVIEW_FAILED', 'Не удалось подготовить предпросмотр.', 500);
        } catch (\Throwable $e) {
            $this->logError('Starter kit preview failed.', ['error' => $e->getMessage()]);
            return $this->error('STARTER_KIT_PREVIEW_FAILED', 'Не удалось подготовить предпросмотр.', 500);
        }
    }

    public function apply(): JsonResponse
    {
        $input = $this->request()->allInput();
        $kitId = is_string($input['kit_id'] ?? null) ? trim($input['kit_id']) : '';
        if ($kitId === '' || strlen($kitId) > 80) {
            return $this->error('VALIDATION_ERROR', 'Укажите корректный идентификатор набора.', 422);
        }
        $selected = $input['selected_components'] ?? null;
        if (!is_array($selected) || $selected === [] || count($selected) > 5) {
            return $this->error('VALIDATION_ERROR', 'Проверьте список выбранных компонентов.', 422);
        }
        foreach ($selected as $component) {
            if (!is_string($component) || !in_array($component, ['statuses', 'roles', 'project_templates', 'task_templates', 'knowledge_spaces'], true)) {
                return $this->error('VALIDATION_ERROR', 'Список содержит неизвестный компонент.', 422);
            }
        }
        if (in_array('roles', $selected, true) && !$this->actorContext()['is_root']) {
            return $this->error('FORBIDDEN', 'Создавать глобальные роли может только главный администратор.', 403);
        }

        $context = $this->actorContext();
        if ($context['organization_id'] <= 0 || $context['user_id'] <= 0) {
            return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Не удалось определить рабочее пространство пользователя.', 409);
        }
        try {
            $result = $this->service()->apply(
                $kitId,
                $context['organization_id'],
                $context['user_id'],
                $selected,
                $this->locale(),
                $context['is_root']
            );
            if (in_array('roles', $selected, true)) {
                $this->invalidateCache('role');
                $this->invalidateCache('permission');
            }
            if (in_array('statuses', $selected, true)) {
                $this->invalidateCache('status');
            }
            if (in_array('project_templates', $selected, true)) {
                $this->invalidateCache('project');
            }
            if (in_array('task_templates', $selected, true)) {
                $this->invalidateCache('task');
            }
            if (in_array('knowledge_spaces', $selected, true)) {
                $this->invalidateCache('knowledge');
            }
            return $this->success('STARTER_KIT_APPLIED', 'Стартовый набор применён.', $result);
        } catch (\InvalidArgumentException $e) {
            return $this->error('VALIDATION_ERROR', 'Проверьте выбранный набор и его компоненты.', 422);
        } catch (\Throwable $e) {
            $this->logError('Starter kit application failed.', ['error' => $e->getMessage()]);
            $status = str_contains($e->getMessage(), 'Only a root administrator') ? 403 : 400;
            return $this->error($status === 403 ? 'FORBIDDEN' : 'STARTER_KIT_APPLY_FAILED', $status === 403 ? 'Создавать глобальные роли может только главный администратор.' : 'Не удалось применить набор. Изменения отменены.', $status);
        }
    }
}
