<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Service\ClientService;
use Module\Crm\HubSpotMigration\Service\HubSpotMigrationService;
use Throwable;

final class HubSpotMigrationApiController extends BaseController
{
    private function authorizedWorkspace(string $permission): array|JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !is_array($auth['user'] ?? null)) return $this->error('UNAUTHORIZED', 'Требуется авторизация.', 401);
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) return $contextError;
        $actor = $this->organizationScopedActor((array)$auth['user']);
        $workspaceId = (int)($actor['organization_id'] ?? 0);
        if ($workspaceId < 1) return $this->error('WORKSPACE_REQUIRED', 'Выберите рабочее пространство.', 409);
        if (empty($actor['is_root']) && (!$this->container->has('service.authz') || !$this->container->get('service.authz')->hasPermissions($actor, [$permission]))) {
            return $this->error('FORBIDDEN', 'Недостаточно прав для миграции контактов.', 403);
        }
        return [$actor, $workspaceId];
    }

    private function migrationService(array $actor, int $workspaceId): HubSpotMigrationService
    {
        /** @var ClientService $clients */
        $clients = $this->container->get('service.client');
        return new HubSpotMigrationService($this->container->get('db.pdo'), $workspaceId, $clients, $actor);
    }

    public function getStatus(): JsonResponse
    {
        $access = $this->authorizedWorkspace('client.view');
        if ($access instanceof JsonResponse) return $access;
        [$actor, $workspaceId] = $access;
        try { return $this->success('HUBSPOT_MIGRATION_STATUS', 'Состояние миграции загружено.', $this->migrationService($actor, $workspaceId)->status()); }
        catch (Throwable) { return $this->error('HUBSPOT_MIGRATION_UNAVAILABLE', 'Не удалось получить состояние модуля.', 503); }
    }

    public function createSession(): JsonResponse
    {
        $access = $this->authorizedWorkspace('client.manage');
        if ($access instanceof JsonResponse) return $access;
        [$actor, $workspaceId] = $access;
        try { return $this->success('HUBSPOT_MIGRATION_SESSION_CREATED', 'Сессия импорта создана.', $this->migrationService($actor, $workspaceId)->createSession(), 201); }
        catch (Throwable) { return $this->error('HUBSPOT_MIGRATION_SESSION_FAILED', 'Не удалось создать сессию миграции.', 503); }
    }

    public function stageContacts(): JsonResponse
    {
        $access = $this->authorizedWorkspace('client.manage');
        if ($access instanceof JsonResponse) return $access;
        [$actor, $workspaceId] = $access;
        $input = $this->bodyInput();
        if ($input instanceof JsonResponse) return $input;
        if (!is_string($input['session_id'] ?? null) || !is_array($input['records'] ?? null) || !is_array($input['mapping'] ?? [])) return $this->error('HUBSPOT_IMPORT_INVALID', 'Передайте session_id, records и необязательную mapping.', 422);
        try {
            $result = $this->migrationService($actor, $workspaceId)->stageContacts($input['session_id'], $input['records'], $input['mapping'] ?? []);
            return $this->success('HUBSPOT_CONTACTS_STAGED', 'Предпросмотр контактов подготовлен; CRM не изменена.', $result);
        } catch (Throwable $e) { return $this->mapError($e); }
    }

    public function getSession(array $params): JsonResponse
    {
        $access = $this->authorizedWorkspace('client.view');
        if ($access instanceof JsonResponse) return $access;
        [$actor, $workspaceId] = $access;
        try {
            $query = $this->request()->query;
            $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
            $perPage = filter_var($query['per_page'] ?? 25, FILTER_VALIDATE_INT);
            if ($page === false || $perPage === false) return $this->error('HUBSPOT_PREVIEW_PAGE_INVALID', 'Параметры страницы должны быть целыми числами.', 422);
            return $this->success('HUBSPOT_MIGRATION_PREVIEW', 'Отчёт сессии загружен.', $this->migrationService($actor, $workspaceId)->preview((string)($params['session_id'] ?? ''), $page, $perPage));
        }
        catch (Throwable $e) { return $this->mapError($e); }
    }

    public function importBatch(array $params): JsonResponse
    {
        $access = $this->authorizedWorkspace('client.manage');
        if ($access instanceof JsonResponse) return $access;
        [$actor, $workspaceId] = $access;
        $input = $this->bodyInput();
        if ($input instanceof JsonResponse) return $input;
        $limit = filter_var($input['limit'] ?? 25, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 25) return $this->error('HUBSPOT_BATCH_INVALID', 'Размер пакета должен быть от 1 до 25.', 422);
        try {
            $result = $this->migrationService($actor, $workspaceId)->importBatch((string)($params['session_id'] ?? ''), $limit);
            $this->invalidateCache('client');
            return $this->success('HUBSPOT_BATCH_IMPORTED', 'Пакет импорта обработан.', $result);
        } catch (Throwable $e) { return $this->mapError($e); }
    }

    public function retryFailedRecords(array $params): JsonResponse
    {
        $access = $this->authorizedWorkspace('client.manage');
        if ($access instanceof JsonResponse) return $access;
        [$actor, $workspaceId] = $access;
        try {
            $result = $this->migrationService($actor, $workspaceId)->retryFailedRecords((string)($params['session_id'] ?? ''), 25);
            $this->invalidateCache('client');
            return $this->success('HUBSPOT_FAILED_RECORDS_RETRIED', 'Повторная обработка ошибок завершена.', $result);
        } catch (Throwable $e) { return $this->mapError($e); }
    }

    public function rollback(array $params): JsonResponse
    {
        $access = $this->authorizedWorkspace('client.manage');
        if ($access instanceof JsonResponse) return $access;
        [$actor, $workspaceId] = $access;
        try {
            $result = $this->migrationService($actor, $workspaceId)->rollback((string)($params['session_id'] ?? ''));
            $this->invalidateCache('client');
            return $this->success('HUBSPOT_MIGRATION_ROLLED_BACK', 'Безопасный откат завершён; изменённые записи оставлены в CRM.', $result);
        } catch (Throwable $e) { return $this->mapError($e); }
    }

    public function discardPending(array $params): JsonResponse
    {
        $access = $this->authorizedWorkspace('client.manage');
        if ($access instanceof JsonResponse) return $access;
        [$actor, $workspaceId] = $access;
        try {
            $result = $this->migrationService($actor, $workspaceId)->discardPending((string)($params['session_id'] ?? ''));
            return $this->success('HUBSPOT_STAGING_DISCARDED', 'Неперенесённые данные очищены.', $result);
        } catch (Throwable $e) { return $this->mapError($e); }
    }

    /** @return array<string,mixed>|JsonResponse */
    private function bodyInput(): array|JsonResponse
    {
        $request = $this->request();
        if ($request->bodyTooLarge || strlen($request->rawBody) > 600000) return $this->error('HUBSPOT_PAYLOAD_TOO_LARGE', 'JSON-пакет должен быть не больше 600 КБ.', 413);
        $contentType = strtolower(trim(explode(';', (string)($request->header('Content-Type') ?? ''))[0]));
        if ($contentType !== 'application/json') return $this->error('UNSUPPORTED_MEDIA_TYPE', 'Передавайте данные в формате application/json.', 415);
        try {
            $data = json_decode($request->rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->error('INVALID_REQUEST_BODY', 'Передайте корректный JSON-объект.', 400);
        }
        if (!is_array($data) || array_is_list($data)) return $this->error('INVALID_REQUEST_BODY', 'Тело запроса должно быть JSON-объектом.', 400);
        return $data;
    }

    private function mapError(Throwable $e): JsonResponse
    {
        if ($e instanceof \JsonException) return $this->error('HUBSPOT_IMPORT_INVALID_JSON', 'В данных есть некорректный JSON или кодировка.', 422);
        $code = $e->getMessage();
        $known = ['SESSION_NOT_FOUND', 'INVALID_SESSION_ID', 'SESSION_NOT_EDITABLE', 'SESSION_NOT_READY', 'SESSION_RECORD_LIMIT_EXCEEDED', 'NO_RETRYABLE_RECORDS', 'BATCH_MUST_CONTAIN_1_TO_100_CONTACTS', 'PAYLOAD_LIMIT_EXCEEDED', 'INVALID_HUBSPOT_CONTACT', 'INVALID_HUBSPOT_CONTACT_ID', 'CONTACT_NAME_REQUIRED_OR_TOO_LONG', 'INVALID_EMAIL', 'PHONE_TOO_LONG', 'NOTES_TOO_LONG', 'DUPLICATE_HUBSPOT_ID_IN_SESSION', 'INVALID_FIELD_MAPPING', 'ID_CANNOT_BE_MAPPED_AS_CONTACT_DATA', 'NESTED_PROPERTIES_NOT_SUPPORTED'];
        $status = in_array($code, ['SESSION_NOT_FOUND'], true) ? 404 : (in_array($code, $known, true) ? 422 : 503);
        return $this->error(in_array($code, $known, true) ? $code : 'HUBSPOT_MIGRATION_FAILED', in_array($code, $known, true) ? 'Проверьте данные и состояние сессии миграции.' : 'Операция миграции не выполнена.', $status);
    }
}
