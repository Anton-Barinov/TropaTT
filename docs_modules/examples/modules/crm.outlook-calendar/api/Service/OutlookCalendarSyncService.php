<?php

declare(strict_types=1);

namespace Module\Crm\OutlookCalendar\Service;

use Api\Model\Calendar\CalendarEventRepository;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Delegated Microsoft Graph calendar import for a single CRM workspace/user.
 * Uses PHP streams and PDO only, so it works on ordinary shared hosting.
 */
final class OutlookCalendarSyncService
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';
    private const OAUTH_SCOPES = 'openid profile offline_access User.Read Calendars.Read';
    private const MAX_RESPONSE_BYTES = 2097152;
    private const MAX_PAGES_PER_RUN = 3;
    private PDO $db;
    private int $workspaceId;
    /** @var Closure(string,string,array<string,string>,?string):array{status:int,body:string}|null */
    private ?Closure $httpTransport;
    private ?Closure $calendarEventCreator;
    private ?Closure $calendarEventAfterCommit;
    /** @var list<array{event:array<string,mixed>,actor:array<string,mixed>}> */
    private array $pendingCalendarDispatches = [];

    /** @param null|callable(string,string,array<string,string>,?string):array{status:int,body:string} $httpTransport Test seam; production uses verified HTTPS streams. */
    public function __construct(PDO $db, int $workspaceId, ?callable $httpTransport = null, ?callable $calendarEventCreator = null, ?callable $calendarEventAfterCommit = null)
    {
        if ($workspaceId <= 0) {
            throw new RuntimeException('An active workspace is required.');
        }
        $this->db = $db;
        $this->workspaceId = $workspaceId;
        $this->httpTransport = $httpTransport === null ? null : Closure::fromCallable($httpTransport);
        $this->calendarEventCreator = $calendarEventCreator === null ? null : Closure::fromCallable($calendarEventCreator);
        $this->calendarEventAfterCommit = $calendarEventAfterCommit === null ? null : Closure::fromCallable($calendarEventAfterCommit);
    }

    /** @return array<string,mixed> */
    public function saveSettings(string $tenantId, string $clientId, string $clientSecret, string $redirectUri): array
    {
        $tenantId = trim($tenantId);
        $clientId = trim($clientId);
        $clientSecret = trim($clientSecret);
        $redirectUri = trim($redirectUri);
        if (!$this->isValidTenant($tenantId)) {
            throw new RuntimeException('Use common, organizations, consumers, or a Microsoft Entra tenant UUID.');
        }
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $clientId)) {
            throw new RuntimeException('A valid Microsoft Entra application client ID is required.');
        }
        if (!$this->isValidRedirectUri($redirectUri)) {
            throw new RuntimeException('Redirect URI must be an HTTPS URL ending in /api/v1/modules/outlook-calendar/oauth/callback.');
        }
        if ($clientSecret !== '' && (strlen($clientSecret) > 2048 || preg_match('/[\r\n]/', $clientSecret))) {
            throw new RuntimeException('The Microsoft Entra client secret is invalid.');
        }
        $existing = $this->settings();
        if ($clientSecret === '' && $existing === null) {
            throw new RuntimeException('A Microsoft Entra client secret is required for the initial setup.');
        }
        $encryptedSecret = $clientSecret !== '' ? $this->encryptSecret($clientSecret) : (string)$existing['client_secret_encrypted'];
        if ($existing !== null) {
            $stmt = $this->db->prepare('UPDATE module_outlook_calendar_settings SET tenant_id = :tenant, client_id = :client_id, client_secret_encrypted = :secret, redirect_uri = :redirect_uri, updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :workspace');
            $stmt->execute([':tenant' => $tenantId, ':client_id' => $clientId, ':secret' => $encryptedSecret, ':redirect_uri' => $redirectUri, ':workspace' => $this->workspaceId]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO module_outlook_calendar_settings (workspace_id, tenant_id, client_id, client_secret_encrypted, redirect_uri) VALUES (:workspace, :tenant, :client_id, :secret, :redirect_uri)');
            $stmt->execute([':workspace' => $this->workspaceId, ':tenant' => $tenantId, ':client_id' => $clientId, ':secret' => $encryptedSecret, ':redirect_uri' => $redirectUri]);
        }

        return ['configured' => true, 'tenant_id' => $tenantId, 'client_id' => $clientId, 'redirect_uri' => $redirectUri];
    }

    /** @return array<string,mixed> */
    public function status(int $userId): array
    {
        $settings = $this->settings();
        $connection = $this->connection($userId);
        return [
            'configured' => $settings !== null,
            'tenant_id' => $settings['tenant_id'] ?? null,
            'client_id' => $settings['client_id'] ?? null,
            'redirect_uri' => $settings['redirect_uri'] ?? null,
            'connected' => $connection !== null && (int)$connection['is_active'] === 1,
            'account_email' => $connection['outlook_account_email'] ?? null,
            'calendar' => 'primary',
            'last_synced_at' => $connection['last_synced_at'] ?? null,
            'sync_in_progress' => $connection !== null && !empty($connection['sync_lock_expires_at']) && strtotime((string)$connection['sync_lock_expires_at'] . ' UTC') > time(),
            'sync_window' => $connection === null || empty($connection['sync_window_start']) ? null : [
                'from' => (string)$connection['sync_window_start'],
                'to' => (string)$connection['sync_window_end'],
            ],
        ];
    }

    /** @return array{authorization_url:string} */
    public function beginAuthorization(int $userId): array
    {
        if ($userId <= 0) {
            throw new RuntimeException('An authenticated CRM user is required.');
        }
        $settings = $this->settings();
        if ($settings === null) {
            throw new RuntimeException('Configure the Entra application before connecting Outlook.');
        }
        // Verify the application key now, before sending the user to Microsoft.
        $this->credentialKey();
        $state = $this->base64Url(random_bytes(32));
        $verifier = $this->base64Url(random_bytes(48));
        $challenge = $this->base64Url(hash('sha256', $verifier, true));
        $stmt = $this->db->prepare('DELETE FROM module_outlook_calendar_oauth_states WHERE expires_at < :now OR (workspace_id = :workspace AND user_id = :user)');
        $stmt->execute([':now' => gmdate('Y-m-d H:i:s'), ':workspace' => $this->workspaceId, ':user' => $userId]);
        $insert = $this->db->prepare('INSERT INTO module_outlook_calendar_oauth_states (state_hash, workspace_id, user_id, code_verifier_encrypted, expires_at, created_at) VALUES (:hash, :workspace, :user, :verifier, :expires, :created)');
        $insert->execute([
            ':hash' => hash('sha256', $state),
            ':workspace' => $this->workspaceId,
            ':user' => $userId,
            ':verifier' => $this->encryptSecret($verifier),
            ':expires' => gmdate('Y-m-d H:i:s', time() + 600),
            ':created' => gmdate('Y-m-d H:i:s'),
        ]);
        $query = http_build_query([
            'client_id' => (string)$settings['client_id'],
            'response_type' => 'code',
            'redirect_uri' => (string)$settings['redirect_uri'],
            'response_mode' => 'query',
            'scope' => self::OAUTH_SCOPES,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return ['authorization_url' => 'https://login.microsoftonline.com/' . rawurlencode((string)$settings['tenant_id']) . '/oauth2/v2.0/authorize?' . $query];
    }

    /** Complete the short-lived state-bound authorization code flow and persist encrypted tokens. */
    public function completeAuthorization(int $userId, string $state, string $code): array
    {
        if ($userId <= 0 || !preg_match('/^[A-Za-z0-9_-]{40,64}$/D', $state) || $code === '' || strlen($code) > 4096 || preg_match('/[\r\n]/', $code)) {
            throw new RuntimeException('The Outlook authorization response is invalid. Start the connection again.');
        }
        $stmt = $this->db->prepare('SELECT state_hash, code_verifier_encrypted, expires_at FROM module_outlook_calendar_oauth_states WHERE state_hash = :hash AND workspace_id = :workspace AND user_id = :user LIMIT 1');
        $stmt->execute([':hash' => hash('sha256', $state), ':workspace' => $this->workspaceId, ':user' => $userId]);
        $oauthState = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($oauthState) || strtotime((string)$oauthState['expires_at'] . ' UTC') < time()) {
            throw new RuntimeException('The Outlook authorization state expired or does not match this user and workspace. Start again.');
        }
        $delete = $this->db->prepare('DELETE FROM module_outlook_calendar_oauth_states WHERE state_hash = :hash AND workspace_id = :workspace AND user_id = :user');
        $delete->execute([':hash' => hash('sha256', $state), ':workspace' => $this->workspaceId, ':user' => $userId]);
        if ($delete->rowCount() !== 1) {
            throw new RuntimeException('The Outlook authorization state was already used. Start again.');
        }

        $settings = $this->settings();
        if ($settings === null) {
            throw new RuntimeException('Microsoft Entra application settings are missing.');
        }
        $verifier = $this->decryptSecret((string)$oauthState['code_verifier_encrypted']);
        $token = $this->tokenRequest((string)$settings['tenant_id'], [
            'client_id' => (string)$settings['client_id'],
            'client_secret' => $this->decryptSecret((string)$settings['client_secret_encrypted']),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => (string)$settings['redirect_uri'],
            'code_verifier' => $verifier,
            'scope' => self::OAUTH_SCOPES,
        ]);
        $identity = $this->graphRequest('GET', self::GRAPH_BASE . '/me?$select=id,displayName,mail,userPrincipalName', (string)$token['access_token']);
        $email = trim((string)($identity['mail'] ?? $identity['userPrincipalName'] ?? ''));
        if ($email === '' || strlen($email) > 255 || preg_match('/[\r\n]/', $email)) {
            throw new RuntimeException('Microsoft did not return a usable account address. Check User.Read consent and try again.');
        }
        $expiresAt = gmdate('Y-m-d H:i:s', time() + (int)$token['expires_in']);
        $publicId = 'outlook_' . bin2hex(random_bytes(12));
        $existing = $this->connection($userId);
        $refreshToken = (string)($token['refresh_token'] ?? '');
        if ($refreshToken === '') {
            throw new RuntimeException('Microsoft did not issue a refresh token. Re-consent with offline_access enabled.');
        }
        if ($existing !== null) {
            $update = $this->db->prepare('UPDATE module_outlook_calendar_connections SET tenant_id = :tenant, outlook_account_email = :email, access_token_encrypted = :access, refresh_token_encrypted = :refresh, expires_at = :expires, is_active = 1, last_synced_at = NULL, delta_token = NULL, delta_next_link = NULL, sync_window_start = NULL, sync_window_end = NULL, updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :workspace AND user_id = :user');
            $update->execute([
                ':tenant' => (string)$settings['tenant_id'], ':email' => $email,
                ':access' => $this->encryptSecret((string)$token['access_token']), ':refresh' => $this->encryptSecret($refreshToken),
                ':expires' => $expiresAt, ':workspace' => $this->workspaceId, ':user' => $userId,
            ]);
        } else {
            $insert = $this->db->prepare('INSERT INTO module_outlook_calendar_connections (public_id, workspace_id, user_id, tenant_id, outlook_account_email, selected_calendar_id, access_token_encrypted, refresh_token_encrypted, expires_at, sync_direction, is_active, created_at, updated_at) VALUES (:public_id, :workspace, :user, :tenant, :email, NULL, :access, :refresh, :expires, \'pull\', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
            $insert->execute([
                ':public_id' => $publicId, ':workspace' => $this->workspaceId, ':user' => $userId,
                ':tenant' => (string)$settings['tenant_id'], ':email' => $email,
                ':access' => $this->encryptSecret((string)$token['access_token']), ':refresh' => $this->encryptSecret($refreshToken), ':expires' => $expiresAt,
            ]);
        }

        return ['connected' => true, 'account_email' => $email, 'return_url' => 'index.php?route=module-outlook-calendar'];
    }

    public function rejectAuthorization(int $userId, string $state): void
    {
        if ($userId <= 0 || !preg_match('/^[A-Za-z0-9_-]{40,64}$/D', $state)) {
            return;
        }
        $stmt = $this->db->prepare('DELETE FROM module_outlook_calendar_oauth_states WHERE state_hash = :hash AND workspace_id = :workspace AND user_id = :user');
        $stmt->execute([':hash' => hash('sha256', $state), ':workspace' => $this->workspaceId, ':user' => $userId]);
    }

    /** Import up to three Graph pages per invocation and safely persist a resume cursor. */
    public function sync(int $userId): array
    {
        if ($userId <= 0) {
            throw new RuntimeException('An authenticated CRM user is required.');
        }
        $connection = $this->connection($userId);
        if ($connection === null || (int)$connection['is_active'] !== 1) {
            throw new RuntimeException('Connect a Microsoft account before syncing.');
        }
        $lockToken = bin2hex(random_bytes(16));
        $lockUntil = gmdate('Y-m-d H:i:s', time() + 120);
        $now = gmdate('Y-m-d H:i:s');
        $lock = $this->db->prepare('UPDATE module_outlook_calendar_connections SET sync_lock_token = :token, sync_lock_expires_at = :expires WHERE id = :id AND workspace_id = :workspace AND user_id = :user AND (sync_lock_expires_at IS NULL OR sync_lock_expires_at < :now)');
        $lock->execute([':token' => $lockToken, ':expires' => $lockUntil, ':id' => (int)$connection['id'], ':workspace' => $this->workspaceId, ':user' => $userId, ':now' => $now]);
        if ($lock->rowCount() !== 1) {
            throw new RuntimeException('A calendar sync is already running for this account.');
        }

        try {
            $connection = $this->connection($userId);
            if ($connection === null) {
                throw new RuntimeException('Outlook connection was removed.');
            }
            $accessToken = $this->ensureFreshToken($connection);
            $connection = $this->connection($userId) ?? $connection;
            if (empty($connection['sync_window_start']) || empty($connection['sync_window_end'])) {
                [$windowStart, $windowEnd] = $this->initialWindow();
                $saveWindow = $this->db->prepare('UPDATE module_outlook_calendar_connections SET sync_window_start = :start, sync_window_end = :end WHERE id = :id AND workspace_id = :workspace AND sync_lock_token = :token');
                $saveWindow->execute([':start' => $windowStart, ':end' => $windowEnd, ':id' => (int)$connection['id'], ':workspace' => $this->workspaceId, ':token' => $lockToken]);
                $connection['sync_window_start'] = $windowStart;
                $connection['sync_window_end'] = $windowEnd;
            }

            $url = trim((string)($connection['delta_next_link'] ?? ''));
            if ($url === '') {
                $url = trim((string)($connection['delta_token'] ?? ''));
            }
            if ($url === '') {
                $url = $this->initialDeltaUrl((string)$connection['sync_window_start'], (string)$connection['sync_window_end']);
            }
            $totals = ['imported' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'pages' => 0];
            $completed = false;
            for ($page = 0; $page < self::MAX_PAGES_PER_RUN; $page++) {
                try {
                    $result = $this->graphRequest('GET', $url, $accessToken, null, ['Prefer' => 'outlook.timezone="UTC", odata.maxpagesize=50']);
                } catch (RuntimeException $e) {
                    if ($e->getMessage() === 'OUTLOOK_DELTA_CURSOR_EXPIRED') {
                        $reset = $this->db->prepare('UPDATE module_outlook_calendar_connections SET delta_token = NULL, delta_next_link = NULL, sync_window_start = NULL, sync_window_end = NULL, last_synced_at = NULL WHERE id = :id AND workspace_id = :workspace AND sync_lock_token = :token');
                        $reset->execute([':id' => (int)$connection['id'], ':workspace' => $this->workspaceId, ':token' => $lockToken]);
                        return ['completed' => false, 'cursor_reset' => true, 'message' => 'Microsoft expired the delta cursor. Run sync again to rebuild the calendar snapshot.'] + $totals;
                    }
                    throw $e;
                }
                $events = $result['value'] ?? null;
                if (!is_array($events)) {
                    throw new RuntimeException('Microsoft Graph returned an invalid delta page.');
                }
                $next = isset($result['@odata.nextLink']) ? $this->validateGraphUrl((string)$result['@odata.nextLink']) : null;
                $delta = isset($result['@odata.deltaLink']) ? $this->validateGraphUrl((string)$result['@odata.deltaLink']) : null;
                if (($next === null) === ($delta === null)) {
                    throw new RuntimeException('Microsoft Graph did not return exactly one continuation or delta cursor.');
                }

                $this->db->beginTransaction();
                try {
                    $this->pendingCalendarDispatches = [];
                    foreach ($events as $event) {
                        if (!is_array($event)) {
                            $totals['skipped']++;
                            continue;
                        }
                        $change = $this->applyGraphEvent($connection, $event);
                        if (isset($totals[$change])) {
                            $totals[$change]++;
                        }
                    }
                    if ($next !== null) {
                        $save = $this->db->prepare('UPDATE module_outlook_calendar_connections SET delta_next_link = :next, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND workspace_id = :workspace AND sync_lock_token = :token');
                        $save->execute([':next' => $next, ':id' => (int)$connection['id'], ':workspace' => $this->workspaceId, ':token' => $lockToken]);
                    } else {
                        $save = $this->db->prepare('UPDATE module_outlook_calendar_connections SET delta_token = :delta, delta_next_link = NULL, last_synced_at = :synced, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND workspace_id = :workspace AND sync_lock_token = :token');
                        $save->execute([':delta' => $delta, ':synced' => gmdate('Y-m-d H:i:s'), ':id' => (int)$connection['id'], ':workspace' => $this->workspaceId, ':token' => $lockToken]);
                        $completed = true;
                    }
                    // MySQL may report zero changed rows for an identical cursor value;
                    // verify lease ownership explicitly instead of relying on rowCount().
                    $lease = $this->db->prepare('SELECT sync_lock_token FROM module_outlook_calendar_connections WHERE id = :id AND workspace_id = :workspace LIMIT 1');
                    $lease->execute([':id' => (int)$connection['id'], ':workspace' => $this->workspaceId]);
                    if (!hash_equals($lockToken, (string)$lease->fetchColumn())) {
                        throw new RuntimeException('The Outlook sync lease was lost before its cursor could be saved.');
                    }
                    $this->db->commit();
                    foreach ($this->pendingCalendarDispatches as $pending) {
                        try { $this->calendarEventAfterCommit?->__invoke($pending['event'], $pending['actor']); }
                        catch (\Throwable) { /* Calendar and outbox are committed; don't misreport sync. */ }
                    }
                    $this->pendingCalendarDispatches = [];
                } catch (\Throwable $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    $this->pendingCalendarDispatches = [];
                    throw $e;
                }

                $totals['pages']++;
                if ($completed) {
                    break;
                }
                $url = (string)$next;
            }

            return ['completed' => $completed, 'continuation_available' => !$completed, 'cursor_reset' => false] + $totals;
        } finally {
            $release = $this->db->prepare('UPDATE module_outlook_calendar_connections SET sync_lock_token = NULL, sync_lock_expires_at = NULL WHERE id = :id AND workspace_id = :workspace AND user_id = :user AND sync_lock_token = :token');
            $release->execute([':id' => (int)$connection['id'], ':workspace' => $this->workspaceId, ':user' => $userId, ':token' => $lockToken]);
        }
    }

    public function disconnect(int $userId): bool
    {
        $connection = $this->connection($userId);
        if ($connection === null) {
            return false;
        }
        if (!empty($connection['sync_lock_expires_at']) && strtotime((string)$connection['sync_lock_expires_at'] . ' UTC') > time()) {
            throw new RuntimeException('A calendar sync is running. Disconnect after it finishes.');
        }
        // Keep imported CRM events and mappings, but make the stored credentials unusable.
        $stmt = $this->db->prepare('UPDATE module_outlook_calendar_connections SET access_token_encrypted = :access, refresh_token_encrypted = :refresh, is_active = 0, delta_token = NULL, delta_next_link = NULL, sync_lock_token = NULL, sync_lock_expires_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND workspace_id = :workspace AND user_id = :user AND (sync_lock_expires_at IS NULL OR sync_lock_expires_at < :now)');
        $stmt->execute([
            ':access' => $this->encryptSecret('disconnected:' . bin2hex(random_bytes(32))),
            ':refresh' => $this->encryptSecret('disconnected:' . bin2hex(random_bytes(32))),
            ':id' => (int)$connection['id'], ':workspace' => $this->workspaceId, ':user' => $userId, ':now' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('A calendar sync is running. Disconnect after it finishes.');
        }
        return true;
    }

    /** Preserve pure mapper public contract for package-level integrations and tests. */
    public function mapGraphEventToCrm(array $graphEvent): array
    {
        $outlookId = trim((string)($graphEvent['id'] ?? ''));
        if ($outlookId === '' || strlen($outlookId) > 255) {
            throw new RuntimeException('Microsoft Graph event ID is invalid.');
        }
        $subject = $this->truncateUtf8(trim((string)($graphEvent['subject'] ?? 'Без названия')), 255);
        if ($subject === '') {
            $subject = 'Без названия';
        }
        $bodyContent = (string)($graphEvent['body']['content'] ?? '');
        if (strtolower((string)($graphEvent['body']['contentType'] ?? '')) === 'html') {
            $bodyContent = html_entity_decode(strip_tags($bodyContent), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $bodyContent = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $bodyContent) ?? '');
        if (strlen($bodyContent) > 60000) {
            $bodyContent = substr($bodyContent, 0, 60000);
            while ($bodyContent !== '' && !preg_match('//u', $bodyContent)) {
                $bodyContent = substr($bodyContent, 0, -1);
            }
        }
        $attendees = [];
        if (is_array($graphEvent['attendees'] ?? null)) {
            foreach (array_slice($graphEvent['attendees'], 0, 100) as $attendee) {
                if (!is_array($attendee)) {
                    continue;
                }
                $address = is_array($attendee['emailAddress'] ?? null) ? $attendee['emailAddress'] : [];
                $email = trim((string)($address['address'] ?? ''));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $attendees[] = [
                        'email' => $email,
                        'name' => $this->truncateUtf8(trim(strip_tags((string)($address['name'] ?? ''))), 255),
                        'status' => in_array(($attendee['status']['response'] ?? 'none'), ['none', 'organizer', 'tentativelyAccepted', 'accepted', 'declined', 'notResponded'], true) ? (string)($attendee['status']['response'] ?? 'none') : 'none',
                    ];
                }
            }
        }
        return [
            'outlook_id' => $outlookId,
            'title' => $subject,
            'description' => $bodyContent,
            'start_at' => $this->graphDateToUtc((string)($graphEvent['start']['dateTime'] ?? '')),
            'end_at' => $this->graphDateToUtc((string)($graphEvent['end']['dateTime'] ?? '')),
            'is_all_day' => (bool)($graphEvent['isAllDay'] ?? false),
            'is_cancelled' => (bool)($graphEvent['isCancelled'] ?? false),
            'etag' => $this->truncateUtf8((string)($graphEvent['@odata.etag'] ?? ''), 128),
            'timezone' => 'UTC',
            'attendees' => $attendees,
            'tags' => ['outlook_calendar'],
        ];
    }

    /** @return array<string,mixed>|null */
    private function settings(): ?array
    {
        $stmt = $this->db->prepare('SELECT workspace_id, tenant_id, client_id, client_secret_encrypted, redirect_uri FROM module_outlook_calendar_settings WHERE workspace_id = :workspace LIMIT 1');
        $stmt->execute([':workspace' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private function connection(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM module_outlook_calendar_connections WHERE workspace_id = :workspace AND user_id = :user LIMIT 1');
        $stmt->execute([':workspace' => $this->workspaceId, ':user' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function ensureFreshToken(array $connection): string
    {
        $expires = strtotime((string)$connection['expires_at'] . ' UTC');
        if ($expires !== false && $expires > time() + 120) {
            return $this->decryptSecret((string)$connection['access_token_encrypted']);
        }
        $settings = $this->settings();
        if ($settings === null) {
            throw new RuntimeException('Microsoft Entra app configuration is missing.');
        }
        $token = $this->tokenRequest((string)$connection['tenant_id'], [
            'client_id' => (string)$settings['client_id'],
            'client_secret' => $this->decryptSecret((string)$settings['client_secret_encrypted']),
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->decryptSecret((string)$connection['refresh_token_encrypted']),
            'scope' => self::OAUTH_SCOPES,
        ]);
        $refreshToken = (string)($token['refresh_token'] ?? '');
        if ($refreshToken === '') {
            $refreshToken = $this->decryptSecret((string)$connection['refresh_token_encrypted']);
        }
        $stmt = $this->db->prepare('UPDATE module_outlook_calendar_connections SET access_token_encrypted = :access, refresh_token_encrypted = :refresh, expires_at = :expires, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND workspace_id = :workspace AND user_id = :user');
        $stmt->execute([
            ':access' => $this->encryptSecret((string)$token['access_token']),
            ':refresh' => $this->encryptSecret($refreshToken),
            ':expires' => gmdate('Y-m-d H:i:s', time() + (int)$token['expires_in']),
            ':id' => (int)$connection['id'], ':workspace' => $this->workspaceId, ':user' => (int)$connection['user_id'],
        ]);
        return (string)$token['access_token'];
    }

    /** @return array<string,mixed> */
    private function tokenRequest(string $tenantId, array $parameters): array
    {
        if (!$this->isValidTenant($tenantId)) {
            throw new RuntimeException('Microsoft Entra tenant is invalid.');
        }
        $url = 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token';
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded'];
        $body = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        $response = $this->sendHttpRequest('POST', $url, $headers, $body);
        $decoded = $this->decodeJsonResponse($response);
        if ((int)$response['status'] < 200 || (int)$response['status'] >= 300) {
            throw new RuntimeException('Microsoft token exchange failed. Check Entra app credentials, consent, and redirect URI.');
        }
        $access = (string)($decoded['access_token'] ?? '');
        $expiresIn = (int)($decoded['expires_in'] ?? 0);
        if ($access === '' || strlen($access) > 16384 || $expiresIn < 60 || $expiresIn > 86400
            || preg_match('/[\r\n]/', $access)) {
            throw new RuntimeException('Microsoft returned an invalid OAuth token response.');
        }
        if (isset($decoded['refresh_token']) && (!is_string($decoded['refresh_token']) || strlen($decoded['refresh_token']) > 16384 || preg_match('/[\r\n]/', $decoded['refresh_token']))) {
            throw new RuntimeException('Microsoft returned an invalid refresh token.');
        }
        return $decoded;
    }

    /** @return array<string,mixed> */
    private function graphRequest(string $method, string $url, string $accessToken, ?array $payload = null, array $extraHeaders = []): array
    {
        $url = $this->validateGraphUrl($url);
        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $accessToken];
        foreach ($extraHeaders as $key => $value) {
            if (in_array($key, ['Prefer', 'Content-Type'], true)) {
                $headers[$key] = $value;
            }
        }
        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        $response = $this->sendHttpRequest($method, $url, $headers, $body);
        $decoded = $this->decodeJsonResponse($response);
        $status = (int)$response['status'];
        if ($status === 410) {
            throw new RuntimeException('OUTLOOK_DELTA_CURSOR_EXPIRED');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Microsoft Graph request failed with HTTP ' . ($status >= 400 && $status < 600 ? $status : 'error') . '.');
        }
        return $decoded;
    }

    /** @param array<string,string> $headers @return array{status:int,body:string} */
    private function sendHttpRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && (int)$parts['port'] !== 443)) {
            throw new RuntimeException('Microsoft request URL is not allowed.');
        }
        if ($this->httpTransport !== null) {
            return ($this->httpTransport)($method, $url, $headers, $body);
        }
        $headerLines = [];
        foreach ($headers as $name => $value) {
            if (preg_match('/^[A-Za-z0-9-]+$/D', $name) !== 1 || preg_match('/[\r\n]/', $value)) {
                throw new RuntimeException('Microsoft request headers are invalid.');
            }
            $headerLines[] = $name . ': ' . $value;
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => 8,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $raw = @file_get_contents($url, false, $context, 0, self::MAX_RESPONSE_BYTES + 1);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        foreach ($responseHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) {
                $status = (int)$matches[1];
            }
        }
        if (!is_string($raw) || strlen($raw) > self::MAX_RESPONSE_BYTES || $status === 0) {
            throw new RuntimeException('Microsoft request failed or returned an oversized response.');
        }
        return ['status' => $status, 'body' => $raw];
    }

    /** @param array{status:int,body:string} $response @return array<string,mixed> */
    private function decodeJsonResponse(array $response): array
    {
        $raw = (string)($response['body'] ?? '');
        if (strlen($raw) > self::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('Microsoft response exceeded the allowed size.');
        }
        try {
            $data = json_decode($raw, true, 48, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Microsoft returned an invalid response.');
        }
        if (!is_array($data)) {
            throw new RuntimeException('Microsoft returned an invalid response.');
        }
        return $data;
    }

    private function validateGraphUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string)($parts['host'] ?? '')) !== 'graph.microsoft.com'
            || (isset($parts['port']) && (int)$parts['port'] !== 443)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || !preg_match('#^/v1\.0/me(?:/(?:calendarView/delta|calendars/[^/]{1,1024}/calendarView/delta|\$metadata))?$#', (string)($parts['path'] ?? ''))
            || strlen($url) > 8192 || preg_match('/[\r\n]/', $url)) {
            throw new RuntimeException('Microsoft Graph returned a continuation URL outside the allowed calendar endpoint.');
        }
        return $url;
    }

    private function initialDeltaUrl(string $start, string $end): string
    {
        $startIso = (new DateTimeImmutable($start, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        $endIso = (new DateTimeImmutable($end, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        return self::GRAPH_BASE . '/me/calendarView/delta?' . http_build_query(['startDateTime' => $startIso, 'endDateTime' => $endIso], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{0:string,1:string} */
    private function initialWindow(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $start = $now->modify('-90 days')->setTime(0, 0, 0);
        $end = $now->modify('+365 days')->setTime(23, 59, 59);
        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    private function applyGraphEvent(array $connection, array $event): string
    {
        $removed = array_key_exists('@removed', $event) || (bool)($event['isCancelled'] ?? false);
        if ($removed) {
            $outlookId = trim((string)($event['id'] ?? ''));
            if ($outlookId === '' || strlen($outlookId) > 255) {
                return 'skipped';
            }
            $lookup = $this->db->prepare('SELECT id, crm_event_id FROM module_outlook_calendar_events_map WHERE workspace_id = :workspace AND connection_id = :connection AND outlook_event_id = :external_id LIMIT 1');
            $lookup->execute([':workspace' => $this->workspaceId, ':connection' => (int)$connection['id'], ':external_id' => $outlookId]);
            $existing = $lookup->fetch(PDO::FETCH_ASSOC);
            if (!is_array($existing)) {
                return 'skipped';
            }
            $deleteEvent = $this->db->prepare("DELETE FROM calendar_events WHERE public_id = :public_id AND organization_id = :workspace AND source_type = 'outlook_calendar' AND source_owner_user_id = :owner AND source_external_id = :external_id");
            $deleteEvent->execute([':public_id' => (string)$existing['crm_event_id'], ':workspace' => $this->workspaceId, ':owner' => (int)$connection['user_id'], ':external_id' => $outlookId]);
            $deleteMap = $this->db->prepare('DELETE FROM module_outlook_calendar_events_map WHERE id = :id AND workspace_id = :workspace AND connection_id = :connection');
            $deleteMap->execute([':id' => (int)$existing['id'], ':workspace' => $this->workspaceId, ':connection' => (int)$connection['id']]);
            return 'deleted';
        }
        $mapped = $this->mapGraphEventToCrm($event);
        $lookup = $this->db->prepare('SELECT id, crm_event_id FROM module_outlook_calendar_events_map WHERE workspace_id = :workspace AND connection_id = :connection AND outlook_event_id = :external_id LIMIT 1');
        $lookup->execute([':workspace' => $this->workspaceId, ':connection' => (int)$connection['id'], ':external_id' => $mapped['outlook_id']]);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!is_array($existing)) {
            $publicId = 'evt_outlook_' . bin2hex(random_bytes(12));
            $now = gmdate('Y-m-d H:i:s');
            if ($this->calendarEventCreator !== null) {
                $actor = ['id' => (int)$connection['user_id'], 'organization_id' => $this->workspaceId, 'is_root' => false];
                $createdEvent = ($this->calendarEventCreator)([
                    'title' => $mapped['title'], 'description' => $mapped['description'],
                    'starts_at' => $mapped['start_at'], 'ends_at' => $mapped['end_at'],
                ], $actor, ['source_type' => 'outlook_calendar', 'source_owner_user_id' => (int)$connection['user_id'], 'source_external_id' => $mapped['outlook_id']]);
                if (!is_array($createdEvent) || !is_string($createdEvent['public_id'] ?? null)) {
                    throw new RuntimeException($createdEvent === 'BOOKING_CONFLICT' ? 'An Outlook event overlaps a confirmed CRM appointment; sync was stopped and its cursor was not advanced.' : 'Calendar event creation failed; sync cursor was not advanced.');
                }
                $publicId = (string)$createdEvent['public_id'];
                $this->pendingCalendarDispatches[] = ['event' => $createdEvent, 'actor' => $actor];
            } else {
                $eventId = (new CalendarEventRepository($this->db))->create([
                    'public_id' => $publicId, 'title' => $mapped['title'], 'description' => $mapped['description'],
                    'starts_at' => $mapped['start_at'], 'ends_at' => $mapped['end_at'], 'owner_user_id' => (int)$connection['user_id'],
                    'project_id' => null, 'task_id' => null, 'organization_id' => $this->workspaceId,
                    'created_at' => $now, 'updated_at' => $now, 'source_type' => 'outlook_calendar',
                    'source_owner_user_id' => (int)$connection['user_id'], 'source_external_id' => $mapped['outlook_id'],
                ], [(int)$connection['user_id']]);
                if ($eventId < 1) throw new RuntimeException('An Outlook event overlaps a confirmed CRM appointment; sync was stopped and its cursor was not advanced.');
            }
            $insertMap = $this->db->prepare('INSERT INTO module_outlook_calendar_events_map (workspace_id, connection_id, crm_event_id, outlook_event_id, last_etag, last_sync_direction, created_at, updated_at) VALUES (:workspace, :connection, :crm_event_id, :external_id, :etag, \'pull\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
            $insertMap->execute([
                ':workspace' => $this->workspaceId, ':connection' => (int)$connection['id'], ':crm_event_id' => $publicId,
                ':external_id' => $mapped['outlook_id'], ':etag' => $mapped['etag'],
            ]);
            return 'imported';
        }

        $eventExists = $this->db->prepare("SELECT id FROM calendar_events WHERE public_id = :public_id AND organization_id = :workspace AND source_type = 'outlook_calendar' AND source_owner_user_id = :owner AND source_external_id = :external_id LIMIT 1");
        $eventExists->execute([':public_id' => (string)$existing['crm_event_id'], ':workspace' => $this->workspaceId, ':owner' => (int)$connection['user_id'], ':external_id' => $mapped['outlook_id']]);
        if ($eventExists->fetchColumn() === false) {
            $deleteMap = $this->db->prepare('DELETE FROM module_outlook_calendar_events_map WHERE id = :id AND workspace_id = :workspace AND connection_id = :connection');
            $deleteMap->execute([':id' => (int)$existing['id'], ':workspace' => $this->workspaceId, ':connection' => (int)$connection['id']]);
            return $this->applyGraphEvent($connection, $event);
        }
        $updateResult = (new CalendarEventRepository($this->db))->updateWithBookingGuard((string)$existing['crm_event_id'], (int)$connection['user_id'], false, [
            'title' => $mapped['title'], 'description' => $mapped['description'], 'starts_at' => $mapped['start_at'],
            'ends_at' => $mapped['end_at'], 'updated_at' => gmdate('Y-m-d H:i:s'),
        ], $this->workspaceId);
        if ($updateResult === 'booking_conflict') throw new RuntimeException('An Outlook event overlaps a confirmed CRM appointment; sync was stopped and its cursor was not advanced.');
        $updateMap = $this->db->prepare('UPDATE module_outlook_calendar_events_map SET last_etag = :etag, last_sync_direction = \'pull\', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND workspace_id = :workspace AND connection_id = :connection');
        $updateMap->execute([':etag' => $mapped['etag'], ':id' => (int)$existing['id'], ':workspace' => $this->workspaceId, ':connection' => (int)$connection['id']]);
        return 'updated';
    }

    private function graphDateToUtc(string $dateTime): string
    {
        if ($dateTime === '' || strlen($dateTime) > 80) {
            throw new RuntimeException('Microsoft Graph event has an invalid start or end time.');
        }
        try {
            // Delta requests explicitly ask Graph to return UTC, so naive values are UTC.
            return (new DateTimeImmutable($dateTime, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw new RuntimeException('Microsoft Graph event has an invalid start or end time.');
        }
    }

    private function encryptSecret(string $plaintext): string
    {
        $key = $this->credentialKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'crm.module.crm.outlook-calendar.credentials', 16);
        if (!is_string($cipher) || strlen($tag) !== 16) {
            throw new RuntimeException('Outlook credential encryption is unavailable.');
        }
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    private function decryptSecret(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'v1:')) {
            throw new RuntimeException('Encrypted Outlook credential has an unsupported format.');
        }
        $payload = base64_decode(substr($encrypted, 3), true);
        if (!is_string($payload) || strlen($payload) < 29) {
            throw new RuntimeException('Encrypted Outlook credential is invalid.');
        }
        $plain = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', $this->credentialKey(), OPENSSL_RAW_DATA, substr($payload, 0, 12), substr($payload, 12, 16), 'crm.module.crm.outlook-calendar.credentials');
        if (!is_string($plain) || $plain === '') {
            throw new RuntimeException('Outlook credential could not be decrypted. Check APP_KEY.');
        }
        return $plain;
    }

    private function credentialKey(): string
    {
        $configured = trim((string)(getenv('APP_KEY') ?: ($_SERVER['APP_KEY'] ?? $_ENV['APP_KEY'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/i', $configured) || !function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('APP_KEY must be configured as a 32-byte hexadecimal key and OpenSSL must be available.');
        }
        $master = hex2bin($configured);
        if (!is_string($master) || strlen($master) !== 32) {
            throw new RuntimeException('APP_KEY is invalid.');
        }
        return hash_hkdf('sha256', $master, 32, 'crm.module.crm.outlook-calendar.credentials', 'TropaTT CRM');
    }

    private function isValidTenant(string $tenantId): bool
    {
        return in_array(strtolower($tenantId), ['common', 'organizations', 'consumers'], true)
            || preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $tenantId) === 1;
    }

    private function isValidRedirectUri(string $uri): bool
    {
        $parts = parse_url($uri);
        return is_array($parts)
            && strtolower((string)($parts['scheme'] ?? '')) === 'https'
            && isset($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass'])
            && (!isset($parts['port']) || (int)$parts['port'] === 443)
            && str_ends_with((string)($parts['path'] ?? ''), '/api/v1/modules/outlook-calendar/oauth/callback')
            && !isset($parts['query']) && !isset($parts['fragment'])
            && strlen($uri) <= 2048;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function truncateUtf8(string $value, int $characters): string
    {
        if (preg_match('/^.{0,' . $characters . '}/us', $value, $matches) === 1) {
            return $matches[0];
        }
        return substr($value, 0, $characters);
    }
}
