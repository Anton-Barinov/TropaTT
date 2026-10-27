<?php

declare(strict_types=1);

namespace Module\Crm\Zoom\Service;

use Api\System\Library\Security\UrlSafetyValidator;
use Api\Model\Calendar\CalendarEventRepository;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** Workspace-scoped Zoom Server-to-Server OAuth and meeting integration. */
final class ZoomService
{
    private const AAD_PREFIX = 'crm.zoom.credentials.v1:';
    private const MAX_RESPONSE_BYTES = 65536;
    private ?Closure $transport;
    private ?Closure $calendarEventCreator;
    private ?Closure $calendarEventAfterCommit;

    /** @param null|callable(string,string,array<string,string>,string):array{status:int,body:array<string,mixed>} $transport Test seam; production always uses pinned HTTPS cURL. */
    public function __construct(
        private readonly PDO $db,
        private readonly int $workspaceId,
        private readonly int $actorUserId = 0,
        ?callable $transport = null,
        ?callable $calendarEventCreator = null,
        ?callable $calendarEventAfterCommit = null,
    ) {
        if ($workspaceId <= 0) throw new RuntimeException('ACTIVE_WORKSPACE_REQUIRED');
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
        $this->calendarEventCreator = $calendarEventCreator === null ? null : Closure::fromCallable($calendarEventCreator);
        $this->calendarEventAfterCommit = $calendarEventAfterCommit === null ? null : Closure::fromCallable($calendarEventAfterCommit);
    }

    /** @return array<string,mixed> */
    public function getStatus(): array
    {
        $this->migrateLegacyMeetingSecrets();
        $row = $this->settingsRow();
        $configured = is_array($row)
            && trim((string)($row['account_id'] ?? '')) !== ''
            && trim((string)($row['client_id'] ?? '')) !== ''
            && str_starts_with((string)($row['client_secret'] ?? ''), 'gcm:')
            && trim((string)($row['host_user_id'] ?? '')) !== '';
        return [
            'status' => $configured ? 'configured' : 'not_configured',
            'configured' => $configured,
            'connected' => $configured && !empty($row['verified_email']),
            'host_user_id' => $configured ? (string)$row['host_user_id'] : '',
            'account_id' => $configured ? (string)$row['account_id'] : '',
            'client_id' => $configured ? (string)$row['client_id'] : '',
            'host_email' => $configured ? (string)($row['verified_email'] ?? '') : '',
            'features' => ['meetings' => true, 'recordings' => false, 'transcripts' => false],
            'message' => $configured ? 'Connection settings are saved. Recheck the connection before relying on it.' : 'Configure Zoom Server-to-Server OAuth for this workspace.',
        ];
    }

    public function saveSettings(array $input): void
    {
        $accountId = trim((string)($input['account_id'] ?? ''));
        $clientId = trim((string)($input['client_id'] ?? ''));
        $clientSecret = trim((string)($input['client_secret'] ?? ''));
        $hostUserId = trim((string)($input['host_user_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/', $accountId)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/', $clientId)
            || !(preg_match('/^[A-Za-z0-9_.-]{1,128}$/', $hostUserId) === 1
                || (strlen($hostUserId) <= 254 && filter_var($hostUserId, FILTER_VALIDATE_EMAIL) !== false))) {
            throw new RuntimeException('ZOOM_SETTINGS_INVALID');
        }
        if ($clientSecret !== '' && (strlen($clientSecret) > 512 || preg_match('/[\r\n\x00]/', $clientSecret))) {
            throw new RuntimeException('ZOOM_SETTINGS_INVALID');
        }
        $existing = $this->settingsRow();
        if ($clientSecret === '') {
            if (!is_array($existing) || !str_starts_with((string)($existing['client_secret'] ?? ''), 'gcm:')) {
                throw new RuntimeException('ZOOM_SECRET_REQUIRED');
            }
            $encryptedSecret = (string)$existing['client_secret'];
        } else {
            $encryptedSecret = $this->encrypt($clientSecret);
        }

        $sql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO module_zoom_settings (workspace_id, account_id, client_id, client_secret, host_user_id, verified_email) VALUES (:workspace_id, :account_id, :client_id, :client_secret, :host_user_id, NULL) ON CONFLICT(workspace_id) DO UPDATE SET account_id=excluded.account_id, client_id=excluded.client_id, client_secret=excluded.client_secret, host_user_id=excluded.host_user_id, verified_email=NULL'
            : 'INSERT INTO module_zoom_settings (workspace_id, account_id, client_id, client_secret, host_user_id, verified_email) VALUES (:workspace_id, :account_id, :client_id, :client_secret, :host_user_id, NULL) ON DUPLICATE KEY UPDATE account_id=VALUES(account_id), client_id=VALUES(client_id), client_secret=VALUES(client_secret), host_user_id=VALUES(host_user_id), verified_email=NULL';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':workspace_id' => $this->workspaceId,
            ':account_id' => $accountId,
            ':client_id' => $clientId,
            ':client_secret' => $encryptedSecret,
            ':host_user_id' => $hostUserId,
        ]);
    }

    /** Exchanges account_credentials and verifies the configured host through Zoom's Users API. */
    public function testConnection(): array
    {
        $row = $this->requiredSettings();
        $token = $this->accessToken($row);
        $user = $this->request('GET', '/users/' . rawurlencode((string)$row['host_user_id']), ['Authorization' => 'Bearer ' . $token]);
        $email = trim((string)($user['email'] ?? ''));
        $userId = trim((string)($user['id'] ?? ''));
        if ($email === '' || $userId === '') throw new RuntimeException('ZOOM_HOST_NOT_VERIFIED');
        $stmt = $this->db->prepare('UPDATE module_zoom_settings SET verified_email = :email WHERE workspace_id = :workspace_id');
        $stmt->execute([':email' => $email, ':workspace_id' => $this->workspaceId]);
        return ['connected' => true, 'host_email' => $email, 'host_user_id' => $userId];
    }

    /** @return array<string,mixed> */
    public function createMeeting(array $input, string $idempotencyKey): array
    {
        if ($this->actorUserId <= 0) throw new RuntimeException('ZOOM_ACTOR_REQUIRED');
        $row = $this->requiredSettings();
        if (empty($row['verified_email'])) throw new RuntimeException('ZOOM_CONNECTION_NOT_VERIFIED');
        $meeting = self::validateMeetingInput($input);
        if (!preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $idempotencyKey)) throw new RuntimeException('ZOOM_IDEMPOTENCY_KEY_REQUIRED');
        $keyHash = hash('sha256', $idempotencyKey);
        $payloadHash = hash('sha256', json_encode($meeting, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $reservation = $this->reserveIdempotency($keyHash, $payloadHash);
        if ($reservation['state'] === 'complete') return $this->meetingByPublicId((string)$reservation['meeting_public_id']);
        if (!$reservation['created']) throw new RuntimeException('ZOOM_CREATE_RESULT_UNKNOWN');

        $providerPostAttempted = false;
        try {
            $token = $this->accessToken($row);
            $eventRepository = new CalendarEventRepository($this->db);
            // Hold the same calendar owner lock as core event creation and public
            // booking until the external meeting and its CRM event are committed.
            $this->db->beginTransaction();
            $eventRepository->lockConflictUsers([$this->actorUserId]);
            $startUtc = (new DateTimeImmutable($meeting['start_time_iso']))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $endUtc = (new DateTimeImmutable($meeting['start_time_iso']))->setTimezone(new DateTimeZone('UTC'))->modify('+' . $meeting['duration'] . ' minutes')->format('Y-m-d H:i:s');
            if ($eventRepository->hasPublicBookingConflict($this->workspaceId, [$this->actorUserId], $startUtc, $endUtc)) {
                throw new RuntimeException('ZOOM_CALENDAR_CONFLICT');
            }
            $providerPostAttempted = true;
            $response = $this->request('POST', '/users/' . rawurlencode((string)$row['host_user_id']) . '/meetings', ['Authorization' => 'Bearer ' . $token], [
                'topic' => $meeting['topic'],
                'type' => 2,
                'start_time' => $meeting['start_time_iso'],
                'duration' => $meeting['duration'],
                'timezone' => $meeting['timezone'],
                'agenda' => $meeting['agenda'],
                'settings' => ['waiting_room' => true, 'join_before_host' => false],
            ]);
            $providerId = filter_var($response['id'] ?? null, FILTER_VALIDATE_INT);
            $joinUrl = (string)($response['join_url'] ?? '');
            $startUrl = (string)($response['start_url'] ?? '');
            $passcode = (string)($response['password'] ?? '');
            if (!$providerId || !$this->isZoomJoinUrl($joinUrl) || !$this->isZoomStartUrl($startUrl) || strlen($passcode) > 64) {
                throw new RuntimeException('ZOOM_RESPONSE_INVALID');
            }
            $publicId = 'zmeet_' . bin2hex(random_bytes(12));
            $cleanJoinUrl = $this->safeJoinUrl($joinUrl);
            $eventPayload = [
                'title' => $meeting['topic'], 'description' => 'Zoom meeting ' . $providerId . "\n" . $cleanJoinUrl,
                'starts_at' => $startUtc, 'ends_at' => $endUtc,
            ];
            if ($this->calendarEventCreator !== null) {
                $createdEvent = ($this->calendarEventCreator)($eventPayload, [
                    'source_type' => 'zoom', 'source_owner_user_id' => $this->actorUserId, 'source_external_id' => (string)$providerId,
                ]);
                if (!is_array($createdEvent) || !is_string($createdEvent['public_id'] ?? null)) {
                    throw new RuntimeException($createdEvent === 'BOOKING_CONFLICT' ? 'ZOOM_CALENDAR_CONFLICT' : 'ZOOM_CALENDAR_UNAVAILABLE');
                }
                $eventPublicId = (string)$createdEvent['public_id'];
            } else {
                $eventPublicId = 'evt_' . bin2hex(random_bytes(12));
                $calendarEventId = $eventRepository->create([
                    'public_id' => $eventPublicId, 'title' => $meeting['topic'],
                    'description' => $eventPayload['description'], 'starts_at' => $startUtc, 'ends_at' => $endUtc,
                    'owner_user_id' => $this->actorUserId, 'project_id' => null, 'task_id' => null,
                    'source_type' => 'zoom', 'source_owner_user_id' => $this->actorUserId,
                    'source_external_id' => (string)$providerId, 'organization_id' => $this->workspaceId,
                    'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
                ], [$this->actorUserId]);
                if ($calendarEventId < 1) throw new RuntimeException('ZOOM_CALENDAR_CONFLICT');
                $eventStmt = $this->db->prepare('SELECT * FROM calendar_events WHERE id = :id');
                $eventStmt->execute([':id' => $calendarEventId]);
                $createdEvent = $eventStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            $insert = $this->db->prepare(
                'INSERT INTO module_zoom_meetings (public_id, workspace_id, crm_calendar_event_public_id, zoom_meeting_id, topic, agenda, start_time, duration_minutes, timezone, join_url, join_url_encrypted, start_url_encrypted, passcode_encrypted, status, provider_verified, idempotency_key_hash, payload_hash)
                 VALUES (:public_id, :workspace_id, :event_id, :zoom_id, :topic, :agenda, :start_time, :duration, :timezone, :join_url, :join_encrypted, :start_encrypted, :passcode_encrypted, :status, 1, :key_hash, :payload_hash)'
            );
            $insert->execute([
                ':public_id' => $publicId, ':workspace_id' => $this->workspaceId, ':event_id' => $eventPublicId,
                ':zoom_id' => (int)$providerId, ':topic' => $meeting['topic'], ':agenda' => $meeting['agenda'],
                ':start_time' => $startUtc, ':duration' => $meeting['duration'], ':timezone' => $meeting['timezone'],
                ':join_url' => $cleanJoinUrl, ':join_encrypted' => $this->encrypt($joinUrl),
                ':start_encrypted' => $this->encrypt($startUrl), ':passcode_encrypted' => $passcode === '' ? null : $this->encrypt($passcode),
                ':status' => (string)($response['status'] ?? 'waiting'), ':key_hash' => $keyHash, ':payload_hash' => $payloadHash,
            ]);
            $update = $this->db->prepare('UPDATE module_zoom_idempotency SET state = :state, meeting_public_id = :meeting_id WHERE workspace_id = :workspace_id AND idempotency_key_hash = :key_hash');
            $update->execute([':state' => 'complete', ':meeting_id' => $publicId, ':workspace_id' => $this->workspaceId, ':key_hash' => $keyHash]);
            $this->db->commit();
            if (is_array($createdEvent) && $this->calendarEventAfterCommit !== null) {
                try { ($this->calendarEventAfterCommit)($createdEvent); }
                catch (Throwable) { /* Provider creation and outbox are committed; do not report a false failure. */ }
            }
            return [
                'public_id' => $publicId, 'crm_calendar_event_public_id' => $eventPublicId,
                'zoom_meeting_id' => (string)$providerId, 'topic' => $meeting['topic'],
                'start_time' => $startUtc, 'duration_minutes' => $meeting['duration'],
                'timezone' => $meeting['timezone'], 'join_url' => $cleanJoinUrl,
                'status' => (string)($response['status'] ?? 'waiting'),
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            // Deliberately keep the reservation pending. A timeout or local failure after POST
            // cannot safely be retried without risking a duplicate Zoom meeting.
            if ($providerPostAttempted) throw new RuntimeException('ZOOM_CREATE_RESULT_UNKNOWN', 0, $e);
            // Before any provider POST, a known local conflict/failure is safe to
            // retry with the same key. Release only this still-pending reservation;
            // once POST may have happened, keep it fail-closed indefinitely.
            $this->releasePendingIdempotency($keyHash, $payloadHash);
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('ZOOM_OPERATION_FAILED', 0, $e);
        }
    }

    /** @return list<array<string,mixed>> */
    public function listMeetings(): array
    {
        $this->migrateLegacyMeetingSecrets();
        $stmt = $this->db->prepare('SELECT public_id, crm_calendar_event_public_id, zoom_meeting_id, topic, agenda, start_time, duration_minutes, timezone, join_url, status, created_at FROM module_zoom_meetings WHERE workspace_id = :workspace_id AND provider_verified = 1 ORDER BY start_time DESC LIMIT 50');
        $stmt->execute([':workspace_id' => $this->workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{topic:string,agenda:string,start_time_iso:string,duration:int,timezone:string} */
    public static function validateMeetingInput(array $input): array
    {
        $allowed = ['topic', 'agenda', 'timezone', 'duration', 'start_time'];
        if (array_diff(array_keys($input), $allowed) !== []) throw new RuntimeException('ZOOM_MEETING_INPUT_INVALID');
        $topic = trim((string)($input['topic'] ?? ''));
        $agenda = trim((string)($input['agenda'] ?? ''));
        $timezone = trim((string)($input['timezone'] ?? 'UTC'));
        $duration = (int)($input['duration'] ?? 40);
        if ($topic === '' || strlen($topic) > 800 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $topic)) throw new RuntimeException('ZOOM_MEETING_INPUT_INVALID');
        if (strlen($agenda) > 8000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $agenda)) throw new RuntimeException('ZOOM_MEETING_INPUT_INVALID');
        if ($duration < 15 || $duration > 480) throw new RuntimeException('ZOOM_MEETING_INPUT_INVALID');
        try { $zone = new DateTimeZone($timezone); $start = new DateTimeImmutable((string)($input['start_time'] ?? ''), $zone); }
        catch (Throwable) { throw new RuntimeException('ZOOM_MEETING_INPUT_INVALID'); }
        if ($start->getTimestamp() < time() + 60) throw new RuntimeException('ZOOM_MEETING_INPUT_INVALID');
        return ['topic' => $topic, 'agenda' => $agenda, 'start_time_iso' => $start->setTimezone($zone)->format('Y-m-d\TH:i:s'), 'duration' => $duration, 'timezone' => $zone->getName()];
    }

    private function releasePendingIdempotency(string $keyHash, string $payloadHash): void
    {
        $stmt = $this->db->prepare("DELETE FROM module_zoom_idempotency WHERE workspace_id = :workspace_id AND idempotency_key_hash = :key_hash AND payload_hash = :payload_hash AND state = 'pending' AND meeting_public_id IS NULL");
        $stmt->execute([':workspace_id' => $this->workspaceId, ':key_hash' => $keyHash, ':payload_hash' => $payloadHash]);
    }

    /** @return array{state:string,created:bool,meeting_public_id?:string} */
    private function reserveIdempotency(string $keyHash, string $payloadHash): array
    {
        try {
            $stmt = $this->db->prepare('INSERT INTO module_zoom_idempotency (workspace_id, idempotency_key_hash, payload_hash, state, created_at) VALUES (:workspace_id, :key_hash, :payload_hash, :state, CURRENT_TIMESTAMP)');
            $stmt->execute([':workspace_id' => $this->workspaceId, ':key_hash' => $keyHash, ':payload_hash' => $payloadHash, ':state' => 'pending']);
            return ['state' => 'pending', 'created' => true];
        } catch (PDOException $e) {
            $check = $this->db->prepare('SELECT payload_hash, state, meeting_public_id FROM module_zoom_idempotency WHERE workspace_id = :workspace_id AND idempotency_key_hash = :key_hash');
            $check->execute([':workspace_id' => $this->workspaceId, ':key_hash' => $keyHash]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if (!is_array($existing)) throw $e;
            if (!hash_equals((string)$existing['payload_hash'], $payloadHash)) throw new RuntimeException('ZOOM_IDEMPOTENCY_CONFLICT');
            return ['state' => (string)$existing['state'], 'created' => false, 'meeting_public_id' => (string)($existing['meeting_public_id'] ?? '')];
        }
    }

    /** @return array<string,mixed> */
    private function meetingByPublicId(string $publicId): array
    {
        $stmt = $this->db->prepare('SELECT public_id, crm_calendar_event_public_id, zoom_meeting_id, topic, start_time, duration_minutes, timezone, join_url, status FROM module_zoom_meetings WHERE workspace_id = :workspace_id AND public_id = :public_id LIMIT 1');
        $stmt->execute([':workspace_id' => $this->workspaceId, ':public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('ZOOM_MEETING_NOT_FOUND');
        return $row;
    }

    private function requiredSettings(): array
    {
        $row = $this->settingsRow();
        if (!is_array($row) || trim((string)($row['account_id'] ?? '')) === '' || trim((string)($row['client_id'] ?? '')) === '' || trim((string)($row['host_user_id'] ?? '')) === '') throw new RuntimeException('ZOOM_NOT_CONFIGURED');
        $row['client_secret_plain'] = $this->decrypt((string)$row['client_secret']);
        return $row;
    }

    private function settingsRow(): ?array
    {
        $stmt = $this->db->prepare('SELECT account_id, client_id, client_secret, host_user_id, verified_email FROM module_zoom_settings WHERE workspace_id = :workspace_id LIMIT 1');
        $stmt->execute([':workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** Move legacy host URLs/passcodes into authenticated encryption without losing them. */
    private function migrateLegacyMeetingSecrets(): void
    {
        $stmt = $this->db->prepare(
            'SELECT id, start_url, passcode FROM module_zoom_meetings
             WHERE workspace_id = :workspace_id
               AND ((start_url IS NOT NULL AND start_url <> \'\' AND start_url_encrypted IS NULL)
                 OR (passcode IS NOT NULL AND passcode <> \'\' AND passcode_encrypted IS NULL))'
        );
        $stmt->execute([':workspace_id' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) return;

        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare(
                'UPDATE module_zoom_meetings
                 SET start_url = NULL, passcode = NULL,
                     start_url_encrypted = COALESCE(start_url_encrypted, :start_encrypted),
                     passcode_encrypted = COALESCE(passcode_encrypted, :pass_encrypted)
                 WHERE id = :id AND workspace_id = :workspace_id'
            );
            foreach ($rows as $row) {
                $startUrl = (string)($row['start_url'] ?? '');
                $passcode = (string)($row['passcode'] ?? '');
                $update->execute([
                    ':start_encrypted' => $startUrl === '' ? null : $this->encrypt($startUrl),
                    ':pass_encrypted' => $passcode === '' ? null : $this->encrypt($passcode),
                    ':id' => (int)$row['id'],
                    ':workspace_id' => $this->workspaceId,
                ]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function accessToken(array $settings): string
    {
        $this->assertProviderEndpoint('https://zoom.us/oauth/token');
        $basic = base64_encode((string)$settings['client_id'] . ':' . (string)$settings['client_secret_plain']);
        $result = $this->send('POST', 'https://zoom.us/oauth/token', [
            'Authorization' => 'Basic ' . $basic,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], http_build_query(['grant_type' => 'account_credentials', 'account_id' => (string)$settings['account_id']], '', '&', PHP_QUERY_RFC3986));
        $token = trim((string)($result['access_token'] ?? ''));
        if (($result['_status'] ?? 0) !== 200 || $token === '' || strlen($token) > 8192) throw new RuntimeException('ZOOM_AUTH_FAILED');
        return $token;
    }

    private function request(string $method, string $path, array $headers, ?array $body = null): array
    {
        if (!preg_match('#^/(users/[A-Za-z0-9%._@+-]+(?:/meetings)?)$#', $path)) throw new RuntimeException('ZOOM_PATH_INVALID');
        $url = 'https://api.zoom.us/v2' . $path;
        $this->assertProviderEndpoint($url);
        $headers['Accept'] = 'application/json';
        if ($body !== null) $headers['Content-Type'] = 'application/json';
        $raw = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $result = $this->send($method, $url, $headers, $raw);
        $status = (int)($result['_status'] ?? 0);
        unset($result['_status']);
        if ($status < 200 || $status >= 300) throw new RuntimeException($status === 401 || $status === 403 ? 'ZOOM_SCOPE_OR_AUTH_FAILED' : 'ZOOM_API_FAILED');
        return $result;
    }

    private function send(string $method, string $url, array $headers, string $body): array
    {
        if ($this->transport !== null) {
            $result = ($this->transport)($method, $url, $headers, $body);
            if (!isset($result['status'], $result['body']) || !is_array($result['body'])) throw new RuntimeException('ZOOM_TRANSPORT_INVALID');
            return $result['body'] + ['_status' => (int)$result['status']];
        }
        if (!function_exists('curl_init') || !extension_loaded('openssl')) throw new RuntimeException('ZOOM_HTTPS_UNAVAILABLE');
        $safe = (new UrlSafetyValidator())->validateProviderUrl($url, true, ['https']);
        if (empty($safe['ok']) || empty($safe['resolved_ips'])) throw new RuntimeException('ZOOM_ENDPOINT_UNSAFE');
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        $expected = str_contains($url, '://zoom.us/') ? 'zoom.us' : 'api.zoom.us';
        if ($host !== $expected || (int)($parts['port'] ?? 443) !== 443 || isset($parts['user']) || isset($parts['pass'])) throw new RuntimeException('ZOOM_ENDPOINT_UNSAFE');
        $ip = trim((string)$safe['resolved_ips'][0], '[]');
        $resolved = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[' . $ip . ']' : $ip;
        $headerLines = [];
        foreach ($headers as $name => $value) $headerLines[] = $name . ': ' . $value;
        $response = '';
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('ZOOM_HTTPS_UNAVAILABLE');
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headerLines, CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$host . ':443:' . $resolved], CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*',
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > self::MAX_RESPONSE_BYTES) return 0;
                $response .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($ok !== true) throw new RuntimeException('ZOOM_NETWORK_RESULT_UNKNOWN');
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('ZOOM_RESPONSE_INVALID');
        return $decoded + ['_status' => $status];
    }

    private function assertProviderEndpoint(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        $expectedHost = str_contains($url, '://zoom.us/') ? 'zoom.us' : 'api.zoom.us';
        if (($parts['scheme'] ?? '') !== 'https' || $host !== $expectedHost || (int)($parts['port'] ?? 443) !== 443 || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('ZOOM_ENDPOINT_UNSAFE');
        }
    }

    private function isZoomJoinUrl(string $url): bool
    {
        $p = parse_url($url);
        return is_array($p) && ($p['scheme'] ?? '') === 'https' && in_array(strtolower((string)($p['host'] ?? '')), ['zoom.us', 'www.zoom.us'], true) && (int)($p['port'] ?? 443) === 443 && preg_match('#^/j/[0-9]+$#', (string)($p['path'] ?? '')) === 1 && !isset($p['user']) && !isset($p['pass']);
    }

    private function isZoomStartUrl(string $url): bool
    {
        $p = parse_url($url);
        return is_array($p) && ($p['scheme'] ?? '') === 'https' && in_array(strtolower((string)($p['host'] ?? '')), ['zoom.us', 'www.zoom.us'], true) && (int)($p['port'] ?? 443) === 443 && preg_match('#^/s/[0-9]+$#', (string)($p['path'] ?? '')) === 1 && !isset($p['user']) && !isset($p['pass']);
    }

    private function safeJoinUrl(string $url): string
    {
        $p = parse_url($url);
        return 'https://' . strtolower((string)$p['host']) . (string)$p['path'];
    }

    private function encrypt(string $plain): string
    {
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('ZOOM_ENCRYPTION_UNAVAILABLE');
        $nonce = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->cryptoKey(), OPENSSL_RAW_DATA, $nonce, $tag, self::AAD_PREFIX . $this->workspaceId, 16);
        if (!is_string($cipher) || strlen($tag) !== 16) throw new RuntimeException('ZOOM_ENCRYPTION_FAILED');
        return 'gcm:' . base64_encode($nonce . $tag . $cipher);
    }

    private function decrypt(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'gcm:') || !function_exists('openssl_decrypt')) throw new RuntimeException('ZOOM_REENTER_CREDENTIALS');
        $raw = base64_decode(substr($encrypted, 4), true);
        if (!is_string($raw) || strlen($raw) < 29) throw new RuntimeException('ZOOM_REENTER_CREDENTIALS');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->cryptoKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), self::AAD_PREFIX . $this->workspaceId);
        if (!is_string($plain) || $plain === '') throw new RuntimeException('ZOOM_REENTER_CREDENTIALS');
        return $plain;
    }

    private function cryptoKey(): string
    {
        $key = trim((string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '')));
        if (strlen($key) < 32) throw new RuntimeException('ZOOM_APP_KEY_REQUIRED');
        return hash('sha256', $key, true);
    }
}
