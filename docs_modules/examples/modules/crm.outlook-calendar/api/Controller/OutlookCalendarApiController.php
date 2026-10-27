<?php

declare(strict_types=1);

namespace Module\Crm\OutlookCalendar\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Http\RawJsonResponse;
use Module\Crm\OutlookCalendar\Service\OutlookCalendarSyncService;
use Throwable;

final class OutlookCalendarApiController extends BaseController
{
    private function authorizeWorkspace(string $permission): array|JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !is_array($auth['user'] ?? null)) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }
        $user = (array)$auth['user'];
        $workspaceId = (int)($user['organization_id'] ?? 0);
        $userId = (int)($user['id'] ?? 0);
        if ($workspaceId <= 0 || $userId <= 0) {
            return $this->error('WORKSPACE_REQUIRED', 'Select an active workspace and sign in again.', 409);
        }
        if (empty($user['is_root']) && (!$this->container->has('service.authz')
            || !$this->container->get('service.authz')->hasPermissions($user, [$permission]))) {
            return $this->error('FORBIDDEN', 'Insufficient permissions for Outlook Calendar.', 403);
        }
        return ['user' => $user, 'workspace_id' => $workspaceId, 'user_id' => $userId];
    }

    private function service(array $access): OutlookCalendarSyncService
    {
        $calendar = $this->container->get('service.calendar');
        $actor = (array)$access['user'];
        $creator = static fn(array $input, array $eventActor, array $source): array|string => $calendar->createEvent($input, $eventActor, $source);
        $afterCommit = static function (array $event, array $eventActor) use ($calendar): void { $calendar->dispatchCreatedAfterCommit($event, $eventActor); };
        return new OutlookCalendarSyncService($this->container->get('db.pdo'), (int)$access['workspace_id'], null, $creator, $afterCommit);
    }

    public function getStatus(): JsonResponse
    {
        $access = $this->authorizeWorkspace('calendar.view');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        try {
            return $this->success('OUTLOOK_CALENDAR_STATUS', 'Connection status loaded.', $this->service($access)->status((int)$access['user_id']));
        } catch (Throwable) {
            return $this->error('OUTLOOK_CALENDAR_STATUS_FAILED', 'Unable to read Outlook connection status. Check that the module migrations have been applied.', 503);
        }
    }

    public function saveSettings(): JsonResponse
    {
        $access = $this->authorizeWorkspace('settings.edit');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        $input = $this->bodyInput();
        if ($input instanceof JsonResponse) {
            return $input;
        }
        try {
            $result = $this->service($access)->saveSettings(
                (string)($input['tenant_id'] ?? 'organizations'),
                (string)($input['client_id'] ?? ''),
                (string)($input['client_secret'] ?? ''),
                (string)($input['redirect_uri'] ?? '')
            );
            return $this->success('OUTLOOK_CALENDAR_SETTINGS_SAVED', 'Microsoft Entra settings saved. The client secret is encrypted and cannot be read back.', $result);
        } catch (Throwable) {
            return $this->error('OUTLOOK_CALENDAR_SETTINGS_INVALID', 'Settings could not be saved. Check the tenant, client ID, secret, HTTPS redirect URI, APP_KEY, and OpenSSL availability.', 422);
        }
    }

    public function startAuthorization(): JsonResponse
    {
        $access = $this->authorizeWorkspace('calendar.edit');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        try {
            $result = $this->service($access)->beginAuthorization((int)$access['user_id']);
            return $this->success('OUTLOOK_OAUTH_STARTED', 'Continue to Microsoft to authorize the primary calendar.', $result);
        } catch (Throwable) {
            return $this->error('OUTLOOK_OAUTH_START_FAILED', 'Authorization could not start. Save valid Entra settings and verify APP_KEY/OpenSSL.', 422);
        }
    }

    public function oauthCallback(): JsonResponse|RawJsonResponse
    {
        $access = $this->authorizeWorkspace('calendar.edit');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        $request = $this->request();
        $state = is_string($request->query['state'] ?? null) ? (string)$request->query['state'] : '';
        $providerError = is_string($request->query['error'] ?? null) ? (string)$request->query['error'] : '';
        if ($providerError !== '') {
            $this->service($access)->rejectAuthorization((int)$access['user_id'], $state);
            return $this->callbackRedirect('error');
        }
        $code = is_string($request->query['code'] ?? null) ? (string)$request->query['code'] : '';
        try {
            $this->service($access)->completeAuthorization((int)$access['user_id'], $state, $code);
            return $this->callbackRedirect('connected');
        } catch (Throwable) {
            return $this->callbackRedirect('error');
        }
    }

    private function callbackRedirect(string $result): JsonResponse|RawJsonResponse
    {
        try {
            $settings = $this->container->get('db.pdo')->prepare('SELECT redirect_uri FROM module_outlook_calendar_settings WHERE workspace_id = :workspace LIMIT 1');
            $settings->execute([':workspace' => (int)($this->user()['user']['organization_id'] ?? 0)]);
            $redirectUri = (string)$settings->fetchColumn();
            $parts = parse_url($redirectUri);
            if (!is_array($parts)) {
                throw new \RuntimeException('Invalid stored callback URL.');
            }
            $path = (string)($parts['path'] ?? '');
            $base = preg_replace('#/api/v1/modules/outlook-calendar/oauth/callback$#', '', $path);
            if (!is_string($base) || $base === $path || !isset($parts['scheme'], $parts['host'])) {
                throw new \RuntimeException('Invalid stored callback URL.');
            }
            $host = (string)($parts['host'] ?? '');
            $returnUrl = (string)$parts['scheme'] . '://' . $host . $base . '/web/index.php?route=module-outlook-calendar&outlook=' . rawurlencode($result);
            return new RawJsonResponse(null, 303, ['Location' => $returnUrl, 'Cache-Control' => 'no-store']);
        } catch (Throwable) {
            return $this->error('OUTLOOK_OAUTH_CALLBACK_FAILED', 'Microsoft account could not be connected. Return to the CRM module and start authorization again.', 400);
        }
    }

    public function sync(): JsonResponse
    {
        $access = $this->authorizeWorkspace('calendar.edit');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        try {
            $result = $this->service($access)->sync((int)$access['user_id']);
            return $this->success('OUTLOOK_CALENDAR_SYNC', 'Microsoft Graph calendar delta processed.', $result);
        } catch (Throwable $e) {
            $busy = str_contains($e->getMessage(), 'already running');
            return $this->error(
                $busy ? 'OUTLOOK_SYNC_BUSY' : 'OUTLOOK_SYNC_FAILED',
                $busy ? 'A sync is already running. Wait briefly, then refresh the status.' : 'Outlook could not be synchronized. Check connection, consent, and retry; CRM cursor state was not advanced for the failed page.',
                $busy ? 409 : 502
            );
        }
    }

    public function disconnect(): JsonResponse
    {
        $access = $this->authorizeWorkspace('calendar.edit');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        try {
            $disconnected = $this->service($access)->disconnect((int)$access['user_id']);
            return $this->success('OUTLOOK_CALENDAR_DISCONNECTED', $disconnected ? 'Local tokens cleared. Imported CRM events are retained.' : 'No Outlook connection was found.', ['connected' => false]);
        } catch (Throwable) {
            return $this->error('OUTLOOK_CALENDAR_DISCONNECT_FAILED', 'The Outlook connection could not be disconnected.', 503);
        }
    }

    /** @return array<string,mixed>|JsonResponse */
    private function bodyInput(): array|JsonResponse
    {
        $request = $this->request();
        if ($request->bodyTooLarge || strlen($request->rawBody) > 16384) {
            return $this->error('PAYLOAD_TOO_LARGE', 'Request exceeds the 16 KB limit.', 413);
        }
        $contentType = strtolower(trim(explode(';', (string)($request->header('Content-Type') ?? ''))[0]));
        if ($contentType !== 'application/json') {
            return $this->error('UNSUPPORTED_MEDIA_TYPE', 'Requests must use application/json.', 415);
        }
        $data = $request->jsonBody;
        // json_decode('{}', true) yields [], indistinguishable from an empty
        // JSON list. Empty bodies are valid for OAuth start and sync actions.
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            return $this->error('INVALID_REQUEST_BODY', 'Request body must be a JSON object.', 400);
        }
        return $data;
    }
}
