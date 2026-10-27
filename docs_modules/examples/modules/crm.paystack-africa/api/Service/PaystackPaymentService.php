<?php

declare(strict_types=1);

namespace Module\Crm\PaystackAfrica\Service;

use PDO;
use RuntimeException;

/** Paystack's official transaction initialize and verify API. */
final class PaystackPaymentService
{
    private const API_BASE = 'https://api.paystack.co';
    private const CURRENCIES = ['NGN', 'GHS', 'ZAR', 'KES', 'USD'];

    /** @var callable|null */
    private $transport;

    public function __construct(
        private readonly PDO $db,
        private readonly int $workspaceId,
        private readonly ?string $secretKey = null,
        ?callable $transport = null
    ) {
        $this->transport = $transport;
    }

    /** Reserve the workspace/reference before calling Paystack, then reconcile the result. */
    public function initializeTransaction(array $data): array
    {
        $this->assertWorkspace();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('A valid customer email is required.');
        }
        $currency = strtoupper(trim((string)($data['currency'] ?? 'NGN')));
        if (!in_array($currency, self::CURRENCIES, true)) {
            throw new \InvalidArgumentException('Unsupported payment currency.');
        }
        $amountMinor = $this->toMinorUnits($data['amount'] ?? null);
        $reference = trim((string)($data['reference'] ?? ''));
        if ($reference === '') {
            $reference = 'pstk-' . bin2hex(random_bytes(16));
        }
        if (strlen($reference) > 100 || !preg_match('/^[A-Za-z0-9.=-]+$/', $reference)) {
            throw new \InvalidArgumentException('Invalid transaction reference.');
        }
        // Reject an unconfigured workspace before reserving a payment reference.
        $this->resolveSecretKey();
        $payload = [
            'email' => $email,
            'amount' => $amountMinor,
            'currency' => $currency,
            'reference' => $reference,
        ];
        $publicId = 'txn_pstk_' . bin2hex(random_bytes(8));
        $amountMajor = $this->toMajorUnits($amountMinor);
        try {
            $reservation = $this->db->prepare("INSERT INTO module_paystack_transactions
                (public_id, workspace_id, reference, paystack_access_code, authorization_url, customer_email, amount_major, currency, status, created_at)
                VALUES (:pid, :ws_id, :ref, NULL, '', :email, :amt, :curr, 'initializing', CURRENT_TIMESTAMP)");
            $reservation->execute([
                'pid' => $publicId,
                'ws_id' => $this->workspaceId,
                'ref' => $reference,
                'email' => $email,
                'amt' => $amountMajor,
                'curr' => $currency,
            ]);
        } catch (\PDOException $exception) {
            // A retry must keep the same reference. Replay a stored checkout, or
            // reconcile an uncertain initialization with Paystack; never POST a
            // second initialization for a reference that may already exist there.
            $existing = $this->db->prepare('SELECT public_id, reference, customer_email, amount_major, currency, status, authorization_url FROM module_paystack_transactions WHERE workspace_id = :ws AND reference = :ref LIMIT 1');
            $existing->execute(['ws' => $this->workspaceId, 'ref' => $reference]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                if (!hash_equals(strtolower((string)$row['customer_email']), $email)
                    || $this->toMinorUnits((string)$row['amount_major']) !== $amountMinor
                    || !hash_equals(strtoupper((string)$row['currency']), $currency)) {
                    throw new RuntimeException('This reference belongs to a different payment request.', 0, $exception);
                }
                $checkoutUrl = trim((string)($row['authorization_url'] ?? ''));
                if ((string)$row['status'] === 'initializing') {
                    throw new RuntimeException('This reference is already being initialized. Verify it before retrying.', 0, $exception);
                }
                if ($checkoutUrl !== '' && self::isPaystackCheckoutUrl($checkoutUrl) && (string)$row['status'] === 'pending') {
                    return [
                        'success' => true,
                        'public_id' => (string)$row['public_id'],
                        'reference' => $reference,
                        'access_code' => '',
                        'authorization_url' => $checkoutUrl,
                        'status' => (string)$row['status'],
                        'replayed' => true,
                    ];
                }
                $verified = $this->verifyTransaction($reference);
                return [
                    'success' => true,
                    'public_id' => (string)$row['public_id'],
                    'reference' => $reference,
                    'access_code' => '',
                    'authorization_url' => '',
                    'status' => (string)$verified['status'],
                    'replayed' => true,
                    'reconciled' => true,
                ];
            }
            throw $exception;
        }

        try {
            $response = $this->request('POST', '/transaction/initialize', $payload);
        } catch (\Throwable $exception) {
            $this->markInitializationUnknown($reference);
            throw new RuntimeException('Paystack initialization outcome is uncertain; verify this reference before retrying.', 0, $exception);
        }
        $providerData = $response['data'] ?? null;
        if (!is_array($providerData)
            || !is_string($providerData['reference'] ?? null)
            || !hash_equals($reference, $providerData['reference'])
            || !is_string($providerData['access_code'] ?? null)
            || $providerData['access_code'] === ''
            || !is_string($providerData['authorization_url'] ?? null)
            || !self::isPaystackCheckoutUrl($providerData['authorization_url'])) {
            $this->markInitializationUnknown($reference);
            throw new RuntimeException('Paystack initialization outcome is uncertain; verify this reference before retrying.');
        }

        $stmt = $this->db->prepare("UPDATE module_paystack_transactions
            SET paystack_access_code = :acc, authorization_url = :url, status = 'pending', updated_at = CURRENT_TIMESTAMP
            WHERE workspace_id = :ws_id AND reference = :ref AND status = 'initializing'");
        $stmt->execute([
            'acc' => $providerData['access_code'],
            'url' => $providerData['authorization_url'],
            'ws_id' => $this->workspaceId,
            'ref' => $reference,
        ]);
        if ($stmt->rowCount() !== 1) {
            $this->markInitializationUnknown($reference);
            throw new RuntimeException('Paystack accepted the request but its result could not be recorded; verify the reference before retrying.');
        }
        $this->logAudit($reference, 'charge.initiated', ['amount_minor' => $amountMinor, 'currency' => $currency]);

        return [
            'success' => true,
            'public_id' => $publicId,
            'reference' => $reference,
            'access_code' => $providerData['access_code'],
            'authorization_url' => $providerData['authorization_url'],
            'status' => 'pending',
        ];
    }

    /** Verify an existing workspace transaction against Paystack's API. */
    public function verifyTransaction(string $reference): array
    {
        $this->assertWorkspace();
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > 100 || !preg_match('/^[A-Za-z0-9.=-]+$/', $reference)) {
            throw new \InvalidArgumentException('Invalid transaction reference.');
        }
        $stmt = $this->db->prepare('SELECT amount_major, currency, status FROM module_paystack_transactions WHERE workspace_id = :ws AND reference = :ref LIMIT 1');
        $stmt->execute(['ws' => $this->workspaceId, 'ref' => $reference]);
        $local = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($local)) {
            throw new RuntimeException('Transaction reference was not found in this workspace.');
        }

        $response = $this->request('GET', '/transaction/verify/' . rawurlencode($reference));
        $providerData = $response['data'] ?? null;
        if (!is_array($providerData)
            || !is_string($providerData['reference'] ?? null)
            || !hash_equals($reference, $providerData['reference'])
            || !is_string($providerData['status'] ?? null)
            || !is_int($providerData['amount'] ?? null)
            || !is_string($providerData['currency'] ?? null)) {
            throw new RuntimeException('Paystack returned an invalid verification response.');
        }

        $expectedMinor = $this->toMinorUnits((string)$local['amount_major']);
        $verifiedCurrency = strtoupper($providerData['currency']);
        $amountMatches = (int)$providerData['amount'] === $expectedMinor;
        $currencyMatches = hash_equals(strtoupper((string)$local['currency']), $verifiedCurrency);
        $providerStatus = strtolower($providerData['status']);
        $localStatus = strtolower((string)($local['status'] ?? 'pending'));
        if ($localStatus === 'success') {
            // Do not downgrade a payment already confirmed by Paystack because a
            // stale or inconsistent later read reports a different state.
            $status = 'success';
        } elseif ($providerStatus === 'success' && $amountMatches && $currencyMatches) {
            $status = 'success';
        } elseif (!$amountMatches || !$currencyMatches) {
            $status = 'review';
        } elseif (in_array($providerStatus, ['failed', 'abandoned'], true)) {
            $status = 'failed';
        } else {
            $status = 'pending';
        }

        $channel = isset($providerData['channel']) && is_string($providerData['channel'])
            && preg_match('/^[a-z0-9_-]{1,64}$/i', $providerData['channel']) ? $providerData['channel'] : null;
        $paidAt = isset($providerData['paid_at']) && is_string($providerData['paid_at'])
            && strtotime($providerData['paid_at']) !== false ? gmdate('Y-m-d H:i:s', strtotime($providerData['paid_at'])) : null;
        $update = $this->db->prepare('UPDATE module_paystack_transactions SET status = :status, channel = COALESCE(:channel, channel), paid_at = COALESCE(:paid_at, paid_at), updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :ws AND reference = :ref');
        $update->execute([
            'status' => $status,
            'channel' => $channel,
            'paid_at' => $paidAt,
            'ws' => $this->workspaceId,
            'ref' => $reference,
        ]);
        $this->logAudit($reference, 'charge.verified', [
            'status' => $status,
            'amount_matches' => $amountMatches,
            'currency_matches' => $currencyMatches,
        ]);

        return [
            'reference' => $reference,
            'status' => $status,
            'verified' => true,
            'channel' => $channel,
            'paid_at' => $paidAt,
            'amount_matches' => $amountMatches,
            'currency_matches' => $currencyMatches,
        ];
    }

    /** Verify Paystack webhook signature using HMAC-SHA512. */
    public function verifyWebhookSignature(string $signatureHeader, string $requestBody, string $secretKey): bool
    {
        return $signatureHeader !== '' && hash_equals(hash_hmac('sha512', $requestBody, $secretKey), $signatureHeader);
    }

    /** Validate and apply a signed Paystack charge event exactly once per workspace. */
    public function processWebhook(string $signatureHeader, string $rawBody): array
    {
        $this->assertWorkspace();
        if ($rawBody === '' || strlen($rawBody) > 262144) {
            throw new \InvalidArgumentException('Invalid Paystack webhook body.');
        }
        $secret = $this->resolveSecretKey();
        if (!$this->verifyWebhookSignature($signatureHeader, $rawBody, $secret)) {
            throw new RuntimeException('INVALID_WEBHOOK_SIGNATURE');
        }
        try {
            $payload = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('Invalid Paystack webhook JSON.');
        }
        if (!is_array($payload) || !in_array($payload['event'] ?? null, ['charge.success', 'charge.failed'], true)
            || !is_array($payload['data'] ?? null)) {
            throw new \InvalidArgumentException('Unsupported Paystack webhook event.');
        }

        $eventName = (string)$payload['event'];
        $data = $payload['data'];
        $reference = trim((string)($data['reference'] ?? ''));
        $providerStatus = strtolower(trim((string)($data['status'] ?? '')));
        $currency = strtoupper(trim((string)($data['currency'] ?? '')));
        $amountRaw = $data['amount'] ?? null;
        if ($reference === '' || strlen($reference) > 100 || !preg_match('/^[A-Za-z0-9.=-]+$/', $reference)
            || !in_array($currency, self::CURRENCIES, true)
            || (!is_int($amountRaw) && !(is_string($amountRaw) && ctype_digit($amountRaw) && strlen($amountRaw) <= 15))
            || !in_array($providerStatus, ['success', 'failed'], true)
            || ($eventName === 'charge.success' && $providerStatus !== 'success')
            || ($eventName === 'charge.failed' && $providerStatus !== 'failed')) {
            throw new \InvalidArgumentException('Invalid Paystack transaction event data.');
        }
        if (is_int($amountRaw) && $amountRaw > 999999999999999) {
            throw new \InvalidArgumentException('Invalid Paystack transaction amount.');
        }
        $amountMinor = (int)$amountRaw;
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Invalid Paystack transaction amount.');
        }

        $eventHash = hash('sha256', implode("\0", [
            $eventName,
            $reference,
            is_scalar($data['id'] ?? null) ? (string)$data['id'] : '',
            $providerStatus,
            (string)$amountMinor,
            $currency,
        ]));
        $this->db->beginTransaction();
        try {
            $find = $this->db->prepare('SELECT amount_major, currency, status FROM module_paystack_transactions WHERE workspace_id = :workspace_id AND reference = :reference LIMIT 1');
            $find->execute(['workspace_id' => $this->workspaceId, 'reference' => $reference]);
            $local = $find->fetch(PDO::FETCH_ASSOC);
            if (!is_array($local)) {
                throw new RuntimeException('PAYSTACK_TRANSACTION_NOT_FOUND');
            }

            $expectedMinor = $this->toMinorUnits((string)$local['amount_major']);
            $amountMatches = $amountMinor === $expectedMinor;
            $currencyMatches = hash_equals(strtoupper((string)$local['currency']), $currency);
            $matches = $amountMatches && $currencyMatches;
            $outcome = !$matches ? 'review' : ($eventName === 'charge.success' ? 'success' : 'failed');
            $event = $this->db->prepare('INSERT INTO module_paystack_webhook_events (workspace_id, event_hash, event_name, transaction_reference, outcome, received_at) VALUES (:workspace_id, :event_hash, :event_name, :reference, :outcome, CURRENT_TIMESTAMP)');
            try {
                $event->execute([
                    'workspace_id' => $this->workspaceId,
                    'event_hash' => $eventHash,
                    'event_name' => $eventName,
                    'reference' => $reference,
                    'outcome' => $outcome,
                ]);
            } catch (\PDOException $e) {
                if (($e->errorInfo[0] ?? '') !== '23000') {
                    throw $e;
                }
                $this->db->rollBack();
                return ['accepted' => true, 'duplicate' => true, 'outcome' => 'duplicate'];
            }

            if ($matches) {
                $status = $eventName === 'charge.success' ? 'success' : 'failed';
                $update = $this->db->prepare("UPDATE module_paystack_transactions SET status = CASE WHEN status = 'success' THEN 'success' ELSE :status END, paid_at = CASE WHEN :paid = 1 THEN COALESCE(paid_at, CURRENT_TIMESTAMP) ELSE paid_at END, updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :workspace_id AND reference = :reference");
                $update->execute([
                    'status' => $status,
                    'paid' => $status === 'success' ? 1 : 0,
                    'workspace_id' => $this->workspaceId,
                    'reference' => $reference,
                ]);
            } else {
                $review = $this->db->prepare("UPDATE module_paystack_transactions SET status = CASE WHEN status = 'success' THEN 'success' ELSE 'review' END, updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :workspace_id AND reference = :reference");
                $review->execute(['workspace_id' => $this->workspaceId, 'reference' => $reference]);
            }
            $this->db->commit();
            return [
                'accepted' => true,
                'duplicate' => false,
                'outcome' => $outcome,
                'amount_matches' => $amountMatches,
                'currency_matches' => $currencyMatches,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** The response is reduced to a boolean; transaction/customer data is discarded. */
    public function testConnection(): array
    {
        $this->assertWorkspace();
        $this->request('GET', '/transaction?perPage=1');
        return ['connected' => true];
    }

    /** @return list<array<string, mixed>> */
    public function listTransactions(int $limit = 30): array
    {
        $this->assertWorkspace();
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare('SELECT public_id, reference, customer_email, amount_major, currency, status, channel, paid_at, authorization_url, created_at FROM module_paystack_transactions WHERE workspace_id = :workspace_id ORDER BY id DESC LIMIT ' . $limit);
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $secret = $this->resolveSecretKey();
        $url = self::API_BASE . $path;
        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers = ['Authorization: Bearer ' . $secret, 'Accept: application/json'];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        try {
            $result = $this->transport !== null
                ? ($this->transport)($method, $url, $headers, $body, 12)
                : $this->streamRequest($method, $url, $headers, $body);
        } catch (\Throwable) {
            throw new RuntimeException('Unable to contact Paystack. Please retry later.');
        }
        if (!is_array($result) || !is_int($result['status'] ?? null) || !is_string($result['body'] ?? null)) {
            throw new RuntimeException('Paystack returned an invalid response.');
        }
        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new RuntimeException('Paystack rejected the payment request (HTTP ' . $result['status'] . ').');
        }
        $decoded = json_decode($result['body'], true);
        if (!is_array($decoded) || ($decoded['status'] ?? null) !== true || !is_array($decoded['data'] ?? null)) {
            throw new RuntimeException('Paystack returned an invalid response.');
        }
        return $decoded;
    }

    /** @param string[] $headers */
    private function streamRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'timeout' => 12,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'protocol_version' => 1.1,
        ], 'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ]]);
        // The explicit length bounds memory even if the remote endpoint or a
        // compromised intermediary returns an unexpectedly large response.
        $responseBody = @file_get_contents($url, false, $context, 0, 1048577);
        if (!is_string($responseBody) || strlen($responseBody) > 1048576) {
            throw new RuntimeException('Paystack request failed.');
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches)) {
                $status = (int)$matches[1];
            }
        }
        return ['status' => $status, 'body' => $responseBody];
    }

    private function resolveSecretKey(): string
    {
        $key = trim((string)($this->secretKey ?? ''));
        if ($key === '') {
            $stmt = $this->db->prepare('SELECT secret_key_encrypted FROM module_paystack_settings WHERE workspace_id = :ws LIMIT 1');
            $stmt->execute(['ws' => $this->workspaceId]);
            $encrypted = $stmt->fetchColumn();
            if (is_string($encrypted) && $encrypted !== '') {
                $key = $this->decryptCredential($encrypted);
            }
        }
        if ($key === '' || !preg_match('/^sk_(test|live)_[A-Za-z0-9]{20,}$/', $key)) {
            throw new RuntimeException('Paystack is not configured for this workspace.');
        }
        return $key;
    }

    private function decryptCredential(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'v1:')) {
            throw new RuntimeException('Stored Paystack credential has an unsupported format.');
        }
        $payload = base64_decode(substr($encrypted, 3), true);
        if (!is_string($payload) || strlen($payload) < 29 || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('Stored Paystack credential is invalid.');
        }
        $configuredKey = trim((string)(getenv('APP_KEY') ?: ($_SERVER['APP_KEY'] ?? $_ENV['APP_KEY'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/i', $configuredKey)) {
            throw new RuntimeException('APP_KEY is unavailable for Paystack credential decryption.');
        }
        $masterKey = hex2bin($configuredKey);
        if (!is_string($masterKey)) {
            throw new RuntimeException('APP_KEY is invalid.');
        }
        $encryptionKey = hash_hkdf('sha256', $masterKey, 32, 'crm.module.crm.paystack-africa.credentials', 'TropaTT CRM');
        $decrypted = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', $encryptionKey, OPENSSL_RAW_DATA, substr($payload, 0, 12), substr($payload, 12, 16));
        if (!is_string($decrypted) || $decrypted === '') {
            throw new RuntimeException('Stored Paystack credential cannot be decrypted.');
        }
        return $decrypted;
    }

    private function toMinorUnits(mixed $amount): int
    {
        $value = trim((string)$amount);
        if (!preg_match('/^(?:0|[1-9]\d{0,12})(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Amount must be positive and have at most two decimal places.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $minor = ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
        if ($minor <= 0 || $minor > 999999999999999) {
            throw new \InvalidArgumentException('Amount is outside the supported range.');
        }
        return $minor;
    }

    private function toMajorUnits(int $amountMinor): string
    {
        return intdiv($amountMinor, 100) . '.' . str_pad((string)($amountMinor % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function isPaystackCheckoutUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts)
            && strtolower((string)($parts['scheme'] ?? '')) === 'https'
            && strtolower((string)($parts['host'] ?? '')) === 'checkout.paystack.com'
            && (!isset($parts['port']) || (int)$parts['port'] === 443)
            && isset($parts['path']) && $parts['path'] !== ''
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment']);
    }

    private function assertWorkspace(): void
    {
        if ($this->workspaceId <= 0) {
            throw new RuntimeException('An authorized workspace is required.');
        }
    }

    private function markInitializationUnknown(string $reference): void
    {
        $stmt = $this->db->prepare("UPDATE module_paystack_transactions SET status = 'initialization_unknown', updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :ws AND reference = :ref AND status IN ('initializing', 'initialization_unknown')");
        $stmt->execute(['ws' => $this->workspaceId, 'ref' => $reference]);
    }

    private function logAudit(string $reference, string $event, array $payload): void
    {
        $stmt = $this->db->prepare('INSERT INTO module_paystack_audit_log (workspace_id, transaction_reference, event_name, payload_json, created_at) VALUES (:workspace_id, :ref, :evt, :payload, CURRENT_TIMESTAMP)');
        $stmt->execute([
            'workspace_id' => $this->workspaceId,
            'ref' => $reference,
            'evt' => $event,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }
}
