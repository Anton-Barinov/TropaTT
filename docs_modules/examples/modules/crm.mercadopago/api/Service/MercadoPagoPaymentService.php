<?php

declare(strict_types=1);

namespace Module\Crm\MercadoPago\Service;

use Closure;
use PDO;
use InvalidArgumentException;
use RuntimeException;

/** Mercado Pago Checkout Pro client. Credentials are workspace-scoped and encrypted at rest. */
final class MercadoPagoPaymentService
{
    private const API_BASE = 'https://api.mercadopago.com';
    private PDO $db;
    private int $workspaceId;
    /** @var Closure(string, string, array<string, string>, ?string): array{status:int,body:string}|null */
    private ?Closure $httpTransport;

    /** @param null|callable(string,string,array<string,string>,?string):array{status:int,body:string} $httpTransport Test seam; production uses HTTPS streams. */
    public function __construct(PDO $db, int $workspaceId, ?callable $httpTransport = null)
    {
        if ($workspaceId < 1) {
            throw new RuntimeException('A valid workspace is required.');
        }
        $this->db = $db;
        $this->workspaceId = $workspaceId;
        $this->httpTransport = $httpTransport === null ? null : Closure::fromCallable($httpTransport);
    }

    public function saveSettings(string $accessToken, ?string $publicKey = null, bool $sandboxMode = true): void
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '' || strlen($accessToken) > 512 || preg_match('/[\r\n]/', $accessToken)) {
            throw new RuntimeException('A valid Mercado Pago access token is required.');
        }
        if ($publicKey !== null && (strlen($publicKey) > 255 || preg_match('/[\x00-\x1F]/', $publicKey))) {
            throw new RuntimeException('A valid Mercado Pago public key is required.');
        }
        $encryptedToken = $this->encryptCredential($accessToken);
        $stmt = $this->db->prepare('SELECT id FROM module_mercadopago_settings WHERE workspace_id = :workspace LIMIT 1');
        $stmt->execute([':workspace' => $this->workspaceId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $stmt = $this->db->prepare('UPDATE module_mercadopago_settings SET public_key = :public_key, access_token_encrypted = :token, sandbox_mode = :sandbox, is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute([':public_key' => $publicKey, ':token' => $encryptedToken, ':sandbox' => $sandboxMode ? 1 : 0, ':id' => $id]);
            return;
        }
        $stmt = $this->db->prepare('INSERT INTO module_mercadopago_settings (workspace_id, public_key, access_token_encrypted, sandbox_mode, is_active) VALUES (:workspace, :public_key, :token, :sandbox, 1)');
        $stmt->execute([':workspace' => $this->workspaceId, ':public_key' => $publicKey, ':token' => $encryptedToken, ':sandbox' => $sandboxMode ? 1 : 0]);
    }

    /** @return array{payment_public_id:string,preference_id:string,init_point_url:string,status:string,amount:float,currency:string,external_reference:string} */
    public function createPaymentPreference(array $data): array
    {
        $titleInput = $data['title'] ?? null;
        if (!is_string($titleInput)) {
            throw new InvalidArgumentException('A valid payment title is required.');
        }
        $title = trim($titleInput);
        $amountMinor = $this->amountToMinorUnits($data['amount'] ?? null);
        $currencyInput = $data['currency_id'] ?? 'BRL';
        if (!is_string($currencyInput)) {
            throw new InvalidArgumentException('This module currently supports BRL only.');
        }
        $currencyId = strtoupper(trim($currencyInput));
        if ($title === '' || strlen($title) > 765 || preg_match('//u', $title) !== 1 || preg_match('/[\x00-\x1F]/', $title)) {
            throw new InvalidArgumentException('A valid payment title is required.');
        }
        if ($currencyId !== 'BRL') {
            throw new InvalidArgumentException('This module currently supports BRL only.');
        }
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if (!is_string($idempotencyKey) || !preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $idempotencyKey)) {
            throw new InvalidArgumentException('A valid idempotency key is required.');
        }
        $externalRefInput = $data['external_reference'] ?? '';
        if (!is_string($externalRefInput)) {
            throw new InvalidArgumentException('A valid external reference is required.');
        }
        $externalRef = trim($externalRefInput);
        if (strlen($externalRef) > 128 || preg_match('//u', $externalRef) !== 1 || preg_match('/[\x00-\x1F]/', $externalRef)) {
            throw new InvalidArgumentException('A valid external reference is required.');
        }

        $credentials = $this->getCredentials();
        $amount = $this->minorUnitsToMajorString($amountMinor);
        $requestHash = hash('sha256', json_encode([
            'title' => $title,
            'amount_minor' => $amountMinor,
            'currency_id' => $currencyId,
            'external_reference' => $externalRef,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $idempotencyHash = hash('sha256', $idempotencyKey);
        $attempt = $this->reservePreferenceAttempt($idempotencyHash, $requestHash, $externalRef);
        if (!hash_equals((string)$attempt['request_hash'], $requestHash)) {
            throw new InvalidArgumentException('This idempotency key was already used for different payment details.');
        }
        if ((string)$attempt['state'] === 'completed') {
            return $this->preferenceResult($attempt);
        }
        if ((string)$attempt['state'] !== 'reserved') {
            throw new RuntimeException('This checkout attempt has an uncertain result. Check the payments list before creating another link.');
        }

        // A reserved attempt is claimed exactly once. Any crash or timeout after this point
        // remains fail-closed: retries return the attempt state and never POST a second preference.
        $claim = $this->db->prepare("UPDATE module_mercadopago_preference_attempts SET state = 'creating', updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :ws AND idempotency_hash = :key_hash AND state = 'reserved'");
        $claim->execute([':ws' => $this->workspaceId, ':key_hash' => $idempotencyHash]);
        if ($claim->rowCount() !== 1) {
            throw new RuntimeException('This checkout attempt is already being processed or has an uncertain result.');
        }

        $payload = [
            'items' => [[
                'title' => $title,
                'quantity' => 1,
                'currency_id' => $currencyId,
                'unit_price' => $amountMinor / 100,
            ]],
            'external_reference' => (string)$attempt['external_reference'],
        ];
        try {
            $response = $this->request('POST', '/checkout/preferences', $credentials['token'], $payload);
            $preferenceId = trim((string)($response['id'] ?? ''));
            $initPoint = trim((string)($credentials['sandbox'] ? ($response['sandbox_init_point'] ?? '') : ($response['init_point'] ?? '')));
            if ($preferenceId === '' || strlen($preferenceId) > 128 || !$this->isMercadoPagoCheckoutUrl($initPoint)) {
                throw new RuntimeException('Mercado Pago returned an invalid preference response.');
            }

            $publicId = 'pay_mp_' . bin2hex(random_bytes(12));
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("INSERT INTO module_mercadopago_payments
                (public_id, workspace_id, external_reference, mp_preference_id, init_point_url, title, amount, currency_id, status, created_at)
                VALUES (:pid, :ws, :ref, :pref, :url, :title, :amount, :currency, 'pending', CURRENT_TIMESTAMP)");
            $stmt->execute([':pid' => $publicId, ':ws' => $this->workspaceId, ':ref' => $attempt['external_reference'], ':pref' => $preferenceId, ':url' => $initPoint, ':title' => $title, ':amount' => $amount, ':currency' => $currencyId]);
            $finish = $this->db->prepare("UPDATE module_mercadopago_preference_attempts SET state = 'completed', payment_public_id = :pid, preference_id = :pref, checkout_url = :url, updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :ws AND idempotency_hash = :key_hash AND state = 'creating'");
            $finish->execute([':pid' => $publicId, ':pref' => $preferenceId, ':url' => $initPoint, ':ws' => $this->workspaceId, ':key_hash' => $idempotencyHash]);
            if ($finish->rowCount() !== 1) {
                throw new RuntimeException('Checkout attempt state could not be completed safely.');
            }
            $this->logAudit($publicId, null, 'pending', ['event' => 'preference_created', 'preference_id' => $preferenceId]);
            $this->db->commit();

            return ['payment_public_id' => $publicId, 'preference_id' => $preferenceId, 'init_point_url' => $initPoint, 'status' => 'pending', 'amount' => $amount, 'currency' => $currencyId, 'external_reference' => (string)$attempt['external_reference']];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $unknown = $this->db->prepare("UPDATE module_mercadopago_preference_attempts SET state = 'unknown', updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :ws AND idempotency_hash = :key_hash AND state = 'creating'");
            $unknown->execute([':ws' => $this->workspaceId, ':key_hash' => $idempotencyHash]);
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function reservePreferenceAttempt(string $keyHash, string $requestHash, string $externalReference): array
    {
        $find = $this->db->prepare('SELECT request_hash, external_reference, state, payment_public_id, preference_id, checkout_url FROM module_mercadopago_preference_attempts WHERE workspace_id = :ws AND idempotency_hash = :key_hash LIMIT 1');
        $find->execute([':ws' => $this->workspaceId, ':key_hash' => $keyHash]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing)) {
            return $existing;
        }
        $stableReference = $externalReference !== '' ? $externalReference : 'crm_' . bin2hex(random_bytes(16));
        try {
            $insert = $this->db->prepare("INSERT INTO module_mercadopago_preference_attempts (workspace_id, idempotency_hash, request_hash, external_reference, state, created_at, updated_at) VALUES (:ws, :key_hash, :request_hash, :ref, 'reserved', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $insert->execute([':ws' => $this->workspaceId, ':key_hash' => $keyHash, ':request_hash' => $requestHash, ':ref' => $stableReference]);
        } catch (\PDOException $e) {
            // A concurrent request may have won the unique-key insert. Re-read its durable reservation.
            $find->execute([':ws' => $this->workspaceId, ':key_hash' => $keyHash]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                return $existing;
            }
            throw $e;
        }
        $find->execute([':ws' => $this->workspaceId, ':key_hash' => $keyHash]);
        $reserved = $find->fetch(PDO::FETCH_ASSOC);
        if (!is_array($reserved)) {
            throw new RuntimeException('Checkout attempt reservation was not persisted.');
        }
        return $reserved;
    }

    /** @param array<string,mixed> $attempt @return array{payment_public_id:string,preference_id:string,init_point_url:string,status:string,amount:string,currency:string,external_reference:string} */
    private function preferenceResult(array $attempt): array
    {
        $stmt = $this->db->prepare('SELECT public_id, amount, currency_id, external_reference, status FROM module_mercadopago_payments WHERE workspace_id = :ws AND public_id = :pid LIMIT 1');
        $stmt->execute([':ws' => $this->workspaceId, ':pid' => (string)$attempt['payment_public_id']]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($payment) || (string)($attempt['preference_id'] ?? '') === '' || !$this->isMercadoPagoCheckoutUrl((string)($attempt['checkout_url'] ?? ''))) {
            throw new RuntimeException('Completed checkout attempt is inconsistent; no new preference was created.');
        }
        return [
            'payment_public_id' => (string)$payment['public_id'],
            'preference_id' => (string)$attempt['preference_id'],
            'init_point_url' => (string)$attempt['checkout_url'],
            'status' => (string)$payment['status'],
            'amount' => $this->minorUnitsToMajorString($this->amountToMinorUnits($payment['amount'])),
            'currency' => (string)$payment['currency_id'],
            'external_reference' => (string)$payment['external_reference'],
        ];
    }

    /** @return list<array{payment_public_id:string,title:string,amount:string,currency:string,status:string,status_detail:?string,checkout_url:string,created_at:string}> */
    public function listPayments(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            'SELECT public_id, title, amount, currency_id, status, status_detail, init_point_url, created_at'
            . ' FROM module_mercadopago_payments WHERE workspace_id = :workspace'
            . ' ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute([':workspace' => $this->workspaceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn(array $row): array => [
            'payment_public_id' => (string)$row['public_id'],
            'title' => (string)$row['title'],
            'amount' => $this->minorUnitsToMajorString($this->amountToMinorUnits($row['amount'])),
            'currency' => (string)$row['currency_id'],
            'status' => (string)$row['status'],
            'status_detail' => isset($row['status_detail']) ? (string)$row['status_detail'] : null,
            'checkout_url' => (string)$row['init_point_url'],
            'created_at' => (string)$row['created_at'],
        ], $rows);
    }

    /** Verify status against Mercado Pago, never trust a client-supplied status. */
    public function checkPaymentStatus(string $paymentPublicId, string $providerPaymentId = ''): array
    {
        if (!preg_match('/^pay_mp_[a-f0-9]{24}$/', $paymentPublicId)) {
            throw new InvalidArgumentException('A valid payment reference is required.');
        }
        $stmt = $this->db->prepare('SELECT public_id, external_reference, mp_preference_id, mp_payment_id, amount, currency_id, status, status_detail, created_at FROM module_mercadopago_payments WHERE workspace_id = :ws AND public_id = :pid LIMIT 1');
        $stmt->execute([':ws' => $this->workspaceId, ':pid' => $paymentPublicId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($payment)) {
            throw new RuntimeException('Payment not found.');
        }
        $mpPaymentId = trim($providerPaymentId !== '' ? $providerPaymentId : (string)($payment['mp_payment_id'] ?? ''));
        if ($mpPaymentId === '') {
            $createdAt = new \DateTimeImmutable((string)$payment['created_at']);
            $beginDate = $createdAt->modify('-5 minutes')->format('Y-m-d\\TH:i:sP');
            $search = $this->request('GET', '/v1/payments/search', $this->getCredentials()['token'], null, [
                'external_reference' => (string)$payment['external_reference'],
                'sort' => 'date_created',
                'criteria' => 'desc',
                'range' => 'date_created',
                'begin_date' => $beginDate,
                'end_date' => 'NOW',
                'limit' => '50',
                'offset' => '0',
            ]);
            $candidates = is_array($search['results'] ?? null) ? $search['results'] : [];
            foreach ($candidates as $candidate) {
                if (!is_array($candidate)
                    || !is_scalar($candidate['id'] ?? null)
                    || (string)($candidate['external_reference'] ?? '') !== (string)$payment['external_reference']
                    || (string)($candidate['preference_id'] ?? '') !== (string)$payment['mp_preference_id']) {
                    continue;
                }
                try {
                    $candidateAmount = $this->amountToMinorUnits($candidate['transaction_amount'] ?? null);
                } catch (InvalidArgumentException) {
                    continue;
                }
                if ($candidateAmount === $this->amountToMinorUnits((string)$payment['amount'])
                    && strtoupper((string)($candidate['currency_id'] ?? '')) === strtoupper((string)$payment['currency_id'])
                    && preg_match('/^[0-9]{1,32}$/', (string)$candidate['id'])) {
                    $mpPaymentId = (string)$candidate['id'];
                    break;
                }
            }
        }
        if ($mpPaymentId === '') {
            return ['payment_public_id' => $paymentPublicId, 'status' => (string)$payment['status'], 'verified' => false, 'detail' => 'No matching provider payment has been found yet.'];
        }
        if (!preg_match('/^[0-9]{1,32}$/', $mpPaymentId)) {
            throw new InvalidArgumentException('Stored provider payment reference is invalid.');
        }
        $result = $this->request('GET', '/v1/payments/' . rawurlencode($mpPaymentId), $this->getCredentials()['token'], null);
        if (!is_string($result['external_reference'] ?? null)
            || !hash_equals((string)$payment['external_reference'], $result['external_reference'])
            || !is_string($result['preference_id'] ?? null)
            || !hash_equals((string)$payment['mp_preference_id'], $result['preference_id'])) {
            throw new RuntimeException('Mercado Pago payment did not match this transaction.');
        }
        try {
            $providerAmountMinor = $this->amountToMinorUnits($result['transaction_amount'] ?? null);
            $localAmountMinor = $this->amountToMinorUnits((string)$payment['amount']);
        } catch (InvalidArgumentException) {
            throw new RuntimeException('Mercado Pago returned an invalid payment amount.');
        }
        $currency = strtoupper((string)($result['currency_id'] ?? ''));
        if ($providerAmountMinor !== $localAmountMinor
            || !hash_equals(strtoupper((string)$payment['currency_id']), $currency)) {
            throw new RuntimeException('Mercado Pago payment amount or currency did not match this transaction.');
        }
        $status = (string)($result['status'] ?? '');
        if (!in_array($status, ['approved', 'pending', 'in_process', 'rejected', 'cancelled', 'refunded', 'charged_back'], true)) {
            throw new RuntimeException('Mercado Pago returned an unknown payment status.');
        }
        $previous = (string)$payment['status'];
        if (!$this->isStatusTransitionAllowed($previous, $status)) {
            // A stale provider read must not move a payment backwards from a
            // verified terminal state (for example approved -> pending).
            return [
                'payment_public_id' => $paymentPublicId,
                'status' => $previous,
                'detail' => (string)($payment['status_detail'] ?? ''),
                'verified' => true,
            ];
        }
        // Always compare against the observed state, even when this provider
        // response repeats it: another verifier may have committed a newer state
        // while the outbound request was in flight.
        $update = $this->db->prepare('UPDATE module_mercadopago_payments SET mp_payment_id = :mp_id, status = :status, status_detail = :detail, updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :ws AND public_id = :pid AND status = :expected_status');
        $detail = substr((string)($result['status_detail'] ?? ''), 0, 128);
        $update->execute([':mp_id' => $mpPaymentId, ':status' => $status, ':detail' => $detail, ':ws' => $this->workspaceId, ':pid' => $paymentPublicId, ':expected_status' => $previous]);
        if ($update->rowCount() !== 1) {
            // Another writer won. Its current row is authoritative; do not
            // report the stale response as the payment's current state.
            $current = $this->db->prepare('SELECT status, status_detail FROM module_mercadopago_payments WHERE workspace_id = :ws AND public_id = :pid LIMIT 1');
            $current->execute([':ws' => $this->workspaceId, ':pid' => $paymentPublicId]);
            $currentRow = $current->fetch(PDO::FETCH_ASSOC);
            if (!is_array($currentRow)) {
                throw new RuntimeException('Payment disappeared during status reconciliation.');
            }
            return ['payment_public_id' => $paymentPublicId, 'status' => (string)$currentRow['status'], 'detail' => (string)($currentRow['status_detail'] ?? ''), 'verified' => true];
        }
        if ($previous !== $status || (string)($payment['mp_payment_id'] ?? '') !== $mpPaymentId) {
            $this->logAudit($paymentPublicId, $previous, $status, ['event' => 'status_checked', 'provider_payment_id' => $mpPaymentId]);
        }
        return ['payment_public_id' => $paymentPublicId, 'status' => $status, 'detail' => substr((string)($result['status_detail'] ?? ''), 0, 128), 'verified' => true];
    }


    private function isStatusTransitionAllowed(string $current, string $next): bool
    {
        if ($current === $next) {
            return true;
        }
        return match ($current) {
            'pending' => in_array($next, ['pending', 'in_process', 'approved', 'rejected', 'cancelled'], true),
            'in_process' => in_array($next, ['in_process', 'approved', 'rejected', 'cancelled'], true),
            'approved' => in_array($next, ['refunded', 'charged_back'], true),
            'refunded' => $next === 'charged_back',
            'rejected', 'cancelled', 'charged_back' => false,
            default => false,
        };
    }

    /** @return array{token:string,sandbox:bool} */
    private function getCredentials(): array
    {
        $stmt = $this->db->prepare('SELECT access_token_encrypted, sandbox_mode FROM module_mercadopago_settings WHERE workspace_id = :ws AND is_active = 1 LIMIT 1');
        $stmt->execute([':ws' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || (string)($row['access_token_encrypted'] ?? '') === '') {
            throw new RuntimeException('Mercado Pago is not configured for this workspace.');
        }
        return ['token' => $this->decryptCredential((string)$row['access_token_encrypted']), 'sandbox' => (bool)($row['sandbox_mode'] ?? true)];
    }

    /** @param array<string, string> $query @return array<string, mixed> */
    private function request(string $method, string $path, string $token, ?array $payload, array $query = []): array
    {
        if (!preg_match('#^/(?:checkout/preferences|v1/payments/[0-9]{1,32}|v1/payments/search)$#', $path)) {
            throw new RuntimeException('Mercado Pago request path is not allowed.');
        }
        $headers = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        try {
            $response = $this->httpTransport !== null
                ? ($this->httpTransport)($method, self::API_BASE . $path . $this->queryString($path, $query), $headers, $body)
                : $this->sendHttpRequest($method, self::API_BASE . $path . $this->queryString($path, $query), $headers, $body);
        } catch (\Throwable $e) {
            // Do not forward transport exceptions: wrappers can include request headers/tokens.
            throw new RuntimeException('Mercado Pago request failed. Check connectivity and try again.');
        }
        $status = (int)($response['status'] ?? 0);
        $raw = (string)($response['body'] ?? '');
        if (strlen($raw) > 1048576) {
            throw new RuntimeException('Mercado Pago response exceeded the allowed size.');
        }
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Mercado Pago returned an invalid response.');
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Mercado Pago returned an invalid response.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Mercado Pago rejected the request (HTTP ' . ($status >= 400 && $status < 600 ? $status : 'error') . ').');
        }
        return $decoded;
    }

    /** @param array<string, string> $query */
    private function queryString(string $path, array $query): string
    {
        if ($query === []) {
            return '';
        }
        if ($path !== '/v1/payments/search') {
            throw new RuntimeException('Query parameters are not allowed for this Mercado Pago endpoint.');
        }
        $allowed = ['external_reference', 'sort', 'criteria', 'range', 'begin_date', 'end_date', 'limit', 'offset'];
        foreach ($query as $key => $value) {
            if (!in_array($key, $allowed, true) || !is_string($value) || strlen($value) > 256 || preg_match('/[\r\n]/', $value)) {
                throw new RuntimeException('Mercado Pago search parameters are invalid.');
            }
        }
        return '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<string, string> $headers @return array{status:int,body:string} */
    private function sendHttpRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $body ?? '',
            'timeout' => 15,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
        ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $raw = @file_get_contents($url, false, $context, 0, 1048577);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        foreach ($responseHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) {
                $status = (int)$matches[1];
            }
        }
        if (!is_string($raw) || $status === 0) {
            throw new RuntimeException('Mercado Pago request failed.');
        }
        return ['status' => $status, 'body' => $raw];
    }

    private function isMercadoPagoCheckoutUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        return ($parts['scheme'] ?? '') === 'https'
            && ($host === 'mercadopago.com' || str_ends_with($host, '.mercadopago.com') || $host === 'mercadopago.com.br' || str_ends_with($host, '.mercadopago.com.br'))
            && !isset($parts['user']) && !isset($parts['pass'])
            && (!isset($parts['port']) || (int)$parts['port'] === 443);
    }

    private function encryptCredential(string $plaintext): string
    {
        $key = $this->credentialKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'crm.module.crm.mercadopago.credentials', 16);
        if (!is_string($cipher) || strlen($tag) !== 16) {
            throw new RuntimeException('Mercado Pago credential encryption failed.');
        }
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    private function decryptCredential(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'v1:')) {
            throw new RuntimeException('Stored Mercado Pago credential is not encrypted with a supported format.');
        }
        $payload = base64_decode(substr($encrypted, 3), true);
        if (!is_string($payload) || strlen($payload) < 29) {
            throw new RuntimeException('Stored Mercado Pago credential is invalid.');
        }
        $plain = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', $this->credentialKey(), OPENSSL_RAW_DATA, substr($payload, 0, 12), substr($payload, 12, 16), 'crm.module.crm.mercadopago.credentials');
        if (!is_string($plain) || $plain === '') {
            throw new RuntimeException('Stored Mercado Pago credential cannot be decrypted.');
        }
        return $plain;
    }

    private function credentialKey(): string
    {
        $configured = trim((string)(getenv('APP_KEY') ?: ($_SERVER['APP_KEY'] ?? $_ENV['APP_KEY'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/i', $configured) || !function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('APP_KEY must be configured as a 32-byte hexadecimal key to use Mercado Pago credentials.');
        }
        $master = hex2bin($configured);
        if (!is_string($master) || strlen($master) !== 32) {
            throw new RuntimeException('APP_KEY is invalid.');
        }
        return hash_hkdf('sha256', $master, 32, 'crm.module.crm.mercadopago.credentials', 'TropaTT CRM');
    }

    /** Parse a decimal amount without doing money arithmetic in binary floats. */
    private function amountToMinorUnits(mixed $amount): int
    {
        if (is_float($amount)) {
            if (!is_finite($amount)) {
                throw new InvalidArgumentException('A valid positive payment amount is required.');
            }
            $amount = rtrim(rtrim(sprintf('%.12F', $amount), '0'), '.');
        }
        if (!is_string($amount) && !is_int($amount)) {
            throw new InvalidArgumentException('A valid positive payment amount is required.');
        }
        $value = trim((string)$amount);
        if (!preg_match('/^(?:0|[1-9]\d{0,11})(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('A valid payment amount with at most two decimal places is required.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $minor = ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
        if ($minor < 1 || $minor > 99999999999999) {
            throw new InvalidArgumentException('A valid positive payment amount is required.');
        }
        return $minor;
    }

    private function minorUnitsToMajorString(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function logAudit(string $publicId, ?string $from, string $to, array $safeDetails): void
    {
        $stmt = $this->db->prepare('INSERT INTO module_mercadopago_audit_log (payment_public_id, from_status, to_status, raw_webhook_payload, created_at) VALUES (:pid, :from_status, :to_status, :details, CURRENT_TIMESTAMP)');
        $stmt->execute([':pid' => $publicId, ':from_status' => $from, ':to_status' => $to, ':details' => json_encode($safeDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
