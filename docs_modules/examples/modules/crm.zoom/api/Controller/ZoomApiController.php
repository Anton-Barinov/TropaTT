<?php

declare(strict_types=1);

namespace Module\Crm\Zoom\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\Zoom\Service\ZoomService;
use Throwable;

final class ZoomApiController extends BaseController
{
    private function workspaceId(): int
    {
        $actor = $this->user();
        return (int)($actor['user']['workspace_id'] ?? $actor['user']['organization_id'] ?? 0);
    }

    private function actorUserId(): int
    {
        $actor = $this->user();
        return (int)($actor['user']['id'] ?? $actor['user']['user_id'] ?? 0);
    }

    private function service(): ZoomService
    {
        $auth = $this->user();
        $actor = is_array($auth['user'] ?? null) ? $this->organizationScopedActor((array)$auth['user']) : [];
        $actor['organization_id'] = $this->workspaceId();
        $calendar = $this->container->get('service.calendar');
        $creator = static fn(array $input, array $source): array|string => $calendar->createEvent($input, $actor, $source);
        $afterCommit = static function (array $event) use ($calendar, $actor): void { $calendar->dispatchCreatedAfterCommit($event, $actor); };
        return new ZoomService($this->container->get('db.pdo'), $this->workspaceId(), $this->actorUserId(), null, $creator, $afterCommit);
    }

    public function getStatus(): JsonResponse
    {
        if ($this->workspaceId() <= 0) return $this->error('ACTIVE_WORKSPACE_REQUIRED', 'An active workspace is required.', 409);
        try { return $this->success('ZOOM_STATUS', 'Zoom status retrieved.', $this->service()->getStatus()); }
        catch (Throwable) { return $this->error('ZOOM_STATUS_UNAVAILABLE', 'Zoom settings are unavailable.', 503); }
    }

    public function saveSettings(): JsonResponse
    {
        if ($this->workspaceId() <= 0) return $this->error('ACTIVE_WORKSPACE_REQUIRED', 'An active workspace is required.', 409);
        try {
            $this->service()->saveSettings($this->request()->allInput());
            return $this->success('ZOOM_SETTINGS_SAVED', 'Zoom credentials were encrypted and saved for this workspace.', $this->service()->getStatus());
        } catch (Throwable $e) {
            return $this->error($this->safeCode($e), 'Zoom settings were not saved. Check the fields and APP_KEY.', 422);
        }
    }

    public function testConnection(): JsonResponse
    {
        if ($this->workspaceId() <= 0) return $this->error('ACTIVE_WORKSPACE_REQUIRED', 'An active workspace is required.', 409);
        try { return $this->success('ZOOM_CONNECTION_VERIFIED', 'Zoom host account verified.', $this->service()->testConnection()); }
        catch (Throwable $e) { return $this->error($this->safeCode($e), 'Zoom connection could not be verified. Check credentials, host user ID, and the required Zoom scopes.', 422); }
    }

    public function createMeeting(): JsonResponse
    {
        if ($this->workspaceId() <= 0) return $this->error('ACTIVE_WORKSPACE_REQUIRED', 'An active workspace is required.', 409);
        $key = trim((string)($this->request()->header('Idempotency-Key') ?? $this->request()->header('X-Idempotency-Key') ?? ''));
        try {
            $result = $this->service()->createMeeting($this->request()->allInput(), $key);
            return $this->success('ZOOM_MEETING_CREATED', 'Zoom meeting was created and added to the CRM calendar.', $result);
        } catch (Throwable $e) {
            $code = $this->safeCode($e);
            $status = $code === 'ZOOM_CREATE_RESULT_UNKNOWN' ? 409 : ($code === 'ZOOM_IDEMPOTENCY_CONFLICT' ? 409 : 422);
            return $this->error($code, $code === 'ZOOM_CREATE_RESULT_UNKNOWN'
                ? 'Zoom may have created this meeting, but the result could not be confirmed. Do not retry with a new key; reconcile it in Zoom first.'
                : 'Zoom meeting was not confirmed. Check the fields and connection before retrying.', $status);
        }
    }

    public function listMeetings(): JsonResponse
    {
        if ($this->workspaceId() <= 0) return $this->error('ACTIVE_WORKSPACE_REQUIRED', 'An active workspace is required.', 409);
        try { return $this->success('ZOOM_MEETINGS_LIST', 'Workspace meetings retrieved.', ['items' => $this->service()->listMeetings()]); }
        catch (Throwable) { return $this->error('ZOOM_MEETINGS_UNAVAILABLE', 'Zoom meetings are unavailable.', 503); }
    }

    private function safeCode(Throwable $e): string
    {
        $code = $e->getMessage();
        return preg_match('/^ZOOM_[A-Z_]+$/', $code) ? $code : 'ZOOM_OPERATION_FAILED';
    }
}
