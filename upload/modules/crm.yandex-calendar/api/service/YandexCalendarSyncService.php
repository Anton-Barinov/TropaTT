<?php
declare(strict_types=1);

namespace Module\Crm\YandexCalendar\Service;

use Module\Crm\YandexCalendar\Repository\YandexCalendarRepository;
use PDO;
use RuntimeException;

final class YandexCalendarSyncService
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly YandexCalendarRepository $repository, private readonly YandexCalDavClient $client, private readonly PDO $pdo, private readonly array $config = []) {}

    public function connectUser(int $userId, string $email, string $password): array
    {
        $email = trim($email);
        $password = trim($password);
        // Validate before persisting the credential. A failed connection must
        // never leave a secret in the database.
        $calendars = $this->client->discoverCalendars($email, $password);
        $encrypted = EncryptionService::encrypt($password);
        $existing = $this->repository->connectionForUser($userId);
        if ($existing) {
            $this->repository->updateConnection((int)$existing['id'], ['account_email' => $email, 'caldav_username' => $email, 'credential_encrypted' => $encrypted, 'status' => 'active', 'last_error' => null]);
            $connection = $this->repository->connectionById((int)$existing['id']) ?: $existing;
        } else {
            $connection = $this->repository->createConnection($userId, $email, $email, $encrypted);
        }
        $this->storeCalendars($connection, $calendars);
        return $connection;
    }

    public function test(int $connectionId, int $userId): array
    {
        $lock = $this->lock($connectionId);
        try {
            $connection = $this->ownedConnection($connectionId, $userId);
            $calendars = $this->discover($connection);
            $this->storeCalendars($connection, $calendars);
            $this->repository->updateConnection($connectionId, ['status' => 'active', 'last_error' => null]);
            return ['account_email' => $connection['account_email'], 'calendars_count' => count($calendars)];
        } catch (\Throwable $e) {
            if ($this->isAuthError($e)) $this->repository->updateConnection($connectionId, ['status' => 'reauthorization_required', 'last_error' => 'Yandex authorization failed']);
            throw $e;
        } finally {
            $this->unlock($lock);
        }
    }

    public function sync(int $connectionId, int $userId): array
    {
        $lock = $this->lock($connectionId);
        try { return $this->syncLocked($connectionId, $userId); }
        finally { $this->unlock($lock); }
    }

    private function syncLocked(int $connectionId, int $userId): array
    {
        $connection = $this->ownedConnection($connectionId, $userId);
        try {
            $calendars = $this->discover($connection);
            $this->storeCalendars($connection, $calendars);
            $sources = $this->repository->sources($connectionId);
            $result = ['calendars' => count($sources), 'pulled' => 0, 'pushed' => 0, 'deleted' => 0, 'warnings' => []];
            $pushDone = false;
            foreach ($sources as $source) {
                try {
                    if (in_array((string)$source['direction'], ['yandex_to_crm', 'bidirectional'], true)) {
                        $pulled = $this->pullSource($connection, $source);
                        $result['pulled'] += $pulled['pulled'];
                        $result['deleted'] += $pulled['deleted'];
                    }
                    if (!$pushDone && in_array((string)$source['direction'], ['crm_to_yandex', 'bidirectional'], true)) {
                        $result['pushed'] += $this->pushLocal($connection, $source);
                        $pushDone = true;
                    }
                } catch (\Throwable $e) {
                    if ($this->isAuthError($e)) throw $e;
                    $this->repository->updateSource((int)$source['id'], ['last_error' => 'Synchronization failed']);
                    $result['warnings'][] = (string)($source['display_name'] ?? $source['id']);
                }
            }
            $values = ['last_sync_at' => gmdate('Y-m-d H:i:s'), 'status' => $result['warnings'] === [] ? 'active' : 'sync_warning', 'last_error' => $result['warnings'] === [] ? null : 'One or more calendars could not be synchronized'];
            $this->repository->updateConnection($connectionId, $values);
            return $result;
        } catch (\Throwable $e) {
            if ($this->isAuthError($e)) $this->repository->updateConnection($connectionId, ['status' => 'reauthorization_required', 'last_error' => 'Yandex authorization failed']);
            throw $e;
        }
    }

    /** @return array{pulled:int,deleted:int} */
    private function pullSource(array $connection, array $source): array
    {
        $from = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-' . $this->settingInt('sync_days_past', 'YANDEX_SYNC_DAYS_PAST', 90, 1, 3650) . ' days')->format('Y-m-d H:i:s');
        $to = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+' . $this->settingInt('sync_days_future', 'YANDEX_SYNC_DAYS_FUTURE', 365, 1, 3650) . ' days')->format('Y-m-d H:i:s');
        [$username, $password] = $this->credentials($connection);
        $remoteRows = $this->client->events($username, $password, (string)$source['calendar_href'], $from, $to);
        $maxEvents = $this->settingInt('max_events_per_sync', 'YANDEX_MAX_EVENTS_PER_SYNC', 5000, 1, 100000);
        $seen = [];
        $processed = 0;
        $pulled = 0;
        $deleted = 0;
        foreach ($remoteRows as $row) {
            $parsed = IcsParser::events((string)$row['ics']);
            // A recurring resource is one ICS object. Import its master only;
            // writing an individual occurrence back to the same href would
            // otherwise erase the resource's exceptions and RRULE.
            $events = array_values(array_filter($parsed, static fn(array $event): bool => ($event['recurrence_id'] ?? null) === null));
            foreach ($events as $event) {
                if (++$processed > $maxEvents) throw new RuntimeException('YANDEX_EVENT_LIMIT_REACHED');
                $key = $this->eventKey($event);
                $seen[$key] = true;
                $mapping = $this->repository->eventByKey((int)$source['id'], (string)$event['uid'], $event['recurrence_id'] !== null ? (string)$event['recurrence_id'] : null);
                if ((string)($event['status'] ?? 'confirmed') === 'cancelled') {
                    if ($mapping) { $this->deleteMappedLocal($mapping, (int)$connection['user_id']); $this->repository->deleteEventMapping((int)$mapping['id']); $deleted++; }
                    continue;
                }
                $this->upsertRemote((int)$connection['user_id'], (int)$source['id'], $row, $event, $mapping);
                $pulled++;
            }
        }
        // The query is bounded. Remove only mappings whose stored occurrence
        // falls inside this same window; never treat an omitted old event as a
        // deletion.
        foreach ($this->repository->mappings((int)$source['id']) as $mapping) {
            if (!$this->inWindow((string)($mapping['event_start'] ?? ''), $from, $to)) continue;
            $mappingKey = $this->eventKeyFromMapping($mapping);
            if (!isset($seen[$mappingKey])) {
                if (!empty($mapping['crm_event_public_id'])) $this->repository->deleteCalendarEvent((string)$mapping['crm_event_public_id'], (int)$connection['user_id']);
                $this->repository->deleteEventMapping((int)$mapping['id']);
                $deleted++;
            }
        }
        $this->repository->updateSource((int)$source['id'], ['last_sync_at' => gmdate('Y-m-d H:i:s'), 'last_error' => null]);
        return ['pulled' => $pulled, 'deleted' => $deleted];
    }

    private function upsertRemote(int $ownerId, int $sourceId, array $row, array $event, ?array $mapping): void
    {
        $data = ['title' => mb_substr((string)$event['summary'], 0, 255), 'description' => strip_tags((string)$event['description']), 'starts_at' => $event['starts_at'], 'ends_at' => $event['ends_at'], 'external_id' => (string)$event['uid']];
        $values = ['event_href' => $row['href'], 'etag' => $row['etag'] ?? null, 'recurrence_rule' => $event['recurrence_rule'] ?? null, 'event_start' => $event['starts_at'], 'event_end' => $event['ends_at'], 'is_all_day' => $event['is_all_day'], 'all_day_start' => $event['all_day_start'], 'all_day_end' => $event['all_day_end'], 'status' => 'active', 'last_synced_at' => gmdate('Y-m-d H:i:s')];
        $started = !$this->pdo->inTransaction();
        if ($started) $this->pdo->beginTransaction();
        try {
            if (!$mapping) {
                $crmId = $this->repository->createCalendarEvent($ownerId, $data);
                $values['crm_event_public_id'] = $crmId;
                $values['external_uid'] = $event['uid'];
                $values['recurrence_id'] = $event['recurrence_id'];
                $this->repository->insertEvent($sourceId, $values);
                if ($started) $this->pdo->commit();
                return;
            }
            $crmId = (string)($mapping['crm_event_public_id'] ?? '');
        if ($crmId === '' || $this->repository->calendarEvent($crmId) === null) $crmId = $this->repository->createCalendarEvent($ownerId, $data);
        else $this->repository->updateCalendarEvent($crmId, $data, $ownerId);
            $values['crm_event_public_id'] = $crmId;
            $this->repository->updateEventMapping((int)$mapping['id'], $values);
            if ($started) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($started && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function pushLocal(array $connection, array $source): int
    {
        [$username, $password] = $this->credentials($connection);
        $count = 0;
        foreach ($this->repository->mappings((int)$source['id']) as $mapping) {
            $crmId = trim((string)($mapping['crm_event_public_id'] ?? ''));
            if ($crmId === '') continue;
            $local = $this->repository->calendarEvent($crmId);
            if (!$local) {
                // Do not DELETE a recurring ICS resource from a single CRM
                // occurrence; that would remove the whole series and its
                // exceptions. The next pull will reconcile it safely.
                if (!empty($mapping['recurrence_rule'])) {
                    $this->repository->updateEventMapping((int)$mapping['id'], ['status' => 'remote_write_skipped', 'last_error' => 'Recurring series is read-only from CRM']);
                    continue;
                }
                if (!empty($mapping['event_href'])) $this->client->delete($username, $password, (string)$mapping['event_href'], (string)($mapping['etag'] ?? ''));
                $this->repository->deleteEventMapping((int)$mapping['id']);
                continue;
            }
            if (!empty($mapping['recurrence_rule'])) {
                // Preserve RRULE and exceptions until a full-resource editor is
                // available; never replace the remote series with one VEVENT.
                continue;
            }
            if (!empty($mapping['last_synced_at']) && strtotime((string)$local['updated_at']) <= strtotime((string)$mapping['last_synced_at'])) continue;
            $uid = (string)$mapping['external_uid'];
            $ics = IcsParser::toIcs($local, $uid, $mapping['recurrence_rule'] ?? null);
            $remote = $this->client->put($username, $password, (string)$source['calendar_href'], $uid, $ics, $mapping['event_href'] ?? null, $mapping['etag'] ?? null);
            $this->repository->updateEventMapping((int)$mapping['id'], ['event_href' => $remote['href'], 'etag' => $remote['etag'], 'last_synced_at' => gmdate('Y-m-d H:i:s')]);
            $count++;
        }
        foreach ($this->repository->localEventsForUser((int)$connection['user_id'], (string)($source['last_sync_at'] ?? '')) as $local) {
            $uid = 'tropatt-' . (string)$local['public_id'] . '@tropatt';
            $ics = IcsParser::toIcs($local, $uid);
            $remote = $this->client->put($username, $password, (string)$source['calendar_href'], $uid, $ics);
            $mapping = ['external_uid' => $uid, 'recurrence_id' => null, 'event_href' => $remote['href'], 'etag' => $remote['etag'], 'event_start' => $local['starts_at'], 'event_end' => $local['ends_at'], 'is_all_day' => 0, 'crm_event_public_id' => $local['public_id']];
            $this->repository->insertEvent((int)$source['id'], $mapping);
            $count++;
        }
        $this->repository->updateSource((int)$source['id'], ['last_sync_at' => gmdate('Y-m-d H:i:s')]);
        return $count;
    }

    public function updateDirection(int $sourceId, int $userId, string $direction, bool $enabled): bool
    {
        $source = $this->repository->sourceById($sourceId);
        if (!$source) return false;
        $connection = $this->repository->connectionById((int)$source['connection_id']);
        if (!$connection || (int)$connection['user_id'] !== $userId || !in_array($direction, ['yandex_to_crm','crm_to_yandex','bidirectional'], true)) return false;
        $this->repository->updateSource($sourceId, ['direction' => $direction, 'is_enabled' => $enabled ? 1 : 0]);
        return true;
    }

    public function disconnect(int $connectionId, int $userId): void
    {
        $connection = $this->ownedConnection($connectionId, $userId);
        $this->repository->deleteConnection((int)$connection['id'], $userId);
    }

    private function discover(array $connection): array
    {
        [$username, $password] = $this->credentials($connection);
        return $this->client->discoverCalendars($username, $password);
    }

    private function storeCalendars(array $connection, array $calendars): void
    {
        $hrefs = [];
        foreach ($calendars as $calendar) { $hrefs[] = (string)$calendar['href']; $this->repository->upsertSource((int)$connection['id'], $calendar); }
        $this->repository->disableMissingSources((int)$connection['id'], $hrefs);
    }

    /** @return array{0:string,1:string} */
    private function credentials(array $connection): array
    {
        $secret = EncryptionService::decrypt((string)($connection['credential_encrypted'] ?? ''));
        if ($secret === null || $secret === '') throw new RuntimeException('YANDEX_CREDENTIALS_UNAVAILABLE');
        return [(string)$connection['caldav_username'], $secret];
    }

    private function ownedConnection(int $id, int $userId): array
    {
        $connection = $this->repository->connectionById($id);
        if (!$connection || (int)$connection['user_id'] !== $userId) throw new RuntimeException('YANDEX_CONNECTION_NOT_FOUND');
        return $connection;
    }

    private function eventKey(array $event): string { return (string)$event['uid'] . "\0" . (string)($event['recurrence_id'] ?? ''); }
    private function eventKeyFromMapping(array $mapping): string { return (string)$mapping['external_uid'] . "\0" . (string)($mapping['recurrence_id'] ?? ''); }
    private function inWindow(string $date, string $from, string $to): bool { $ts=strtotime($date); return $ts!==false && $ts>=strtotime($from) && $ts<=strtotime($to); }
    private function deleteMappedLocal(array $mapping, int $ownerId): void { if (!empty($mapping['crm_event_public_id'])) $this->repository->deleteCalendarEvent((string)$mapping['crm_event_public_id'], $ownerId); }
    private function isAuthError(\Throwable $e): bool { return in_array($e->getMessage(), ['YANDEX_AUTH_FAILED','YANDEX_CREDENTIALS_UNAVAILABLE'], true); }

    private function settingInt(string $key, string $environmentKey, int $default, int $min, int $max): int
    {
        $environmentValue = getenv($environmentKey);
        $value = $environmentValue !== false && $environmentValue !== '' ? $environmentValue : ($this->config[$key] ?? $default);
        return is_numeric($value) ? max($min, min($max, (int)$value)) : $default;
    }

    /** @return resource */
    private function lock(int $connectionId)
    {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tropatt-yandex-calendar-' . substr(hash('sha256', __DIR__), 0, 16) . '-' . $connectionId . '.lock';
        $handle = fopen($path, 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) { if (is_resource($handle)) fclose($handle); throw new RuntimeException('YANDEX_SYNC_IN_PROGRESS'); }
        return $handle;
    }
    /** @param resource $handle */
    private function unlock($handle): void { flock($handle, LOCK_UN); fclose($handle); }
}
