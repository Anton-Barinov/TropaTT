<?php

declare(strict_types=1);

namespace Module\Crm\PaystackAfrica\Service;

/** Paystack HTTP integration. Provider secrets are only held in memory and never persisted in logs. */
class PaystackPaymentService
{
    private const API = 'https://api.paystack.co';
    private \PDO $db;
    private int $workspaceId;
    private ?string $secretKey;
    /** @var callable|null */
    private $transport;

    public function __construct(\PDO $db, int $workspaceId = 1, ?string $secretKey = null, ?callable $transport = null)
    {
        $this->db = $db;
        $this->workspaceId = $workspaceId;
        $this->secretKey = $secretKey ?? (($env = getenv('PAYSTACK_SECRET_KEY')) !== false ? $env : null);
        $this->transport = $transport;
    }

    public function initializeTransaction(array $data): array
    {
        $this->assertConfigured();
        $this->assertWorkspace();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $amountMajor = $this->amount($data['amount'] ?? null);
        $currency = strtoupper(trim((string)($data['currency'] ?? 'NGN')));
        $reference = trim((string)($data['reference'] ?? 'pstk_' . bin2hex(random_bytes(12))));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $amountMajor <= 0 || !preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('Valid email, positive amount, and three-letter currency are required');
        }
        if ($reference === '' || strlen($reference) > 128) {
            throw new \InvalidArgumentException('A transaction reference of 1 to 128 characters is required');
        }
        $minor = (int)round($amountMajor * 100);
        if ($minor < 1 || abs($amountMajor - ($minor / 100)) > 0.000001) {
            throw new \InvalidArgumentException('Amount must have no more than two decimal places');
        }

        $payload = ['email' => $email, 'amount' => $minor, 'currency' => $currency, 'reference' => $reference];
        $dataOut = $this->request('POST', self::API . '/transaction/initialize', $payload);
        if (!is_array($dataOut) || ($dataOut['reference'] ?? null) !== $reference
            || empty($dataOut['access_code']) || !filter_var($dataOut['authorization_url'] ?? null, FILTER_VALIDATE_URL)
            || parse_url((string)$dataOut['authorization_url'], PHP_URL_HOST) !== 'checkout.paystack.com') {
            throw new \RuntimeException('Paystack returned an invalid initialization response');
        }

        $publicId = 'txn_pstk_' . bin2hex(random_bytes(8));
        $stmt = $this->db->prepare("INSERT INTO module_paystack_transactions
            (public_id, workspace_id, reference, paystack_access_code, authorization_url, customer_email, amount_major, currency, status, created_at)
            VALUES (:pid, :ws, :ref, :access, :url, :email, :amount, :currency, 'pending', CURRENT_TIMESTAMP)");
        $stmt->execute(['pid' => $publicId, 'ws' => $this->workspaceId, 'ref' => $reference,
            'access' => (string)$dataOut['access_code'], 'url' => (string)$dataOut['authorization_url'],
            'email' => $email, 'amount' => $amountMajor, 'currency' => $currency]);
        $this->logAudit($reference, 'charge.initiated', ['amount' => $amountMajor, 'currency' => $currency]);
        return ['success' => true, 'public_id' => $publicId, 'reference' => $reference,
            'access_code' => (string)$dataOut['access_code'], 'authorization_url' => (string)$dataOut['authorization_url'], 'status' => 'pending'];
    }

    public function verifyTransaction(string $reference): array
    {
        $this->assertConfigured();
        $this->assertWorkspace();
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > 128) {
            throw new \InvalidArgumentException('A valid transaction reference is required');
        }
        $stmt = $this->db->prepare('SELECT amount_major, currency FROM module_paystack_transactions WHERE workspace_id = :ws AND reference = :ref LIMIT 1');
        $stmt->execute(['ws' => $this->workspaceId, 'ref' => $reference]);
        $local = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$local) {
            throw new \RuntimeException('Transaction reference not found');
        }
        $data = $this->request('GET', self::API . '/transaction/verify/' . rawurlencode($reference));
        if (!is_array($data) || ($data['reference'] ?? null) !== $reference || !isset($data['status'], $data['amount'], $data['currency'])) {
            throw new \RuntimeException('Paystack returned an invalid verification response');
        }
        $amountMatches = (int)$data['amount'] === (int)round((float)$local['amount_major'] * 100);
        $currencyMatches = strtoupper((string)$data['currency']) === strtoupper((string)$local['currency']);
        $providerStatus = strtolower((string)$data['status']);
        $verifiedStatus = ($providerStatus === 'success' && $amountMatches && $currencyMatches) ? 'success'
            : (in_array($providerStatus, ['failed', 'abandoned'], true) ? $providerStatus : 'pending');
        $paidAt = $verifiedStatus === 'success' ? ($data['paid_at'] ?? null) : null;
        $update = $this->db->prepare('UPDATE module_paystack_transactions SET status = :status, channel = :channel, gateway_response = :response, paid_at = :paid_at, updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :ws AND reference = :ref');
        $update->execute(['status' => $verifiedStatus, 'channel' => isset($data['channel']) ? (string)$data['channel'] : null,
            'response' => isset($data['gateway_response']) ? substr((string)$data['gateway_response'], 0, 255) : null,
            'paid_at' => $paidAt, 'ws' => $this->workspaceId, 'ref' => $reference]);
        $this->logAudit($reference, 'charge.verified', ['status' => $verifiedStatus, 'amount_matches' => $amountMatches, 'currency_matches' => $currencyMatches]);
        return ['reference' => $reference, 'status' => $verifiedStatus, 'verified' => true,
            'amount_matches' => $amountMatches, 'currency_matches' => $currencyMatches,
            'gateway_response' => isset($data['gateway_response']) ? (string)$data['gateway_response'] : null];
    }

    public function verifyWebhookSignature(string $signatureHeader, string $requestBody, string $secretKey): bool
    {
        return hash_equals(hash_hmac('sha512', $requestBody, $secretKey), $signatureHeader);
    }

    public function recordTransactionOutcome(string $reference, string $status, ?string $channel = null, ?string $gatewayResponse = null): array
    {
        if (!in_array($status, ['success', 'failed', 'abandoned', 'pending'], true)) {
            throw new \InvalidArgumentException('Unsupported transaction status');
        }
        $find = $this->db->prepare('SELECT id, status FROM module_paystack_transactions WHERE workspace_id = :ws AND reference = :ref LIMIT 1');
        $find->execute(['ws' => $this->workspaceId, 'ref' => $reference]);
        $row = $find->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException('Transaction reference not found');
        }
        $from = (string)$row['status'];
        if ($from === $status) return ['reference' => $reference, 'status' => $status, 'unchanged' => true];
        $stmt = $this->db->prepare('UPDATE module_paystack_transactions SET status = :status, channel = COALESCE(:channel, channel), gateway_response = COALESCE(:response, gateway_response), updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute(['status' => $status, 'channel' => $channel, 'response' => $gatewayResponse, 'id' => $row['id']]);
        $this->logAudit($reference, 'charge.' . $status, ['channel' => $channel]);
        return ['reference' => $reference, 'from_status' => $from, 'to_status' => $status, 'success' => $status === 'success'];
    }

    private function request(string $method, string $url, ?array $payload = null): array
    {
        $body = $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR);
        try {
            if ($this->transport !== null) {
                $raw = ($this->transport)($method, $url, ['Authorization: Bearer ' . $this->secretKey, 'Content-Type: application/json'], $body, 12);
            } else {
                if (!function_exists('curl_init')) throw new \RuntimeException('Paystack transport unavailable');
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->secretKey, 'Content-Type: application/json'],
                    CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5]);
                if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                $responseBody = curl_exec($ch);
                $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $raw = ['status' => $httpStatus, 'body' => $responseBody];
                $curlError = curl_errno($ch);
                curl_close($ch);
                if ($curlError !== 0) throw new \RuntimeException('network error');
            }
            if (!is_array($raw) || (int)($raw['status'] ?? 0) < 200 || (int)$raw['status'] >= 300 || !is_string($raw['body'] ?? null)) {
                throw new \RuntimeException('provider rejected request');
            }
            $decoded = json_decode($raw['body'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || ($decoded['status'] ?? false) !== true || !is_array($decoded['data'] ?? null)) {
                throw new \RuntimeException('provider response invalid');
            }
            return $decoded['data'];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Paystack request failed; verify provider configuration and try again');
        }
    }

    private function assertConfigured(): void
    {
        if ($this->secretKey === null || trim($this->secretKey) === '') throw new \RuntimeException('Paystack is not configured');
    }
    private function assertWorkspace(): void
    {
        if ($this->workspaceId <= 0) throw new \RuntimeException('A valid workspace is required');
    }
    private function amount($amount): float
    {
        if (!is_numeric($amount) || !is_finite((float)$amount)) throw new \InvalidArgumentException('Amount must be numeric');
        return (float)$amount;
    }
    private function logAudit(string $reference, string $event, array $payload): void
    {
        $stmt = $this->db->prepare('INSERT INTO module_paystack_audit_log (transaction_reference, event_name, payload_json, created_at) VALUES (:ref, :event, :payload, CURRENT_TIMESTAMP)');
        $stmt->execute(['ref' => $reference, 'event' => $event, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }
}
