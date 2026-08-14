<?php
declare(strict_types=1);

namespace Module\Crm\YandexCalendar\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\YandexCalendar\Repository\YandexCalendarRepository;
use Module\Crm\YandexCalendar\Service\YandexCalendarSyncService;
use RuntimeException;

final class YandexCalendarController
{
    public function __construct(private readonly Container $container) {}

    public function connect(): JsonResponse
    {
        $actorId = $this->actorId();
        $input = $this->container->get('request')->allInput();
        $email = trim((string)($input['email'] ?? ''));
        $password = trim((string)($input['app_password'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 512) return JsonResponse::error('VALIDATION_ERROR', 'Укажите email Яндекса и пароль приложения календаря', 422);
        try {
            $service = $this->service();
            $connection = $service->connectUser($actorId, $email, $password);
            $connection = $this->repository()->connectionForUser($actorId) ?: $connection;
            return JsonResponse::success('YANDEX_CONNECTED', 'Яндекс.Календарь подключён', ['connection' => $this->publicConnection($connection)]);
        } catch (RuntimeException $e) {
            $code = match ($e->getMessage()) {
                'YANDEX_AUTH_FAILED' => 'YANDEX_AUTH_FAILED',
                'YANDEX_CALENDARS_NOT_FOUND' => 'YANDEX_CALENDARS_NOT_FOUND',
                'APP_SECRET_REQUIRED' => 'APP_SECRET_REQUIRED',
                default => 'YANDEX_CONNECTION_FAILED',
            };
            return JsonResponse::error($code, 'Не удалось подключить Яндекс.Календарь. Проверьте email и пароль приложения.', 422);
        }
    }

    public function connections(): JsonResponse
    {
        $items = [];
        foreach ($this->repository()->listConnectionsForUser($this->actorId()) as $connection) {
            $connection['calendars'] = array_map([$this, 'publicSource'], $this->repository()->allSources((int)$connection['id']));
            $items[] = $this->publicConnection($connection);
        }
        return JsonResponse::success('YANDEX_CONNECTIONS', 'OK', ['connections' => $items]);
    }

    public function test(array $params): JsonResponse
    {
        $connection = $this->ownedConnection((string)($params['public_id'] ?? ''));
        if (!$connection) return $this->notFound();
        try { $result = $this->service()->test((int)$connection['id'], $this->actorId()); return JsonResponse::success('YANDEX_CONNECTION_TEST_OK', 'Подключение работает', ['result' => $result]); }
        catch (RuntimeException $e) { return JsonResponse::error($this->isAuthError($e) ? 'YANDEX_REAUTH_REQUIRED' : 'YANDEX_CONNECTION_TEST_FAILED', 'Проверка подключения не пройдена', 422); }
    }

    public function sync(array $params): JsonResponse
    {
        $connection = $this->ownedConnection((string)($params['public_id'] ?? ''));
        if (!$connection) return $this->notFound();
        try {
            $result = $this->service()->sync((int)$connection['id'], $this->actorId());
            $partial = ($result['warnings'] ?? []) !== [];
            return JsonResponse::success($partial ? 'YANDEX_SYNC_WARNING' : 'YANDEX_SYNC_COMPLETE', $partial ? 'Синхронизация завершена с предупреждениями' : 'Синхронизация завершена', ['result' => $result], $partial ? 207 : 200);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'YANDEX_SYNC_IN_PROGRESS') return JsonResponse::error('YANDEX_SYNC_IN_PROGRESS', 'Синхронизация уже выполняется', 409);
            return JsonResponse::error($this->isAuthError($e) ? 'YANDEX_REAUTH_REQUIRED' : 'YANDEX_SYNC_FAILED', 'Синхронизация не выполнена', 422);
        }
    }

    public function updateCalendar(array $params): JsonResponse
    {
        $input = $this->container->get('request')->allInput();
        $direction = (string)($input['direction'] ?? 'yandex_to_crm');
        $source = $this->repository()->sourceForUser((string)($params['public_id'] ?? ''), $this->actorId());
        if (!$source || !$this->service()->updateDirection((int)$source['id'], $this->actorId(), $direction, array_key_exists('is_enabled', $input) ? (bool)$input['is_enabled'] : (bool)$source['is_enabled'])) return JsonResponse::error('YANDEX_CALENDAR_NOT_FOUND', 'Календарь не найден или направление недопустимо', 404);
        return JsonResponse::success('YANDEX_CALENDAR_UPDATED', 'Настройки календаря сохранены');
    }

    public function disconnect(array $params): JsonResponse
    {
        $connection = $this->ownedConnection((string)($params['public_id'] ?? ''));
        if (!$connection) return $this->notFound();
        try { $this->service()->disconnect((int)$connection['id'], $this->actorId()); return JsonResponse::success('YANDEX_DISCONNECTED', 'Яндекс.Календарь отключён'); }
        catch (\Throwable) { return JsonResponse::error('YANDEX_DISCONNECT_FAILED', 'Не удалось отключить календарь', 409); }
    }

    private function repository(): YandexCalendarRepository { return $this->container->get('module.yandex_calendar.repository'); }
    private function service(): YandexCalendarSyncService { return $this->container->get('module.yandex_calendar.sync'); }
    private function actorId(): int { $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : []; return (int)($auth['user']['id'] ?? 0); }
    private function ownedConnection(string $publicId): ?array { foreach ($this->repository()->listConnectionsForUser($this->actorId()) as $item) if ((string)$item['public_id'] === $publicId) return $this->repository()->connectionById((int)$item['id']); return null; }
    private function isAuthError(RuntimeException $e): bool { return in_array($e->getMessage(), ['YANDEX_AUTH_FAILED','YANDEX_CREDENTIALS_UNAVAILABLE'], true); }
    private function publicSource(array $source): array { return ['public_id'=>(string)$source['public_id'],'display_name'=>$source['display_name']??null,'timezone'=>$source['timezone']??null,'direction'=>(string)$source['direction'],'is_enabled'=>(bool)$source['is_enabled'],'is_primary'=>(bool)$source['is_primary'],'last_sync_at'=>$source['last_sync_at']??null,'last_error'=>$source['last_error']??null]; }
    private function publicConnection(array $connection): array { return ['public_id'=>(string)($connection['public_id']??''),'account_email'=>$connection['account_email']??null,'auth_mode'=>'app_password','status'=>(string)($connection['status']??'active'),'last_error'=>$connection['last_error']??null,'last_sync_at'=>$connection['last_sync_at']??null,'created_at'=>$connection['created_at']??null,'updated_at'=>$connection['updated_at']??null,'calendars'=>$connection['calendars']??[]]; }
    private function notFound(): JsonResponse { return JsonResponse::error('YANDEX_CONNECTION_NOT_FOUND', 'Подключение не найдено', 404); }
}
