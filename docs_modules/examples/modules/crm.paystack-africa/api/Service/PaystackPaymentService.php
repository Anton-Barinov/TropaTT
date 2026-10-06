<?php

declare(strict_types=1);

namespace Module\Crm\PaystackAfrica\Service;

class PaystackPaymentService
{
    private \PDO $db;
    private int $workspaceId;

    public function __construct(\PDO $db, int $workspaceId = 1)
    {
        $this->db = $db;
        $this->workspaceId = $workspaceId;
    }

    /**
     * Initialize payment transaction and store locally
     */
    public function initializeTransaction(array $data): array
    {
        $email = trim(strtolower((string)($data['email'] ?? '')));
        $amountMajor = (float)($data['amount'] ?? 0.0);
        $currency = strtoupper(trim((string)($data['currency'] ?? 'NGN')));
        $reference = trim((string)($data['reference'] ?? ('pstk_' . uniqid())));

        if ($email === '' || $amountMajor <= 0) {
            throw new \InvalidArgumentException("Valid customer email and positive amount are required");
        }

        $publicId = 'txn_pstk_' . substr(md5(uniqid('', true)), 0, 16);
        $accessCode = 'pstk_acc_' . substr(md5($publicId), 0, 10);
        $authUrl = "https://checkout.paystack.com/{$accessCode}";

        $stmt = $this->db->prepare("
            INSERT INTO module_paystack_transactions
            (public_id, workspace_id, reference, paystack_access_code, authorization_url, customer_email, amount_major, currency, status, created_at)
            VALUES (:pid, :ws_id, :ref, :acc, :url, :email, :amt, :curr, 'pending', CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            'pid' => $publicId,
            'ws_id' => $this->workspaceId,
            'ref' => $reference,
            'acc' => $accessCode,
            'url' => $authUrl,
            'email' => $email,
            'amt' => $amountMajor,
            'curr' => $currency
        ]);

        $this->logAudit($reference, 'charge.initiated', ['email' => $email, 'amount' => $amountMajor, 'currency' => $currency]);

        return [
            'public_id' => $publicId,
            'reference' => $reference,
            'access_code' => $accessCode,
            'authorization_url' => $authUrl,
            'status' => 'pending'
        ];
    }

    /**
     * Verify webhook signature using HMAC-SHA512
     */
    public function verifyWebhookSignature(string $signatureHeader, string $requestBody, string $secretKey): bool
    {
        $computed = hash_hmac('sha512', $requestBody, $secretKey);
        return hash_equals($computed, $signatureHeader);
    }

    /**
     * Update transaction status upon webhook or verification query
     */
    public function recordTransactionOutcome(string $reference, string $status, ?string $channel = null, ?string $gatewayResponse = null): array
    {
        $stmt = $this->db->prepare("
            SELECT id, status FROM module_paystack_transactions
            WHERE workspace_id = :ws_id AND reference = :ref
            LIMIT 1
        ");
        $stmt->execute([
            'ws_id' => $this->workspaceId,
            'ref' => $reference
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \RuntimeException("Transaction reference not found: {$reference}");
        }

        $currentStatus = $row['status'];
        if ($currentStatus === $status) {
            return ['reference' => $reference, 'status' => $status, 'unchanged' => true];
        }

        $updateStmt = $this->db->prepare("
            UPDATE module_paystack_transactions
            SET status = :status, channel = COALESCE(:chan, channel), gateway_response = COALESCE(:gw, gateway_response), updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $updateStmt->execute([
            'status' => $status,
            'chan' => $channel,
            'gw' => $gatewayResponse,
            'id' => $row['id']
        ]);

        $this->logAudit($reference, 'charge.' . $status, ['from' => $currentStatus, 'to' => $status, 'channel' => $channel]);

        return [
            'reference' => $reference,
            'from_status' => $currentStatus,
            'to_status' => $status,
            'success' => $status === 'success'
        ];
    }

    private function logAudit(string $reference, string $event, array $payload): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO module_paystack_audit_log
            (transaction_reference, event_name, payload_json, created_at)
            VALUES (:ref, :evt, :payload, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            'ref' => $reference,
            'evt' => $event,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)
        ]);
    }
}
