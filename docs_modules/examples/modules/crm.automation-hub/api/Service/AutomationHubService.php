<?php
declare(strict_types=1);

namespace Module\Crm\AutomationHub\Service;

use PDO;
use RuntimeException;
use Closure;
use Api\System\Library\Service\WebhookService;

/** Workspace-scoped n8n/Make webhook recipes and bounded delivery auditing. */
final class AutomationHubService
{
    private const MAX_RESPONSE_BYTES = 65536;
    private ?Closure $webhookTransport;

    /** @var list<string> */
    private const SUPPORTED_EVENTS = [
        'task.created', 'task.updated', 'task.status_changed',
        'project.created', 'project.updated', 'comment.added', 'intake.created',
    ];

    public function __construct(
        private PDO $db,
        ?callable $webhookTransport = null,
        private ?WebhookService $webhookService = null
    ) {
        $this->webhookTransport = $webhookTransport === null ? null : Closure::fromCallable($webhookTransport);
    }

    /** @return list<array{code:string,label:string}> */
    public static function supportedEvents(): array
    {
        return [
            ['code' => 'task.created', 'label' => 'Новая задача'],
            ['code' => 'task.updated', 'label' => 'Изменение задачи'],
            ['code' => 'task.status_changed', 'label' => 'Изменение статуса задачи'],
            ['code' => 'project.created', 'label' => 'Новый проект'],
            ['code' => 'project.updated', 'label' => 'Изменение проекта'],
            ['code' => 'comment.added', 'label' => 'Новый комментарий'],
            ['code' => 'intake.created', 'label' => 'Новое обращение во входящих'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listRecipes(int $organizationId): array
    {
        $this->requireOrganization($organizationId);
        $stmt = $this->db->prepare(
            "SELECT public_id, platform, recipe_key, title, webhook_endpoint_url, is_active, events_json, webhook_public_id, created_at, updated_at
             FROM crm_automation_recipes
             WHERE organization_id = :org_id
             ORDER BY id ASC"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($recipes as &$recipe) {
            // Endpoint paths can contain provider-issued bearer-like identifiers.
            // Expose only the host in the general settings list, never the path.
            $parts = parse_url((string)($recipe['webhook_endpoint_url'] ?? ''));
            $recipe['webhook_endpoint_host'] = is_array($parts) ? (string)($parts['host'] ?? '') : '';
            $decodedEvents = json_decode((string)($recipe['events_json'] ?? ''), true);
            $recipe['events'] = is_array($decodedEvents) ? array_values(array_intersect(self::SUPPORTED_EVENTS, $decodedEvents)) : [];
            $recipe['event_subscription_ready'] = (string)($recipe['webhook_public_id'] ?? '') !== '';
            unset($recipe['webhook_endpoint_url'], $recipe['events_json'], $recipe['webhook_public_id']);
        }
        unset($recipe);
        return $recipes;
    }

    /** @param array<string, mixed> $fieldMappings */
    public function saveRecipe(
        int $organizationId,
        string $platform,
        string $recipeKey,
        string $title,
        string $webhookEndpointUrl,
        string $secretToken,
        array $fieldMappings = [],
        array $events = [],
        array $actor = []
    ): string {
        $this->requireOrganization($organizationId);
        $platform = strtolower(trim($platform));
        if (!in_array($platform, ['n8n', 'make'], true)) {
            throw new RuntimeException('Unsupported automation platform.');
        }
        // Keep validation aligned with the installed schema: title is VARCHAR(255),
        // endpoint is VARCHAR(1024), and mappings use MySQL TEXT (65,535 bytes).
        if (trim($recipeKey) === '' || self::utf8Length(trim($recipeKey)) > 64 || trim($title) === '' || self::utf8Length(trim($title)) > 255) {
            throw new RuntimeException('Recipe key or title is invalid.');
        }
        if (strlen($webhookEndpointUrl) > 2048 || self::utf8Length($webhookEndpointUrl) > 1024) {
            throw new RuntimeException('Webhook endpoint exceeds the configured size limit.');
        }
        if ($secretToken === '' || strlen($secretToken) > 4096) {
            throw new RuntimeException('A non-empty signing secret is required.');
        }
        $normalizedEvents = [];
        foreach ($events as $event) {
            if (!is_string($event) || strlen($event) > 64) {
                throw new RuntimeException('CRM event selection is invalid.');
            }
            $event = trim($event);
            if ($event !== '') {
                $normalizedEvents[] = $event;
            }
        }
        $events = array_values(array_unique($normalizedEvents));
        sort($events);
        if (count($events) > count(self::SUPPORTED_EVENTS) || ($events === [] && $this->webhookService !== null) || array_diff($events, self::SUPPORTED_EVENTS) !== []) {
            throw new RuntimeException('Select at least one supported CRM event.');
        }
        if ($events !== [] && ($this->webhookService === null || !in_array('webhook.manage', (array)($actor['permission_codes'] ?? []), true) && empty($actor['is_root']))) {
            throw new RuntimeException('Webhook management permission is required to subscribe to CRM events.');
        }

        // Resolve and validate before persisting. Dispatch repeats this check and pins
        // the resulting public IPs to the cURL connection to prevent DNS rebinding.
        self::resolvePublicWebhookEndpoint($webhookEndpointUrl);
        $encryptedSecret = self::encryptSecret($secretToken);
        $mappingJson = json_encode($fieldMappings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($mappingJson) > 65535) {
            throw new RuntimeException('Field mappings exceed the configured size limit.');
        }
        $pubId = 'rcp_' . bin2hex(random_bytes(10));
        $now = date('Y-m-d H:i:s');
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            // Reuse the core webhook service for endpoint validation, encryption,
            // workspace scoping and HMAC format. The service shares this PDO, so
            // the recipe link and native outbox subscription commit together.
            $webhookPublicId = null;
            if ($this->webhookService !== null) {
                $subscription = $this->webhookService->createSubscription([
                    'title' => 'Automation Hub · ' . trim($title),
                    'endpoint' => trim($webhookEndpointUrl),
                    'secret' => $secretToken,
                    'events' => $events,
                    'is_active' => 1,
                ], $actor);
                if (empty($subscription['ok']) || empty($subscription['webhook']['public_id'])) {
                    throw new RuntimeException('Core webhook subscription could not be created.');
                }
                $webhookPublicId = (string)$subscription['webhook']['public_id'];
            }
            $eventsJson = json_encode($events, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $stmt = $this->db->prepare(
                "INSERT INTO crm_automation_recipes (public_id, organization_id, platform, recipe_key, title, webhook_endpoint_url, secret_token_encrypted, field_mappings_json, is_active, webhook_public_id, events_json, created_at, updated_at)
                 VALUES (:pub_id, :org_id, :platform, :key, :title, :url, :sec, :map, 1, :webhook_public_id, :events, :now, :now)"
            );
            $stmt->execute([
                ':pub_id' => $pubId,
                ':org_id' => $organizationId,
                ':platform' => $platform,
                ':key' => trim($recipeKey),
                ':title' => trim($title),
                ':url' => trim($webhookEndpointUrl),
                ':sec' => $encryptedSecret,
                ':map' => $mappingJson,
                ':webhook_public_id' => $webhookPublicId,
                ':events' => $eventsJson,
                ':now' => $now,
            ]);
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $pubId;
    }

    /** Pause or resume outbound deliveries without deleting the workspace connection. */
    public function setRecipeActive(int $organizationId, string $recipePublicId, bool $active, array $actor = []): bool
    {
        $this->requireOrganization($organizationId);
        if (!preg_match('/^rcp_[a-f0-9]{20}$/D', $recipePublicId)) {
            throw new RuntimeException('Automation recipe not found or inactive.');
        }
        $currentStmt = $this->db->prepare(
            'SELECT is_active, webhook_public_id, events_json, title, webhook_endpoint_url, secret_token_encrypted FROM crm_automation_recipes WHERE public_id = :public_id AND organization_id = :organization_id LIMIT 1'
        );
        $currentStmt->execute([':public_id' => $recipePublicId, ':organization_id' => $organizationId]);
        $currentRecipe = $currentStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($currentRecipe)) {
            throw new RuntimeException('Automation recipe not found or inactive.');
        }
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $stmt = $this->db->prepare(
                'UPDATE crm_automation_recipes SET is_active = :active, updated_at = :updated_at
                 WHERE public_id = :public_id AND organization_id = :organization_id'
            );
            $stmt->execute([
                ':active' => $active ? 1 : 0,
                ':updated_at' => date('Y-m-d H:i:s'),
                ':public_id' => $recipePublicId,
                ':organization_id' => $organizationId,
            ]);
            $webhookPublicId = trim((string)($currentRecipe['webhook_public_id'] ?? ''));
            if ($webhookPublicId === '' && $this->webhookService !== null) {
                // Upgrade recipes created before event subscriptions existed.
                // The previous encrypted module secret is reused without exposing
                // it; default to task.created so activating the old connection
                // actually starts delivering events instead of silently doing nothing.
                $events = json_decode((string)($currentRecipe['events_json'] ?? ''), true);
                if (!is_array($events) || $events === []) {
                    $events = ['task.created'];
                }
                $events = array_values(array_unique(array_filter(array_map(static fn($event): string => trim((string)$event), $events))));
                if (array_diff($events, self::SUPPORTED_EVENTS) !== []) {
                    throw new RuntimeException('Legacy automation event list contains unsupported events; recreate the subscription.');
                }
                $secret = self::decryptSecret((string)($currentRecipe['secret_token_encrypted'] ?? ''));
                if ($secret === '') {
                    throw new RuntimeException('Legacy automation secret cannot be restored safely; recreate the subscription.');
                }
                if ($actor === [] || !in_array('webhook.manage', (array)($actor['permission_codes'] ?? []), true) && empty($actor['is_root'])) {
                    throw new RuntimeException('Webhook management permission is required to restore the event subscription.');
                }
                $subscription = $this->webhookService->createSubscription([
                    'title' => 'Automation Hub · ' . (string)$currentRecipe['title'],
                    'endpoint' => (string)$currentRecipe['webhook_endpoint_url'],
                    'secret' => $secret,
                    'events' => $events,
                    'is_active' => $active ? 1 : 0,
                ], $actor);
                if (empty($subscription['ok']) || empty($subscription['webhook']['public_id'])) {
                    throw new RuntimeException('Legacy event subscription could not be restored safely.');
                }
                $webhookPublicId = (string)$subscription['webhook']['public_id'];
                $map = $this->db->prepare('UPDATE crm_automation_recipes SET webhook_public_id = :webhook_id, events_json = :events WHERE public_id = :public_id AND organization_id = :organization_id');
                $map->execute([
                    ':webhook_id' => $webhookPublicId,
                    ':events' => json_encode($events, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ':public_id' => $recipePublicId,
                    ':organization_id' => $organizationId,
                ]);
            }
            if ($webhookPublicId !== '') {
                $sync = $this->db->prepare(
                    'UPDATE webhook_subscriptions SET is_active = :active, updated_at = :updated_at
                     WHERE public_id = :public_id AND organization_id = :organization_id'
                );
                $sync->execute([
                    ':active' => $active ? 1 : 0,
                    ':updated_at' => date('Y-m-d H:i:s'),
                    ':public_id' => $webhookPublicId,
                    ':organization_id' => $organizationId,
                ]);
                if ($sync->rowCount() === 0) {
                    $exists = $this->db->prepare('SELECT 1 FROM webhook_subscriptions WHERE public_id = :public_id AND organization_id = :organization_id LIMIT 1');
                    $exists->execute([':public_id' => $webhookPublicId, ':organization_id' => $organizationId]);
                    if (!$exists->fetchColumn()) {
                        throw new RuntimeException('Linked workspace webhook subscription is missing.');
                    }
                }
            }
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
        return true;
    }

    /**
     * Send a signed test/domain event. All identifiers and writes are workspace-scoped.
     * @param array<string, mixed> $payload
     * @return array{ok: bool, http_code: int, correlation_id: string, delivery_id: string}
     */
    public function dispatch(
        int $organizationId,
        string $recipePublicId,
        string $eventType,
        array $payload,
        ?string $correlationId = null
    ): array {
        $this->requireOrganization($organizationId);
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/D', $eventType)) {
            throw new RuntimeException('Event type is invalid.');
        }
        $stmt = $this->db->prepare(
            "SELECT id, organization_id, webhook_endpoint_url, secret_token_encrypted, is_active
             FROM crm_automation_recipes
             WHERE public_id = :pub_id AND organization_id = :org_id
             LIMIT 1"
        );
        $stmt->execute([':pub_id' => $recipePublicId, ':org_id' => $organizationId]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$recipe || empty($recipe['is_active'])) {
            throw new RuntimeException('Automation recipe not found or inactive.');
        }

        $corrId = $correlationId ?? ('corr_' . bin2hex(random_bytes(12)));
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/D', $corrId)) {
            throw new RuntimeException('Correlation ID is invalid.');
        }
        $dupCheck = $this->db->prepare(
            "SELECT public_id, response_code, status FROM crm_automation_delivery_logs
             WHERE recipe_id = :rid AND organization_id = :org_id AND correlation_id = :cid ORDER BY id DESC LIMIT 1"
        );
        $dupCheck->execute([':rid' => $recipe['id'], ':org_id' => $organizationId, ':cid' => $corrId]);
        $existingDelivery = $dupCheck->fetch(PDO::FETCH_ASSOC);
        if ($existingDelivery) {
            $wasDelivered = (string)($existingDelivery['status'] ?? '') === 'delivered';
            return [
                'ok' => $wasDelivered,
                'http_code' => (int)($existingDelivery['response_code'] ?? 0),
                'correlation_id' => $corrId,
                'delivery_id' => (string)$existingDelivery['public_id'],
                'status' => (string)($existingDelivery['status'] ?? 'unknown'),
            ];
        }

        $secret = self::decryptSecret((string)($recipe['secret_token_encrypted'] ?? ''));
        $bodyJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($bodyJson) > 262144) {
            throw new RuntimeException('Webhook payload exceeds the configured limit.');
        }
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $bodyJson, $secret);
        $endpoint = self::resolvePublicWebhookEndpoint((string)$recipe['webhook_endpoint_url']);
        $headers = [
            'Content-Type: application/json',
            'X-Signature-SHA256: ' . $signature,
            'X-Signature-Timestamp: ' . $timestamp,
            'X-Correlation-ID: ' . $corrId,
            'X-Event-Type: ' . $eventType,
        ];

        // Atomically reserve the correlation ID before the network request. The
        // unique claim index prevents two PHP requests on shared hosting from
        // passing the read-before-send check simultaneously and sending twice.
        $deliveryId = 'del_' . bin2hex(random_bytes(10));
        $now = date('Y-m-d H:i:s');
        try {
            $claim = $this->db->prepare(
                "INSERT INTO crm_automation_delivery_claims (organization_id, recipe_id, correlation_id, delivery_public_id, status, created_at, updated_at)
                 VALUES (:org_id, :rid, :cid, :pub_id, 'sending', :now, :now)"
            );
            $claim->execute([
                ':org_id' => $organizationId,
                ':rid' => (int)$recipe['id'],
                ':cid' => $corrId,
                ':pub_id' => $deliveryId,
                ':now' => $now,
            ]);
        } catch (\PDOException $exception) {
            $claimLookup = $this->db->prepare(
                'SELECT delivery_public_id, response_code, status FROM crm_automation_delivery_claims WHERE organization_id = :org_id AND recipe_id = :rid AND correlation_id = :cid LIMIT 1'
            );
            $claimLookup->execute([':org_id' => $organizationId, ':rid' => (int)$recipe['id'], ':cid' => $corrId]);
            $claimed = $claimLookup->fetch(PDO::FETCH_ASSOC);
            if (!is_array($claimed)) {
                throw $exception;
            }
            return [
                'ok' => (string)$claimed['status'] === 'delivered',
                'http_code' => (int)($claimed['response_code'] ?? 0),
                'correlation_id' => $corrId,
                'delivery_id' => (string)$claimed['delivery_public_id'],
                'status' => (string)($claimed['status'] ?? 'sending'),
            ];
        }

        try {
            $response = $this->postWebhook($endpoint, $bodyJson, $headers);
        } catch (\Throwable $exception) {
            $this->updateDeliveryClaim($organizationId, (int)$recipe['id'], $corrId, 'uncertain', null);
            throw new RuntimeException('Webhook delivery outcome is uncertain; the same correlation ID will not be sent again.', 0, $exception);
        }

        $success = $response['error'] === null && $response['http_code'] >= 200 && $response['http_code'] < 300;
        // Once the request may have reached the remote service, a timeout, 5xx,
        // oversized response, or local persistence failure is ambiguous. Never
        // report that as a safe-to-retry failure: the receiver may have acted.
        $uncertain = !$success && ($response['error'] !== null || $response['http_code'] >= 500 || $response['http_code'] === 0);
        $status = $success ? 'delivered' : ($uncertain ? 'uncertain' : 'failed');
        try {
            $this->db->beginTransaction();
            $logStmt = $this->db->prepare(
                "INSERT INTO crm_automation_delivery_logs (public_id, organization_id, recipe_id, correlation_id, event_type, payload_json, response_code, status, error_message, created_at)
                 VALUES (:pub_id, :org_id, :rid, :cid, :event, NULL, :code, :status, :err, :now)"
            );
            $logStmt->execute([
                ':pub_id' => $deliveryId,
                ':org_id' => $organizationId,
                ':rid' => $recipe['id'],
                ':cid' => $corrId,
                ':event' => $eventType,
                ':code' => $response['http_code'] ?: null,
                ':status' => $status,
                ':err' => $response['error'] ?? ($success ? null : ($uncertain ? 'REMOTE_OUTCOME_UNCERTAIN' : 'REMOTE_HTTP_ERROR')),
                ':now' => $now,
            ]);
            $this->updateDeliveryClaim($organizationId, (int)$recipe['id'], $corrId, $status, $response['http_code'] ?: null);
            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // The remote request has already completed. Preserve the idempotency
            // claim if possible, so the same event cannot be sent again blindly.
            try {
                $this->updateDeliveryClaim($organizationId, (int)$recipe['id'], $corrId, 'uncertain', $response['http_code'] ?: null);
            } catch (\Throwable) {
                // The claim may remain `sending`; no automatic resend is allowed.
            }
            throw new RuntimeException('Webhook outcome could not be recorded safely; inspect the receiver before retrying.', 0, $exception);
        }

        return ['ok' => $success, 'http_code' => $response['http_code'], 'correlation_id' => $corrId, 'delivery_id' => $deliveryId, 'status' => $status];
    }

    /**
     * @param callable(string):array<int,string>|null $resolver Test seam; production uses DNS.
     * @return array{url:string,host:string,port:int,addresses:array<int,string>}
     */
    public static function resolvePublicWebhookEndpoint(string $url, ?callable $resolver = null): array
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\\\\]/', $url)) {
            throw new RuntimeException('Webhook endpoint URL is invalid.');
        }
        if (self::utf8Length($url) > 1024) {
            throw new RuntimeException('Webhook endpoint exceeds the configured character limit.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || (int)($parts['port'] ?? 443) !== 443) {
            throw new RuntimeException('Webhook endpoint must be a public HTTPS URL on port 443 without credentials, query, or fragment.');
        }
        $host = strtolower(rtrim((string)$parts['host'], '.'));
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }
        if ($host === '' || strlen($host) > 253) {
            throw new RuntimeException('Webhook endpoint host is invalid.');
        }

        $addresses = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } else {
            if (strpos($host, '.') === false || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host)) {
                throw new RuntimeException('Webhook endpoint host is invalid.');
            }
            if ($resolver !== null) {
                $addresses = $resolver($host);
            } else {
                $records = @dns_get_record($host, DNS_A | DNS_AAAA);
                if (is_array($records)) {
                    foreach ($records as $record) {
                        if (isset($record['ip'])) {
                            $addresses[] = (string)$record['ip'];
                        }
                        if (isset($record['ipv6'])) {
                            $addresses[] = (string)$record['ipv6'];
                        }
                    }
                }
            }
        }

        $addresses = array_values(array_unique(array_filter(array_map('trim', $addresses), static fn(string $ip): bool => $ip !== '')));
        if ($addresses === []) {
            throw new RuntimeException('Webhook endpoint host did not resolve to a public address.');
        }
        if (count($addresses) > 32) {
            throw new RuntimeException('Webhook endpoint returned too many address records.');
        }
        foreach ($addresses as $ip) {
            if (!self::isPublicRoutableIp($ip)) {
                throw new RuntimeException('Webhook endpoint resolves to a non-public address.');
            }
        }

        $path = (string)($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        return ['url' => 'https://' . (str_contains($host, ':') ? '[' . $host . ']' : $host) . $path, 'host' => $host, 'port' => 443, 'addresses' => $addresses];
    }

    private static function encryptSecret(string $secret): string
    {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('Secure automation secret storage requires OpenSSL.');
        }
        $key = self::encryptionKey();
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'crm.automation-hub.v1', 16);
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('Automation secret could not be encrypted.');
        }
        return 'v1:' . base64_encode($nonce . $tag . $ciphertext);
    }

    private static function decryptSecret(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'v1:') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('Automation signing secret must be re-entered using secure storage.');
        }
        $packed = base64_decode(substr($encrypted, 3), true);
        if (!is_string($packed) || strlen($packed) < 28) {
            throw new RuntimeException('Stored automation signing secret is invalid.');
        }
        $plain = openssl_decrypt(substr($packed, 28), 'aes-256-gcm', self::encryptionKey(), OPENSSL_RAW_DATA, substr($packed, 0, 12), substr($packed, 12, 16), 'crm.automation-hub.v1');
        if (!is_string($plain) || $plain === '') {
            throw new RuntimeException('Stored automation signing secret could not be decrypted.');
        }
        return $plain;
    }

    private static function encryptionKey(): string
    {
        $key = (string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? ''));
        if (strlen($key) < 32) {
            throw new RuntimeException('APP_KEY must contain at least 32 characters to secure automation credentials.');
        }
        return hash('sha256', $key, true);
    }

    private static function isPublicRoutableIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        $blockedV4 = [
            '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
            '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
            '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
            '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        ];
        $blockedV6 = ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'];
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16) {
            // Only global-unicast 2000::/3 is eligible for a webhook destination.
            if (!self::ipInCidr($packed, '2000::/3')) {
                return false;
            }
            foreach ($blockedV6 as $cidr) {
                if (self::ipInCidr($packed, $cidr)) {
                    return false;
                }
            }
            return true;
        }

        foreach ($blockedV4 as $cidr) {
            if (self::ipInCidr($packed, $cidr)) {
                return false;
            }
        }
        return true;
    }

    private static function ipInCidr(string $packedIp, string $cidr): bool
    {
        [$network, $prefixRaw] = explode('/', $cidr, 2);
        $packedNetwork = @inet_pton($network);
        $prefix = (int)$prefixRaw;
        if ($packedNetwork === false || strlen($packedNetwork) !== strlen($packedIp)) {
            return false;
        }
        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;
        if ($wholeBytes > 0 && substr($packedIp, 0, $wholeBytes) !== substr($packedNetwork, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($packedIp[$wholeBytes]) & $mask) === (ord($packedNetwork[$wholeBytes]) & $mask);
    }

    /** @param array{url:string,host:string,port:int,addresses:array<int,string>} $endpoint
     *  @param string[] $headers
     *  @return array{http_code:int,error:?string}
     */
    private function postWebhook(array $endpoint, string $body, array $headers): array
    {
        if ($this->webhookTransport !== null) {
            $response = ($this->webhookTransport)($endpoint, $body, $headers);
            if (!is_array($response) || !isset($response['http_code']) || !is_int($response['http_code'])
                || !array_key_exists('error', $response) || ($response['error'] !== null && !is_string($response['error']))) {
                throw new RuntimeException('Webhook transport returned an invalid result.');
            }
            return ['http_code' => $response['http_code'], 'error' => $response['error']];
        }
        if (!function_exists('curl_init') || !defined('CURLOPT_RESOLVE')) {
            throw new RuntimeException('Secure webhook delivery requires the PHP cURL extension with DNS pinning support.');
        }
        $curlOptions = [];
        // A literal IPv6 address is already the destination and cannot be
        // represented as the hostname part of CURLOPT_RESOLVE's host:port:ip
        // tuple. DNS names still get pinned to the validated public answers.
        if (!filter_var($endpoint['host'], FILTER_VALIDATE_IP)) {
            $resolve = [];
            foreach ($endpoint['addresses'] as $ip) {
                $resolve[] = $endpoint['host'] . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip);
            }
            $curlOptions[CURLOPT_RESOLVE] = $resolve;
        }
        $responseBytes = 0;
        $ch = curl_init($endpoint['url']);
        if ($ch === false) {
            throw new RuntimeException('Webhook request could not be initialized.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_PROXY => '',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBytes): int {
                $responseBytes += strlen($chunk);
                return $responseBytes <= self::MAX_RESPONSE_BYTES ? strlen($chunk) : 0;
            },
        ] + $curlOptions);
        $ok = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch);
        curl_close($ch);

        // Do not store arbitrary remote response text or cURL diagnostics; both can
        // contain PII, query secrets, internal addresses, or reflected credentials.
        $error = null;
        if ($responseBytes > self::MAX_RESPONSE_BYTES) {
            $error = 'REMOTE_RESPONSE_TOO_LARGE';
        } elseif ($ok === false) {
            $error = $curlError === CURLE_OPERATION_TIMEDOUT ? 'REMOTE_TIMEOUT' : 'REMOTE_REQUEST_FAILED';
        }
        return ['http_code' => $httpCode, 'error' => $error];
    }

    private static function utf8Length(string $value): int
    {
        $length = preg_match_all('/./us', $value);
        if ($length === false) {
            throw new RuntimeException('Value must be valid UTF-8.');
        }
        return $length;
    }

    private function updateDeliveryClaim(int $organizationId, int $recipeId, string $correlationId, string $status, ?int $responseCode): void
    {
        $stmt = $this->db->prepare(
            'UPDATE crm_automation_delivery_claims SET status = :status, response_code = :response_code, updated_at = :now WHERE organization_id = :org_id AND recipe_id = :rid AND correlation_id = :cid'
        );
        $stmt->execute([
            ':status' => $status,
            ':response_code' => $responseCode,
            ':now' => date('Y-m-d H:i:s'),
            ':org_id' => $organizationId,
            ':rid' => $recipeId,
            ':cid' => $correlationId,
        ]);
    }

    private function requireOrganization(int $organizationId): void
    {
        if ($organizationId < 1) {
            throw new RuntimeException('Active workspace is required.');
        }
    }
}
