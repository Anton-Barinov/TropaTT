<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Api\System\Library\Http\Request;
use Module\Crm\EcommerceGateway\Repository\StoreRepository;

/**
 * Authenticates a store (CMS) request before any data is touched (E-COM-01 §4).
 *
 * Check order: store lookup -> status -> IP whitelist -> timestamp window ->
 * signature -> nonce. Cheap, non-mutating validations run first, and the nonce
 * is recorded only after the signature is proven valid, so an attacker cannot
 * flood the nonce table with garbage.
 *
 * @phpstan-type AuthResult array{ok:bool, code:string, http_status:int, store:array<string,mixed>|null}
 */
final class StoreAuthService
{
    public function __construct(
        private readonly StoreRepository $repository,
        private readonly int $timestampTolerance = SignatureService::DEFAULT_TIMESTAMP_TOLERANCE,
        private readonly int $nonceRetentionSeconds = 900,
    ) {
    }

    /**
     * @return AuthResult
     */
    public function authenticate(Request $request): array
    {
        $storeKey = trim((string)$request->header(SignatureService::HEADER_STORE_KEY, ''));
        if ($storeKey === '') {
            return $this->reject('INGESTION_STORE_KEY_MISSING', 401, null, $request);
        }

        $store = $this->repository->getStoreByApiKey($storeKey);
        if ($store === null) {
            return $this->reject('INGESTION_STORE_KEY_UNKNOWN', 401, null, $request, $storeKey);
        }

        $storeId = (int)$store['id'];

        if (!empty($store['deleted_at']) || (string)($store['status'] ?? '') !== 'active') {
            return $this->reject('INGESTION_STORE_INACTIVE', 403, $storeId, $request, $storeKey);
        }

        if (!$this->ipAllowed($store, $request->clientIp())) {
            return $this->reject('INGESTION_IP_NOT_ALLOWED', 403, $storeId, $request, $storeKey);
        }

        $timestampRaw = trim((string)$request->header(SignatureService::HEADER_TIMESTAMP, ''));
        if ($timestampRaw === '' || !ctype_digit($timestampRaw)) {
            return $this->reject('INGESTION_TIMESTAMP_EXPIRED', 401, $storeId, $request, $storeKey);
        }
        $timestamp = (int)$timestampRaw;
        if (!SignatureService::isTimestampFresh($timestamp, time(), $this->timestampTolerance)) {
            return $this->reject('INGESTION_TIMESTAMP_EXPIRED', 401, $storeId, $request, $storeKey);
        }

        $nonce = trim((string)$request->header(SignatureService::HEADER_NONCE, ''));
        if (!SignatureService::isValidNonce($nonce)) {
            return $this->reject('INGESTION_SIGNATURE_INVALID', 401, $storeId, $request, $storeKey);
        }

        $signature = trim((string)$request->header(SignatureService::HEADER_SIGNATURE, ''));
        $secret = $this->resolveSigningSecret($store);
        $legacySecret = $this->resolveLegacySecret($store);
        if ($secret === null && $legacySecret === null) {
            // The stored secret cannot be decrypted (missing/rotated APP_SECRET).
            // Treated as an auth failure so nothing is leaked to the caller.
            return $this->reject('INGESTION_SIGNATURE_INVALID', 401, $storeId, $request, $storeKey);
        }

        $path = self::canonicalPath($request);
        $verified = $secret !== null
            && SignatureService::verify($secret, $signature, $request->method, $path, $timestampRaw, $nonce, $request->rawBody);
        if (!$verified && $legacySecret !== null) {
            $verified = SignatureService::verify($legacySecret, $signature, $request->method, $path, $timestampRaw, $nonce, $request->rawBody);
        }
        if (!$verified) {
            return $this->reject('INGESTION_SIGNATURE_INVALID', 401, $storeId, $request, $storeKey);
        }

        if (!$this->repository->recordNonce($storeId, $nonce)) {
            return $this->reject('INGESTION_NONCE_REPLAYED', 401, $storeId, $request, $storeKey);
        }

        $this->maybePruneNonces();

        return [
            'ok' => true,
            'code' => 'OK',
            'http_status' => 200,
            'store' => $store,
        ];
    }

    /**
     * Canonical request path used in the signature: the `route` query value
     * normalised to exactly one leading slash. Falls back to the physical API
     * path when the API is reached without the `route` parameter.
     */
    public static function canonicalPath(Request $request): string
    {
        $route = trim((string)($request->query['route'] ?? ''));
        if ($route !== '') {
            return '/' . ltrim($route, '/');
        }

        $path = (string)$request->path;
        $path = preg_replace('#^/api/index\.php#', '', $path) ?? $path;
        $path = preg_replace('#^/index\.php#', '', $path) ?? $path;

        return '/' . ltrim($path, '/');
    }

    /**
     * Active secret, or the previous one while its rotation grace period lasts.
     *
     * @param array<string,mixed> $store
     */
    public function resolveSigningSecret(array $store): ?string
    {
        $active = EncryptionService::decrypt((string)($store['api_secret_encrypted'] ?? ''));
        if ($active !== null && $active !== '') {
            return $active;
        }

        return null;
    }

    /**
     * The previous secret kept alive by a rotation grace period.
     *
     * @param array<string,mixed> $store
     */
    public function resolveLegacySecret(array $store): ?string
    {
        $expires = (string)($store['secondary_secret_expires_at'] ?? '');
        if ($expires === '' || strtotime($expires) < time()) {
            return null;
        }

        return EncryptionService::decrypt((string)($store['secondary_api_secret_encrypted'] ?? ''));
    }

    /**
     * @param array<string,mixed> $store
     */
    private function ipAllowed(array $store, string $clientIp): bool
    {
        $raw = trim((string)($store['ip_whitelist'] ?? ''));
        if ($raw === '') {
            return true;
        }

        $list = array_filter(array_map('trim', explode(',', $raw)), static fn(string $v): bool => $v !== '');
        if ($list === []) {
            return true;
        }
        if ($clientIp === '') {
            return false;
        }

        foreach ($list as $allowed) {
            if ($allowed === $clientIp || self::ipMatchesCidr($clientIp, $allowed)) {
                return true;
            }
        }

        return false;
    }

    private static function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int)$bits;
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }

        $mask = 0xff << (8 - $remainder) & 0xff;

        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }

    /**
     * Security-relevant rejections are recorded both in the audit log and in the
     * dedicated security log used by the admin diagnostics.
     */
    private function reject(string $code, int $httpStatus, ?int $storeId, Request $request, ?string $storeKey = null): array
    {
        $details = ['code' => $code];
        if ($storeKey !== null) {
            $details['api_key_hint'] = EncryptionService::mask($storeKey);
        }

        $ip = $request->clientIp();
        $userAgent = substr((string)$request->header('User-Agent', ''), 0, 512);

        $this->repository->audit(
            action: 'ingest.auth_rejected',
            storeId: $storeId,
            actorType: 'store',
            actorId: $storeKey !== null ? EncryptionService::mask($storeKey) : null,
            details: $details,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->repository->logSecurityEvent(
            eventType: $code,
            severity: $httpStatus >= 500 ? 'error' : 'warning',
            storeId: $storeId,
            ip: $ip,
            userAgent: $userAgent,
            details: $details
        );

        return [
            'ok' => false,
            'code' => $code,
            'http_status' => $httpStatus,
            'store' => null,
        ];
    }

    private function maybePruneNonces(): void
    {
        // Cheap probabilistic cleanup; a cron task handles the deterministic sweep.
        if (random_int(1, 100) === 1) {
            $this->repository->pruneNonces($this->nonceRetentionSeconds);
        }
    }

}
