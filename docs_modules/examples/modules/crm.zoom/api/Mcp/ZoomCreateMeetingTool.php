<?php

declare(strict_types=1);

namespace Module\Crm\Zoom\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\Zoom\Service\ZoomService;
use Throwable;

final class ZoomCreateMeetingTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if ($context->organizationId <= 0 || $context->organizationPublicId === null || $context->actorPublicId === null) return ['success' => false, 'error' => 'ACTIVE_WORKSPACE_OR_ACTOR_REQUIRED'];
        if (!$container->has('auth_user')) return ['success' => false, 'error' => 'ACTIVE_WORKSPACE_OR_ACTOR_REQUIRED'];
        $auth = $container->get('auth_user');
        $actor = is_array($auth['user'] ?? null) ? $auth['user'] : [];
        if ((int)($actor['organization_id'] ?? 0) !== $context->organizationId || ($actor['public_id'] ?? null) !== $context->actorPublicId) return ['success' => false, 'error' => 'ACTOR_CONTEXT_MISMATCH'];
        $key = trim((string)($arguments['idempotency_key'] ?? ''));
        unset($arguments['idempotency_key']);
        $userId = (int)($actor['id'] ?? $actor['user_id'] ?? 0);
        if ($userId <= 0 || $key === '') return ['success' => false, 'error' => 'ZOOM_IDEMPOTENCY_KEY_REQUIRED'];
        try {
            $db = $container->get('db.pdo');
            if (!$db instanceof \PDO) return ['success' => false, 'error' => 'DATABASE_UNAVAILABLE'];
            $actor['organization_id'] = $context->organizationId;
            $calendar = $container->get('service.calendar');
            $creator = static fn(array $input, array $source): array|string => $calendar->createEvent($input, $actor, $source);
            $afterCommit = static function (array $event) use ($calendar, $actor): void { $calendar->dispatchCreatedAfterCommit($event, $actor); };
            $result = (new ZoomService($db, $context->organizationId, $userId, null, $creator, $afterCommit))->createMeeting($arguments, $key);
            return ['success' => true, 'result' => $result];
        } catch (Throwable $e) {
            $code = preg_match('/^ZOOM_[A-Z_]+$/', $e->getMessage()) ? $e->getMessage() : 'ZOOM_CREATE_FAILED';
            return ['success' => false, 'error' => $code, 'message' => $code === 'ZOOM_CREATE_RESULT_UNKNOWN' ? 'The outcome is uncertain. Do not retry with a new key until the meeting is checked in Zoom.' : 'Meeting creation was not confirmed. Check Zoom configuration and the required app scopes.'];
        }
    }
}
