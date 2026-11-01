<?php
declare(strict_types=1);

namespace Api\Controller\Install;

use Api\System\Library\Support\AppLog;
use Api\Controller\Common\BaseController;
use Api\System\Library\Service\MigrationService;
use Throwable;

final class MigrationController extends BaseController
{
    private function isRoot(): bool
    {
        $auth = $this->user();
        if (!$auth || !isset($auth['user'])) {
            return false;
        }
        $rawUser = (array)$auth['user'];
        if ((bool)($rawUser['is_root'] ?? false)) {
            return true;
        }
        $actor = $this->organizationScopedActor($rawUser);
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }
        $roles = is_array($actor['roles'] ?? null) ? $actor['roles'] : [];
        $normalizedRoles = array_values(array_unique(array_filter(array_map(
            static function (mixed $role): string {
                if (is_array($role)) {
                    $role = $role['code'] ?? $role['public_id'] ?? $role['name'] ?? '';
                }
                return strtolower(str_replace('-', '_', trim(is_scalar($role) ? (string)$role : '')));
            },
            $roles
        ), static fn(string $role): bool => $role !== '')));
        return array_intersect($normalizedRoles, ['super_admin', 'super_administrator', 'root']) !== [];
    }

    public function status(): \Api\System\Library\Http\JsonResponse
    {
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        if (!$this->isRoot()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => [$this->t('install/messages.root_required')],
            ]);
        }

        /** @var MigrationService $service */
        $service = $this->container->get('service.migration');

        try {
            return $this->success('MIGRATION_STATUS', $this->t('install/messages.migration_status'), $service->status());
        } catch (Throwable $e) {
            AppLog::error('[MigrationController::status] ' . $e->getMessage());
            return $this->error('MIGRATION_STATUS_FAILED', $this->t('install/messages.migration_status_failed'), 422, [
                'migration' => ['Migration status check failed. Check server logs for details.'],
            ]);
        }
    }

    public function up(): \Api\System\Library\Http\JsonResponse
    {
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        if (!$this->isRoot()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => [$this->t('install/messages.root_required')],
            ]);
        }

        /** @var MigrationService $service */
        $service = $this->container->get('service.migration');

        try {
            return $this->success('MIGRATION_UP_DONE', $this->t('install/messages.migration_up_done'), $service->up());
        } catch (Throwable $e) {
            AppLog::error('[MigrationController::up] ' . $e->getMessage());
            return $this->error('MIGRATION_UP_FAILED', $this->t('install/messages.migration_up_failed'), 422, [
                'migration' => ['Migration execution failed. Check server logs for details.'],
            ]);
        }
    }

    public function dryRun(): \Api\System\Library\Http\JsonResponse
    {
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        if (!$this->isRoot()) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => [$this->t('install/messages.root_required')],
            ]);
        }

        /** @var MigrationService $service */
        $service = $this->container->get('service.migration');

        try {
            return $this->success('MIGRATION_DRY_RUN', $this->t('install/messages.migration_dry_run'), $service->dryRun());
        } catch (Throwable $e) {
            AppLog::error('[MigrationController::dryRun] ' . $e->getMessage());
            return $this->error('MIGRATION_DRY_RUN_FAILED', $this->t('install/messages.migration_dry_run_failed'), 422, [
                'migration' => ['Migration dry run failed. Check server logs for details.'],
            ]);
        }
    }

    public function rollbackCheck(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => [$this->t('install/messages.root_required')],
            ]);
        }

        /** @var MigrationService $service */
        $service = $this->container->get('service.migration');

        try {
            return $this->success('MIGRATION_ROLLBACK_CHECK', $this->t('install/messages.migration_rollback_check'), $service->rollbackCheck());
        } catch (Throwable $e) {
            AppLog::error('[MigrationController::rollbackCheck] ' . $e->getMessage());
            return $this->error('MIGRATION_ROLLBACK_CHECK_FAILED', $this->t('install/messages.migration_rollback_check_failed'), 422, [
                'migration' => ['Migration rollback check failed. Check server logs for details.'],
            ]);
        }
    }
}
