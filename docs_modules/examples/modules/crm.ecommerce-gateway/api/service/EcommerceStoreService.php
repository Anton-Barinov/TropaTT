<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Api\System\Library\Security\UrlSafetyValidator;
use Module\Crm\EcommerceGateway\Repository\StoreRepository;

/**
 * Store (CMS) lifecycle: validation, API key/secret management, rotation and
 * the settings payload. Controllers stay thin and delegate here.
 */
final class EcommerceStoreService
{
    public const CMS_TYPES = ['opencart', 'woocommerce', 'bitrix', 'insales', 'custom'];
    public const STATUSES = ['active', 'paused', 'disabled'];
    public const INGEST_TYPES = ['order', 'quick_order', 'callback', 'feedback', 'form'];
    public const ON_DUPLICATE_MODES = ['merge', 'reject', 'create'];

    public const DEFAULT_RATE_LIMIT = 120;
    public const DEFAULT_RETENTION_DAYS = 90;
    public const ROTATION_GRACE_SECONDS = 3600;

    /** @var list<string> */
    public const SETTING_KEYS = [
        'default_project_id',
        'default_assignee_id',
        'default_priority_code',
        'create_task_for',
        'on_duplicate',
        'allow_inline_files',
        'antispam_enabled',
        'retention_days',
        'tags',
        'routing_matrix',
        'field_mappings',
    ];

    /**
     * @param array<string,mixed> $config module config defaults
     */
    public function __construct(
        private readonly StoreRepository $repository,
        private readonly array $config = [],
    ) {
    }

    // ── Public API ─────────────────────────────────────────────────────

    /**
     * @return list<array<string,mixed>>
     */
    public function listStores(?int $organizationId = null): array
    {
        $stores = $this->repository->listStores(100, false, $organizationId);

        return array_map(fn(array $store): array => $this->presentSingleStore($store), $stores);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getStore(string $publicId, ?int $organizationId = null): ?array
    {
        $store = $this->repository->getStoreByPublicId($publicId, false, $organizationId);

        return $store === null ? null : $this->presentSingleStore($store);
    }

    /**
     * Public store payload plus the routing targets as public ids, so the
     * settings screen can preselect them (it works with prj_… / usr_…).
     *
     * @param array<string,mixed> $store
     * @return array<string,mixed>
     */
    private function presentSingleStore(array $store): array
    {
        $settings = self::settingsFromStore($store);
        $projectId = (int)($settings['default_project_id'] ?? 0);
        $userId = (int)($settings['default_assignee_id'] ?? 0);

        return $this->decorateRoutingPublicIds(
            self::publicStore($store),
            $settings,
            $projectId > 0 ? $this->repository->projectPublicIdsByIds([$projectId], self::storeOrganizationId($store)) : [],
            $userId > 0 ? $this->repository->userPublicIdsByIds([$userId], self::storeOrganizationId($store)) : []
        );
    }

    /**
     * @param array<string,mixed> $presented
     * @param array<string,mixed> $settings
     * @param array<int,string> $projectMap
     * @param array<int,string> $userMap
     * @return array<string,mixed>
     */
    private function decorateRoutingPublicIds(array $presented, array $settings, array $projectMap, array $userMap): array
    {
        $projectId = (int)($settings['default_project_id'] ?? 0);
        $userId = (int)($settings['default_assignee_id'] ?? 0);
        $presented['default_project_public_id'] = $projectId > 0 ? ($projectMap[$projectId] ?? null) : null;
        $presented['default_assignee_public_id'] = $userId > 0 ? ($userMap[$userId] ?? null) : null;

        return $presented;
    }

    /**
     * Resolve the routing targets from either a numeric id or a public id.
     * Ingest casts the stored values to int, so public ids are translated here.
     *
     * @param array<string,mixed> $input
     * @return array{input:array<string,mixed>, error:array<string,mixed>|null}
     */
    private function resolveRoutingInput(array $input, ?int $organizationId): array
    {
        $resolvers = [
            'default_project_id' => 'resolveProjectId',
            'default_assignee_id' => 'resolveUserId',
        ];
        foreach ($resolvers as $key => $resolver) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $input[$key] = null;
                continue;
            }
            $resolved = $this->repository->{$resolver}($value, $organizationId);
            if ($resolved === null) {
                return [
                    'input' => $input,
                    'error' => self::failure('STORE_ROUTING_TARGET_INVALID', 422, [
                        $key => ['The project or user does not exist'],
                    ]),
                ];
            }
            $input[$key] = $resolved;
        }

        if (array_key_exists('routing_matrix', $input)) {
            if (!is_array($input['routing_matrix'])) {
                return [
                    'input' => $input,
                    'error' => self::failure('STORE_ROUTING_TARGET_INVALID', 422, [
                        'routing_matrix' => ['Routing rules must be an object of rules'],
                    ]),
                ];
            }
            foreach ($input['routing_matrix'] as $ruleKey => $rule) {
                if (!is_array($rule)) {
                    return [
                        'input' => $input,
                        'error' => self::failure('STORE_ROUTING_TARGET_INVALID', 422, [
                            'routing_matrix.' . (string)$ruleKey => ['Each routing rule must be an object'],
                        ]),
                    ];
                }
                foreach (['project_id' => 'resolveProjectId', 'assignee_id' => 'resolveUserId'] as $key => $resolver) {
                    if (!array_key_exists($key, $rule)) {
                        continue;
                    }
                    $value = $rule[$key];
                    if ($value === null || (is_string($value) && trim($value) === '')) {
                        $rule[$key] = null;
                        continue;
                    }
                    $resolved = $organizationId !== null && $organizationId > 0
                        ? $this->repository->{$resolver}($value, $organizationId)
                        : null;
                    if ($resolved === null) {
                        return [
                            'input' => $input,
                            'error' => self::failure('STORE_ROUTING_TARGET_INVALID', 422, [
                                'routing_matrix.' . (string)$ruleKey . '.' . $key => ['The project or user must belong to the active workspace'],
                            ]),
                        ];
                    }
                    $rule[$key] = $resolved;
                }
                $input['routing_matrix'][$ruleKey] = $rule;
            }
        }

        return ['input' => $input, 'error' => null];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{ok:bool, code:string, http_status:int, errors:array<string,list<string>>, store:array<string,mixed>|null, secret:?string, secret_valid_until:?string}
     */
    public function createStore(array $input, array $actor = [], ?string $ip = null, ?string $userAgent = null, ?int $organizationId = null): array
    {
        if ($organizationId === null || $organizationId <= 0) {
            return self::failure('ACTIVE_WORKSPACE_REQUIRED', 409, ['organization_id' => ['Select an active workspace before creating a store']]);
        }
        $ipWhitelistInput = $input['ip_whitelist'] ?? $input['allowed_ips'] ?? null;
        $ipWhitelistError = self::validateIpWhitelistInput($ipWhitelistInput);
        if ($ipWhitelistError !== null) {
            return self::failure('STORE_IP_WHITELIST_INVALID', 422, ['ip_whitelist' => [$ipWhitelistError]]);
        }
        $name = trim((string)($input['name'] ?? $input['title'] ?? ''));
        if ($name === '') {
            return self::failure('STORE_NAME_REQUIRED', 422, ['name' => ['Store name is required']]);
        }

        $cmsType = self::normalizeCmsType($input['cms_type'] ?? null);
        $status = self::normalizeStatus($input['status'] ?? null);
        $locale = self::normalizeLocale($input['locale'] ?? null);

        $routing = $this->resolveRoutingInput($input, $organizationId);
        if ($routing['error'] !== null) {
            return $routing['error'];
        }
        $input = $routing['input'];

        $webhookUrl = null;
        $webhookRaw = trim((string)($input['webhook_url'] ?? ''));
        if ($webhookRaw !== '') {
            $urlError = self::validateWebhookUrl($webhookRaw);
            if ($urlError !== null) {
                return self::failure('STORE_WEBHOOK_URL_INVALID', 422, ['webhook_url' => [$urlError]]);
            }
            $webhookUrl = $webhookRaw;
        }

        $secret = EncryptionService::generateSecret();
        $store = $this->repository->createStore([
            'organization_id' => $organizationId,
            'name' => $name,
            'cms_type' => $cmsType,
            'store_url' => self::trimOrNull($input['store_url'] ?? null),
            'description' => self::trimOrNull($input['description'] ?? null),
            'api_key' => $this->generateApiKey(),
            'api_secret_encrypted' => EncryptionService::encrypt($secret),
            'api_secret_hint' => EncryptionService::mask($secret),
            'ip_whitelist' => self::normalizeIpWhitelist($input['ip_whitelist'] ?? $input['allowed_ips'] ?? null),
            'rate_limit_per_minute' => self::normalizeRateLimit($input['rate_limit_per_minute'] ?? null),
            'webhook_url' => $webhookUrl,
            'status' => $status,
            'locale' => $locale,
            'settings_json' => self::encodeSettings(self::mergeSettings([], $input, $this->config)),
            'created_by_user_id' => self::actorUserId($actor),
        ]);

        if ($store === []) {
            return self::failure('STORE_CREATE_FAILED', 500, []);
        }

        $this->repository->audit('store.created', (int)$store['id'], 'user', (string)self::actorUserId($actor), [
            'name' => $name,
            'cms_type' => $cmsType,
            'status' => $status,
        ], $ip, $userAgent);

        return [
            'ok' => true,
            'code' => 'STORE_CREATED',
            'http_status' => 201,
            'errors' => [],
            'store' => $this->presentSingleStore($store),
            // Returned exactly once, like a freshly issued API key.
            'secret' => $secret,
            'secret_valid_until' => null,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{ok:bool, code:string, http_status:int, errors:array<string,list<string>>, store:array<string,mixed>|null, secret:?string, secret_valid_until:?string}
     */
    public function updateStore(string $publicId, array $input, array $actor = [], ?string $ip = null, ?string $userAgent = null, ?int $organizationId = null): array
    {
        $current = $this->repository->getStoreByPublicId($publicId, false, $organizationId);
        if ($current === null) {
            return self::failure('STORE_NOT_FOUND', 404, []);
        }

        $set = [];
        if (array_key_exists('name', $input)) {
            $name = trim((string)$input['name']);
            if ($name === '') {
                return self::failure('STORE_NAME_REQUIRED', 422, ['name' => ['Store name is required']]);
            }
            $set['name'] = $name;
        }
        foreach (['store_url', 'description'] as $field) {
            if (array_key_exists($field, $input)) {
                $set[$field] = self::trimOrNull($input[$field]);
            }
        }
        if (array_key_exists('cms_type', $input)) {
            $set['cms_type'] = self::normalizeCmsType($input['cms_type']);
        }
        if (array_key_exists('status', $input)) {
            $set['status'] = self::normalizeStatus($input['status']);
        }
        if (array_key_exists('locale', $input)) {
            $set['locale'] = self::normalizeLocale($input['locale']);
        }
        if (array_key_exists('rate_limit_per_minute', $input)) {
            $set['rate_limit_per_minute'] = self::normalizeRateLimit($input['rate_limit_per_minute']);
        }
        if (array_key_exists('ip_whitelist', $input) || array_key_exists('allowed_ips', $input)) {
            $ipWhitelistInput = $input['ip_whitelist'] ?? $input['allowed_ips'];
            $ipWhitelistError = self::validateIpWhitelistInput($ipWhitelistInput);
            if ($ipWhitelistError !== null) {
                return self::failure('STORE_IP_WHITELIST_INVALID', 422, ['ip_whitelist' => [$ipWhitelistError]]);
            }
            $set['ip_whitelist'] = self::normalizeIpWhitelist($ipWhitelistInput);
        }
        if (array_key_exists('webhook_url', $input)) {
            $webhookRaw = trim((string)$input['webhook_url']);
            if ($webhookRaw === '') {
                $set['webhook_url'] = null;
            } else {
                $urlError = self::validateWebhookUrl($webhookRaw);
                if ($urlError !== null) {
                    return self::failure('STORE_WEBHOOK_URL_INVALID', 422, ['webhook_url' => [$urlError]]);
                }
                $set['webhook_url'] = $webhookRaw;
            }
        }
        if (array_key_exists('webhook_secret', $input)) {
            $webhookSecret = trim((string)$input['webhook_secret']);
            $set['webhook_secret_encrypted'] = $webhookSecret === '' ? null : EncryptionService::encrypt($webhookSecret);
        }

        $routing = $this->resolveRoutingInput($input, $organizationId ?? self::storeOrganizationId($current));
        if ($routing['error'] !== null) {
            return $routing['error'];
        }
        $input = $routing['input'];

        $settings = self::mergeSettings(self::settingsFromStore($current), $input, $this->config);
        if (self::hasAnySettingKey($input)) {
            $set['settings_json'] = self::encodeSettings($settings);
        }

        if ($set === []) {
            return self::failure('STORE_NO_UPDATABLE_FIELDS', 422, []);
        }

        if (!$this->repository->updateStore($publicId, $set, $organizationId)) {
            return self::failure('STORE_UPDATE_FAILED', 500, []);
        }

        $this->repository->audit('store.updated', (int)$current['id'], 'user', (string)self::actorUserId($actor), [
            'fields' => array_keys($set),
        ], $ip, $userAgent);

        return [
            'ok' => true,
            'code' => 'STORE_UPDATED',
            'http_status' => 200,
            'errors' => [],
            'store' => $this->getStore($publicId, $organizationId),
            'secret' => null,
            'secret_valid_until' => null,
        ];
    }

    /**
     * @param array<string,mixed> $actor
     * @return array{ok:bool, code:string, http_status:int, errors:array<string,list<string>>, store:array<string,mixed>|null, secret:?string, secret_valid_until:?string}
     */
    public function deleteStore(string $publicId, array $actor = [], ?string $ip = null, ?string $userAgent = null, ?int $organizationId = null): array
    {
        $current = $this->repository->getStoreByPublicId($publicId, false, $organizationId);
        if ($current === null) {
            return self::failure('STORE_NOT_FOUND', 404, []);
        }

        if (!$this->repository->softDeleteStore($publicId, $organizationId)) {
            return self::failure('STORE_DELETE_FAILED', 500, []);
        }
        $this->repository->audit('store.deleted', (int)$current['id'], 'user', (string)self::actorUserId($actor), [], $ip, $userAgent);

        return [
            'ok' => true,
            'code' => 'STORE_DELETED',
            'http_status' => 200,
            'errors' => [],
            'store' => null,
            'secret' => null,
            'secret_valid_until' => null,
        ];
    }

    /**
     * Issues a new secret; the previous one stays valid for a grace period so a
     * store can roll over without downtime.
     *
     * @param array<string,mixed> $actor
     * @return array{ok:bool, code:string, http_status:int, errors:array<string,list<string>>, store:array<string,mixed>|null, secret:?string, secret_valid_until:?string}
     */
    public function rotateSecret(string $publicId, array $actor = [], ?string $ip = null, ?string $userAgent = null, ?int $organizationId = null): array
    {
        $current = $this->repository->getStoreByPublicId($publicId, false, $organizationId);
        if ($current === null) {
            return self::failure('STORE_NOT_FOUND', 404, []);
        }

        $secret = EncryptionService::generateSecret();
        $validUntil = gmdate('Y-m-d H:i:s', time() + self::ROTATION_GRACE_SECONDS);

        if (!$this->repository->updateStore($publicId, [
            'api_secret_encrypted' => EncryptionService::encrypt($secret),
            'api_secret_hint' => EncryptionService::mask($secret),
            'secondary_api_secret_encrypted' => (string)($current['api_secret_encrypted'] ?? ''),
            'secondary_secret_expires_at' => $validUntil,
            'secret_rotated_at' => gmdate('Y-m-d H:i:s'),
        ], $organizationId)) {
            return self::failure('STORE_SECRET_ROTATION_FAILED', 500, []);
        }

        $this->repository->audit('store.secret_rotated', (int)$current['id'], 'user', (string)self::actorUserId($actor), [
            'grace_seconds' => self::ROTATION_GRACE_SECONDS,
        ], $ip, $userAgent);

        return [
            'ok' => true,
            'code' => 'STORE_SECRET_ROTATED',
            'http_status' => 200,
            'errors' => [],
            'store' => $this->getStore($publicId, $organizationId),
            'secret' => $secret,
            'secret_valid_until' => gmdate('c', time() + self::ROTATION_GRACE_SECONDS),
        ];
    }

    // ── Pure helpers (unit tested) ─────────────────────────────────────

    public static function normalizeCmsType(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));

        return in_array($normalized, self::CMS_TYPES, true) ? $normalized : 'custom';
    }

    public static function normalizeStatus(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));

        return in_array($normalized, self::STATUSES, true) ? $normalized : 'active';
    }

    public static function normalizeLocale(mixed $value): string
    {
        return strtolower(trim((string)$value)) === 'en-gb' ? 'en-gb' : 'ru-ru';
    }

    /**
     * Normalises an IP/CIDR list into a comma-separated string. Invalid entries
     * are dropped instead of rejected, so a partially wrong paste still saves.
     */
    public static function normalizeIpWhitelist(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $rawEntries = is_array($value) ? $value : [$value];
        $list = [];
        foreach ($rawEntries as $rawEntry) {
            if (!is_string($rawEntry) && !is_int($rawEntry)) {
                continue;
            }
            $parts = preg_split('/[\s,]+/', trim((string)$rawEntry));
            if (is_array($parts)) {
                $list = array_merge($list, $parts);
            }
        }
        $clean = [];
        foreach ($list as $entry) {
            $entry = trim((string)$entry);
            if ($entry === '') {
                continue;
            }
            $address = $entry;
            $bits = null;
            if (str_contains($entry, '/')) {
                [$address, $bits] = explode('/', $entry, 2);
                if ($bits === '' || !ctype_digit($bits)) {
                    continue;
                }
                $bits = (int)$bits;
            }
            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            if ($bits !== null) {
                $maxBits = str_contains($address, ':') ? 128 : 32;
                if ($bits < 0 || $bits > $maxBits) {
                    continue;
                }
                $entry = $address . '/' . $bits;
            }
            $clean[] = $entry;
        }

        return $clean === [] ? null : implode(',', array_values(array_unique($clean)));
    }

    /**
     * Reject malformed allowlist input before normalization. Silently dropping
     * every invalid entry turns a configured restriction into NULL, which the
     * authentication path interprets as "allow every source IP".
     *
     * Null/blank input intentionally means no IP restriction. A non-empty
     * value must contain only valid IPs or CIDRs; partial acceptance is unsafe
     * because the administrator may believe a discarded address is enforced.
     */
    public static function validateIpWhitelistInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) && !is_array($value) && !is_int($value)) {
            return 'Enter IP addresses or CIDR ranges as text.';
        }
        if ((is_string($value) && strlen($value) > 6500) || (is_array($value) && count($value) > 100)) {
            return 'The allowlist may contain at most 100 entries, each no longer than 64 characters.';
        }

        $entries = is_array($value) ? $value : preg_split('/[\\s,]+/', trim((string)$value));
        if (!is_array($entries)) {
            return 'Enter IP addresses or CIDR ranges as text.';
        }
        $entryCount = 0;
        foreach ($entries as $entry) {
            if (!is_string($entry) && !is_int($entry)) {
                return 'Each allowlist entry must be an IP address or CIDR range.';
            }
            $entry = trim((string)$entry);
            if ($entry === '') {
                continue;
            }
            // Array callers may send comma/newline separated chunks too.
            $parts = preg_split('/[\\s,]+/', $entry);
            if (!is_array($parts)) {
                return 'Enter IP addresses or CIDR ranges as text.';
            }
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                if (++$entryCount > 100 || strlen($part) > 64) {
                    return 'The allowlist may contain at most 100 entries, each no longer than 64 characters.';
                }
                $address = $part;
                $bits = null;
                if (str_contains($part, '/')) {
                    if (substr_count($part, '/') !== 1) {
                        return 'Invalid IP address or CIDR range: ' . $part;
                    }
                    [$address, $bitsRaw] = explode('/', $part, 2);
                    if ($bitsRaw === '' || !ctype_digit($bitsRaw)) {
                        return 'Invalid CIDR prefix: ' . $part;
                    }
                    $bits = (int)$bitsRaw;
                }
                if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                    return 'Invalid IP address or CIDR range: ' . $part;
                }
                if ($bits !== null) {
                    $maxBits = str_contains($address, ':') ? 128 : 32;
                    if ($bits < 0 || $bits > $maxBits) {
                        return 'CIDR prefix is out of range: ' . $part;
                    }
                }
            }
        }

        return null;
    }

    public static function normalizeRateLimit(mixed $value, int $default = self::DEFAULT_RATE_LIMIT): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        if ($filtered === false || (int)$filtered <= 0) {
            // Zero/negative is not a meaningful rate limit, keep the default.
            return $default;
        }

        return max(1, min(10000, (int)$filtered));
    }

    /**
     * Merges known settings keys only; unknown keys are ignored so the payload
     * cannot grow without a schema change.
     *
     * @param array<string,mixed> $current
     * @param array<string,mixed> $input
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public static function mergeSettings(array $current, array $input, array $config = []): array
    {
        $settings = $current;

        foreach (['default_project_id', 'default_assignee_id'] as $key) {
            if (array_key_exists($key, $input)) {
                $settings[$key] = self::intOrNull($input[$key]);
            }
        }
        if (array_key_exists('default_priority_code', $input)) {
            $settings['default_priority_code'] = self::trimOrNull($input['default_priority_code']);
        }
        if (array_key_exists('create_task_for', $input)) {
            $settings['create_task_for'] = self::normalizeIngestTypes($input['create_task_for']);
        }
        if (array_key_exists('on_duplicate', $input)) {
            $settings['on_duplicate'] = self::normalizeOnDuplicate($input['on_duplicate'], $config);
        }
        if (array_key_exists('allow_inline_files', $input)) {
            $settings['allow_inline_files'] = self::boolFrom($input['allow_inline_files']);
        }
        if (array_key_exists('antispam_enabled', $input)) {
            $settings['antispam_enabled'] = self::boolFrom($input['antispam_enabled']);
        }
        if (array_key_exists('retention_days', $input)) {
            $settings['retention_days'] = self::clampRetention($input['retention_days']);
        }
        if (array_key_exists('tags', $input)) {
            $settings['tags'] = self::normalizeTags($input['tags']);
        }
        if (array_key_exists('routing_matrix', $input) && is_array($input['routing_matrix'])) {
            $settings['routing_matrix'] = $input['routing_matrix'];
        }
        if (array_key_exists('field_mappings', $input) && is_array($input['field_mappings'])) {
            $settings['field_mappings'] = $input['field_mappings'];
        }

        return self::withSettingDefaults($settings, $config);
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public static function withSettingDefaults(array $settings, array $config = []): array
    {
        if (!array_key_exists('on_duplicate', $settings)) {
            $settings['on_duplicate'] = self::normalizeOnDuplicate($config['default_on_duplicate'] ?? 'merge', $config);
        }
        if (!array_key_exists('create_task_for', $settings)) {
            $settings['create_task_for'] = [];
        }
        if (!array_key_exists('allow_inline_files', $settings)) {
            $settings['allow_inline_files'] = 0;
        }
        if (!array_key_exists('antispam_enabled', $settings)) {
            $settings['antispam_enabled'] = 1;
        }
        if (!array_key_exists('retention_days', $settings)) {
            $settings['retention_days'] = self::clampRetention($config['ingest_events_retention_days'] ?? self::DEFAULT_RETENTION_DAYS);
        }

        return $settings;
    }

    /**
     * @param array<string,mixed> $store
     * @return array<string,mixed>
     */
    public static function settingsFromStore(array $store): array
    {
        $raw = trim((string)($store['settings_json'] ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_intersect_key($decoded, array_flip(self::SETTING_KEYS)) : [];
    }

    private static function storeOrganizationId(array $store): ?int
    {
        $organizationId = (int)($store['organization_id'] ?? 0);
        return $organizationId > 0 ? $organizationId : null;
    }

    /**
     * Store payload safe for API responses: never exposes secrets.
     *
     * @param array<string,mixed> $store
     * @return array<string,mixed>
     */
    public static function publicStore(array $store): array
    {
        $store['has_secret'] = trim((string)($store['api_secret_encrypted'] ?? '')) !== '';
        $store['has_secondary_secret'] = trim((string)($store['secondary_api_secret_encrypted'] ?? '')) !== ''
            && !empty($store['secondary_secret_expires_at'])
            && strtotime((string)$store['secondary_secret_expires_at']) > time();
        $store['has_webhook_secret'] = trim((string)($store['webhook_secret_encrypted'] ?? '')) !== '';
        $store['ip_whitelist_list'] = self::ipWhitelistToList($store['ip_whitelist'] ?? null);
        $store['settings'] = self::withSettingDefaults(self::settingsFromStore($store));

        unset(
            $store['api_secret_encrypted'],
            $store['secondary_api_secret_encrypted'],
            $store['webhook_secret_encrypted']
        );

        return $store;
    }

    /**
     * @return list<string>
     */
    public static function ipWhitelistToList(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $v): bool => $v !== ''));
    }

    public static function generateApiKey(): string
    {
        return 'stk_' . bin2hex(random_bytes(16));
    }

    public static function validateWebhookUrl(string $url): ?string
    {
        $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: ''));
        $host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return 'Absolute http(s) URL required';
        }
        if (class_exists(UrlSafetyValidator::class)) {
            $validator = new UrlSafetyValidator();
            $checked = $validator->validateProviderUrl($url, true, ['https', 'http']);
            if (!(bool)($checked['ok'] ?? false)) {
                return (string)($checked['code'] ?? 'URL_INVALID');
            }
        }

        return null;
    }

    // ── Internals ──────────────────────────────────────────────────────

    /**
     * @param array<string,list<string>> $errors
     * @return array{ok:bool, code:string, http_status:int, errors:array<string,list<string>>, store:null, secret:null, secret_valid_until:null}
     */
    private static function failure(string $code, int $httpStatus, array $errors): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'http_status' => $httpStatus,
            'errors' => $errors,
            'store' => null,
            'secret' => null,
            'secret_valid_until' => null,
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    private static function hasAnySettingKey(array $input): bool
    {
        foreach (self::SETTING_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private static function encodeSettings(array $settings): ?string
    {
        return $settings === []
            ? null
            : json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return list<string>
     */
    private static function normalizeIngestTypes(mixed $value): array
    {
        $list = is_array($value) ? $value : preg_split('/[\s,]+/', (string)$value);
        $list = is_array($list) ? $list : [];
        $clean = [];
        foreach ($list as $entry) {
            $entry = strtolower(trim((string)$entry));
            if ($entry === 'quick-order') {
                $entry = 'quick_order';
            }
            if (in_array($entry, self::INGEST_TYPES, true)) {
                $clean[] = $entry;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function normalizeOnDuplicate(mixed $value, array $config = []): string
    {
        $normalized = strtolower(trim((string)$value));
        if (in_array($normalized, self::ON_DUPLICATE_MODES, true)) {
            return $normalized;
        }
        $configured = strtolower(trim((string)($config['default_on_duplicate'] ?? 'merge')));

        return in_array($configured, self::ON_DUPLICATE_MODES, true) ? $configured : 'merge';
    }

    private static function clampRetention(mixed $value): int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        if ($filtered === false) {
            return self::DEFAULT_RETENTION_DAYS;
        }

        return max(1, min(3650, (int)$filtered));
    }

    /**
     * @return list<string>
     */
    private static function normalizeTags(mixed $value): array
    {
        $list = is_array($value) ? $value : preg_split('/[\s,]+/', (string)$value);
        $list = is_array($list) ? $list : [];
        $clean = [];
        foreach ($list as $entry) {
            $entry = trim((string)$entry);
            if ($entry !== '' && mb_strlen($entry) <= 64) {
                $clean[] = $entry;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param array<string,mixed> $actor
     */
    private static function actorUserId(array $actor): int
    {
        return (int)($actor['id'] ?? 0);
    }

    private static function trimOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string)$value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        return $filtered === false ? null : (int)$filtered;
    }

    private static function boolFrom(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        $normalized = strtolower(trim((string)$value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }
}
