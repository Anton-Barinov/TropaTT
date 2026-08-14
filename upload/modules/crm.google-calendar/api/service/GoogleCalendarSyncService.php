<?php
declare(strict_types=1);

namespace Module\Crm\GoogleCalendar\Service;

use Module\Crm\GoogleCalendar\Repository\GoogleCalendarRepository;
use PDO;
use RuntimeException;

final class GoogleCalendarSyncService
{
    public function __construct(
        private readonly GoogleCalendarRepository $repository,
        private readonly GoogleCalendarClient $client,
        private readonly PDO $pdo,
    ) {
    }

    /** @param array<string,mixed> $tokens */
    public function connectUser(int $userId, array $tokens): array
    {
        $refresh = trim((string)($tokens['refresh_token'] ?? ''));
        if ($refresh === '') {
            throw new RuntimeException('GOOGLE_REFRESH_TOKEN_MISSING');
        }

        $values = [
            'refresh_token_encrypted' => EncryptionService::encrypt($refresh),
            'access_token_encrypted' => !empty($tokens['access_token']) ? EncryptionService::encrypt((string)$tokens['access_token']) : null,
            'access_token_expires_at' => !empty($tokens['expires_in']) ? gmdate('Y-m-d H:i:s', time() + (int)$tokens['expires_in']) : null,
            'status' => 'active',
            'last_error' => null,
        ];
        $existing = $this->repository->connectionForUser($userId);
        if ($existing !== null) {
            $this->repository->updateConnection((int)$existing['id'], $values);
            return $this->repository->connectionForUser($userId) ?: $existing;
        }

        return $this->repository->createConnection($userId, $values);
    }

    /** @return array<string,mixed> */
    public function test(int $connectionId, int $userId): array
    {
        $connection = $this->ownedConnection($connectionId, $userId);
        $access = $this->accessToken($connection);
        $calendars = $this->calendarsWithRefresh($connection, $access);
        foreach ($calendars as $calendar) {
            $this->repository->upsertSource($connectionId, $calendar);
        }

        return ['email' => $connection['google_account_email'] ?? null, 'calendars_count' => count($calendars)];
    }

    /** @return array<string,mixed> */
    public function sync(int $connectionId, int $userId): array
    {
        $connection = $this->ownedConnection($connectionId, $userId);
        $access = $this->accessToken($connection);
        $calendars = $this->calendarsWithRefresh($connection, $access);
        foreach ($calendars as $calendar) {
            $this->repository->upsertSource($connectionId, $calendar);
        }

        $sources = $this->repository->sources($connectionId);
        $result = ['calendars' => count($sources), 'pulled' => 0, 'pushed' => 0, 'deleted' => 0, 'warnings' => []];
        $pushDone = false;
        foreach ($sources as $source) {
            try {
                if (in_array((string)$source['direction'], ['google_to_crm', 'bidirectional'], true)) {
                    $pulled = $this->pullSource($connection, $source, $access);
                    $result['pulled'] += (int)$pulled['pulled'];
                    $result['deleted'] += (int)$pulled['deleted'];
                }
                // A new CRM event has no calendar_id. Pick one writable source
                // only, otherwise multiple selected calendars would duplicate it.
                if (!$pushDone && in_array((string)$source['direction'], ['crm_to_google', 'bidirectional'], true)) {
                    $result['pushed'] += $this->pushLocal($connection, $source, $access);
                    $pushDone = true;
                }
            } catch (\Throwable) {
                $this->repository->updateSource((int)$source['id'], ['last_error' => 'Synchronization failed']);
                $result['warnings'][] = (string)($source['calendar_id'] ?? $source['id']);
            }
        }

        $connectionValues = ['last_sync_at' => gmdate('Y-m-d H:i:s')];
        if ($result['warnings'] !== []) {
            $connectionValues['status'] = 'sync_warning';
            $connectionValues['last_error'] = 'One or more calendars could not be synchronized';
        } else {
            $connectionValues['status'] = 'active';
            $connectionValues['last_error'] = null;
        }
        $this->repository->updateConnection((int)$connection['id'], $connectionValues);
        return $result;
    }

    public function updateDirection(int $sourceId, int $userId, string $direction, bool $enabled): bool
    {
        $source = $this->repository->sourceById($sourceId);
        if ($source === null) return false;
        $connection = $this->repository->connectionById((int)$source['connection_id']);
        if ($connection === null || (int)$connection['user_id'] !== $userId) return false;
        if (!in_array($direction, ['google_to_crm', 'crm_to_google', 'bidirectional'], true)) return false;
        $this->repository->updateSource($sourceId, ['direction' => $direction, 'is_enabled' => $enabled ? 1 : 0]);
        return true;
    }

    public function disconnect(int $connectionId, int $userId): void
    {
        $connection = $this->ownedConnection($connectionId, $userId);
        $refresh = EncryptionService::decrypt((string)$connection['refresh_token_encrypted']);
        if ($refresh !== null) {
            try { $this->client->revoke($refresh); } catch (\Throwable) { /* local cleanup still must complete */ }
        }
        foreach ($this->repository->allSources($connectionId) as $source) {
            foreach ($this->repository->mappings((int)$source['id']) as $mapping) {
                if (!empty($mapping['crm_event_public_id'])) {
                    $this->repository->deleteCalendarEvent((string)$mapping['crm_event_public_id'], $userId);
                }
            }
        }
        $this->repository->deleteConnection($connectionId);
    }

    /** @return array{pulled:int,deleted:int} */
    private function pullSource(array $connection, array $source, string &$access, int $fullSyncRetry = 0, int $accessRetry = 0): array
    {
        $token = $source['sync_token'] ?? null;
        $full = $token === null || $token === '';
        $pageToken = null;
        $seen = [];
        $pulled = 0;
        $deleted = 0;
        try {
            do {
                $query = ['maxResults' => 2500, 'showDeleted' => 'true', 'singleEvents' => 'true'];
                if ($token) $query['syncToken'] = $token;
                if ($pageToken) $query['pageToken'] = $pageToken;
                $page = $this->client->eventsPage($access, (string)$source['calendar_id'], $query);
                foreach ((array)($page['items'] ?? []) as $event) {
                    if (!is_array($event) || empty($event['id'])) continue;
                    $googleId = (string)$event['id']; $seen[$googleId] = true;
                    if ((string)($event['status'] ?? 'confirmed') === 'cancelled') {
                        $mapping = $this->repository->event((int)$source['id'], $googleId);
                        if ($mapping && !empty($mapping['crm_event_public_id'])) {
                            $this->repository->deleteCalendarEvent((string)$mapping['crm_event_public_id'], (int)$connection['user_id']);
                            $deleted++;
                        }
                        if ($mapping) $this->repository->deleteEventMapping((int)$mapping['id']);
                        continue;
                    }
                    $this->upsertRemote((int)$connection['user_id'], (int)$source['id'], $event);
                    $pulled++;
                }
                $pageToken = isset($page['nextPageToken']) ? (string)$page['nextPageToken'] : null;
                if (!$pageToken && isset($page['nextSyncToken'])) $token = (string)$page['nextSyncToken'];
            } while ($pageToken !== null && $pageToken !== '');
        } catch (RuntimeException $e) {
            if ((int)$e->getCode() === 410 || $e->getMessage() === 'GOOGLE_SYNC_TOKEN_EXPIRED') {
                if ($fullSyncRetry >= 1) throw $e;
                $this->repository->updateSource((int)$source['id'], ['sync_token' => null]);
                return $this->pullSource($connection, array_merge($source, ['sync_token' => null]), $access, $fullSyncRetry + 1, $accessRetry);
            }
            if ((int)$e->getCode() === 401 || $e->getMessage() === 'GOOGLE_ACCESS_TOKEN_EXPIRED') {
                if ($accessRetry >= 1) throw $e;
                $access = $this->forceRefreshAccessToken($connection);
                return $this->pullSource($connection, $source, $access, $fullSyncRetry, $accessRetry + 1);
            }
            throw $e;
        }

        if ($full) {
            foreach ($this->repository->mappings((int)$source['id']) as $mapping) {
                if (!isset($seen[(string)$mapping['google_event_id']])) {
                    if (!empty($mapping['crm_event_public_id'])) $this->repository->deleteCalendarEvent((string)$mapping['crm_event_public_id'], (int)$connection['user_id']);
                    $this->repository->deleteEventMapping((int)$mapping['id']); $deleted++;
                }
            }
        }
        $this->repository->updateSource((int)$source['id'], ['sync_token' => $token, 'last_sync_at' => gmdate('Y-m-d H:i:s'), 'last_error' => null]);
        return ['pulled' => $pulled, 'deleted' => $deleted];
    }

    private function upsertRemote(int $ownerId, int $sourceId, array $event): void
    {
        $googleId = (string)$event['id']; $mapping = $this->repository->event($sourceId, $googleId); $etag = (string)($event['etag'] ?? ''); $now = gmdate('Y-m-d H:i:s');
        if ($mapping && $etag !== '' && (string)($mapping['etag'] ?? '') === $etag) {
            // Preserve a local edit made since the previous sync. It will be
            // pushed after the pull; an unchanged Google etag is not a conflict.
            $local = !empty($mapping['crm_event_public_id']) ? $this->repository->calendarEvent((string)$mapping['crm_event_public_id']) : null;
            $localNewer = $local && !empty($mapping['last_synced_at']) && strtotime((string)$local['updated_at']) > strtotime((string)$mapping['last_synced_at']);
            $this->repository->updateEventMapping((int)$mapping['id'], $localNewer ? ['last_error' => null] : ['last_synced_at' => $now, 'last_error' => null]);
            return;
        }
        $data = $this->eventData($event);
        if ($mapping && !empty($mapping['crm_event_public_id'])) {
            // Google wins if both sides changed; this update is the canonical
            // remote version and the next push sees the new sync timestamp.
            $this->repository->updateCalendarEvent((string)$mapping['crm_event_public_id'], $data, $ownerId);
            $this->repository->updateEventMapping((int)$mapping['id'], [
                'etag' => $etag, 'google_updated_at' => $this->googleUpdated($event), 'is_all_day' => $data['is_all_day'],
                'all_day_start' => $data['all_day_start'], 'all_day_end' => $data['all_day_end'],
                'recurring_event_id' => $event['recurringEventId'] ?? (!empty($event['recurrence']) ? $googleId : null), 'last_synced_at' => $now, 'status' => 'active', 'last_error' => null,
            ]);
            return;
        }
        $crmId = $this->repository->createCalendarEvent($ownerId, $data);
        $this->repository->insertEvent($sourceId, $googleId, $crmId, [
            'recurring_event_id' => $event['recurringEventId'] ?? (!empty($event['recurrence']) ? $googleId : null), 'etag' => $etag,
            'google_updated_at' => $this->googleUpdated($event), 'is_all_day' => $data['is_all_day'], 'all_day_start' => $data['all_day_start'], 'all_day_end' => $data['all_day_end'],
        ]);
    }

    private function pushLocal(array $connection, array $source, string &$access): int
    {
        $last = (string)($source['last_sync_at'] ?? ''); $count = 0;
        foreach ($this->repository->mappings((int)$source['id']) as $mapping) {
            $crmId = (string)($mapping['crm_event_public_id'] ?? ''); if ($crmId === '') continue;
            $local = $this->repository->calendarEvent($crmId);
            if (!$local) {
                try { $this->deleteRemoteWithRefresh($connection, $access, (string)$source['calendar_id'], (string)$mapping['google_event_id']); } catch (\Throwable) {}
                $this->repository->deleteEventMapping((int)$mapping['id']); continue;
            }
            if ($last !== '' && strtotime((string)$local['updated_at']) <= strtotime((string)($mapping['last_synced_at'] ?? '1970-01-01'))) continue;
            $remote = $this->updateRemoteWithRefresh($connection, $access, (string)$source['calendar_id'], (string)$mapping['google_event_id'], $this->googleData($local, $crmId, $mapping));
            $this->repository->updateEventMapping((int)$mapping['id'], ['etag' => $remote['etag'] ?? $mapping['etag'], 'google_updated_at' => $this->googleUpdated($remote), 'last_synced_at' => gmdate('Y-m-d H:i:s')]); $count++;
        }
        foreach ($this->repository->localEventsForUser((int)$connection['user_id'], $last !== '' ? $last : null) as $local) {
            $remote = $this->createRemoteWithRefresh($connection, $access, (string)$source['calendar_id'], $this->googleData($local, (string)$local['public_id']));
            $this->repository->updateCalendarEvent((string)$local['public_id'], $local + ['google_event_id' => $remote['id'] ?? null], (int)$connection['user_id']);
            $this->repository->insertEvent((int)$source['id'], (string)($remote['id'] ?? ''), (string)$local['public_id'], ['etag' => $remote['etag'] ?? null, 'google_updated_at' => $this->googleUpdated($remote)]); $count++;
        }
        $this->repository->updateSource((int)$source['id'], ['last_sync_at' => gmdate('Y-m-d H:i:s')]); return $count;
    }

    private function ownedConnection(int $id, int $userId): array
    {
        $connection = $this->repository->connectionById($id);
        if (!$connection || (int)$connection['user_id'] !== $userId) throw new RuntimeException('GOOGLE_CONNECTION_NOT_FOUND');
        return $connection;
    }

    /** @return array<int,array<string,mixed>> */
    private function calendarsWithRefresh(array $connection, string &$access): array
    {
        try {
            return $this->client->calendars($access);
        } catch (RuntimeException $e) {
            if ((int)$e->getCode() !== 401 && $e->getMessage() !== 'GOOGLE_ACCESS_TOKEN_EXPIRED') throw $e;
            $access = $this->forceRefreshAccessToken($connection);
            return $this->client->calendars($access);
        }
    }

    /** @return array<string,mixed> */
    private function updateRemoteWithRefresh(array $connection, string &$access, string $calendarId, string $eventId, array $payload): array
    {
        try { return $this->client->updateEvent($access, $calendarId, $eventId, $payload); }
        catch (RuntimeException $e) { if ((int)$e->getCode() !== 401 && $e->getMessage() !== 'GOOGLE_ACCESS_TOKEN_EXPIRED') throw $e; $access = $this->forceRefreshAccessToken($connection); return $this->client->updateEvent($access, $calendarId, $eventId, $payload); }
    }

    /** @return array<string,mixed> */
    private function createRemoteWithRefresh(array $connection, string &$access, string $calendarId, array $payload): array
    {
        try { return $this->client->createEvent($access, $calendarId, $payload); }
        catch (RuntimeException $e) { if ((int)$e->getCode() !== 401 && $e->getMessage() !== 'GOOGLE_ACCESS_TOKEN_EXPIRED') throw $e; $access = $this->forceRefreshAccessToken($connection); return $this->client->createEvent($access, $calendarId, $payload); }
    }

    private function deleteRemoteWithRefresh(array $connection, string &$access, string $calendarId, string $eventId): void
    {
        try { $this->client->deleteEvent($access, $calendarId, $eventId); }
        catch (RuntimeException $e) { if ((int)$e->getCode() !== 401 && $e->getMessage() !== 'GOOGLE_ACCESS_TOKEN_EXPIRED') throw $e; $access = $this->forceRefreshAccessToken($connection); $this->client->deleteEvent($access, $calendarId, $eventId); }
    }

    private function accessToken(array $connection): string
    {
        $access = EncryptionService::decrypt((string)($connection['access_token_encrypted'] ?? '')); $expires = strtotime((string)($connection['access_token_expires_at'] ?? ''));
        if ($access !== null && $expires > time() + 60) return $access;
        return $this->forceRefreshAccessToken($connection);
    }

    private function forceRefreshAccessToken(array $connection): string
    {
        $refresh = EncryptionService::decrypt((string)($connection['refresh_token_encrypted'] ?? '')); if ($refresh === null) throw new RuntimeException('GOOGLE_REFRESH_REVOKED');
        try { $token = $this->client->refresh($refresh); } catch (\Throwable $e) { $this->repository->updateConnection((int)$connection['id'], ['status' => 'reauthorization_required', 'last_error' => 'Google authorization expired']); throw $e; }
        $access = (string)$token['access_token']; $this->repository->updateConnection((int)$connection['id'], ['access_token_encrypted' => EncryptionService::encrypt($access), 'access_token_expires_at' => gmdate('Y-m-d H:i:s', time() + (int)($token['expires_in'] ?? 3600))]); return $access;
    }

    /** @return array<string,mixed> */
    private function eventData(array $event): array
    {
        $start = $this->datePart((array)($event['start'] ?? [])); $end = $this->datePart((array)($event['end'] ?? [])); $allDay = !empty($event['start']['date']) && !empty($event['end']['date']);
        return ['title' => mb_substr(trim((string)($event['summary'] ?? '(Без названия)')), 0, 255), 'description' => strip_tags((string)($event['description'] ?? '')), 'starts_at' => $start, 'ends_at' => $end, 'google_event_id' => (string)($event['id'] ?? ''), 'is_all_day' => $allDay ? 1 : 0, 'all_day_start' => $allDay ? (string)$event['start']['date'] : null, 'all_day_end' => $allDay ? (string)$event['end']['date'] : null];
    }

    private function googleData(array $local, string $crmId, ?array $mapping = null): array
    {
        if ($mapping && !empty($mapping['is_all_day']) && !empty($mapping['all_day_start']) && !empty($mapping['all_day_end'])) return ['summary' => mb_substr((string)$local['title'], 0, 255), 'description' => strip_tags((string)($local['description'] ?? '')), 'start' => ['date' => (string)$mapping['all_day_start']], 'end' => ['date' => (string)$mapping['all_day_end']], 'extendedProperties' => ['private' => ['tropatt_event_public_id' => $crmId]]];
        return ['summary' => mb_substr((string)$local['title'], 0, 255), 'description' => strip_tags((string)($local['description'] ?? '')), 'start' => ['dateTime' => $this->rfc3339((string)$local['starts_at'])], 'end' => ['dateTime' => $this->rfc3339((string)$local['ends_at'])], 'extendedProperties' => ['private' => ['tropatt_event_public_id' => $crmId]]];
    }

    private function datePart(array $part): string
    {
        if (!empty($part['dateTime'])) { $date = new \DateTimeImmutable((string)$part['dateTime']); return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
        if (!empty($part['date'])) return (new \DateTimeImmutable((string)$part['date'] . ' 00:00:00', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        return gmdate('Y-m-d H:i:s');
    }

    private function rfc3339(string $value): string
    {
        $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC')); return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private function googleUpdated(array $event): ?string
    {
        if (empty($event['updated'])) return null;
        try { return (new \DateTimeImmutable((string)$event['updated']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'); } catch (\Throwable) { return null; }
    }
}
