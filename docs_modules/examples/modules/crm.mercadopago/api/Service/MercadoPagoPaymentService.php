<?php

declare(strict_types=1);

namespace Module\Crm\MercadoPago\Service;

class MercadoPagoPaymentService
{
    private \PDO $db;
    private int $workspaceId;

    public function __construct(\PDO $db, int $workspaceId = 1)
    {
        $this->db = $db;
        $this->workspaceId = $workspaceId;
    }

    /**
     * Create payment preference and store locally
     */
    public function createPaymentPreference(array $data): array
    {
        $title = trim((string)($data['title'] ?? 'CRM Service'));
        $amount = (float)($data['amount'] ?? 0.0);
        $currencyId = (string)($data['currency_id'] ?? 'BRL');
        $externalRef = (string)($data['external_reference'] ?? ('ref_' . uniqid()));

        $publicId = 'pay_mp_' . substr(md5(uniqid('', true)), 0, 16);
        $prefId = 'mp_pref_' . substr(md5($publicId), 0, 12);
        $initPointUrl = "https://www.mercadopago.com.br/checkout/v1/redirect?pref_id={$prefId}";

        $stmt = $this->db->prepare("
            INSERT INTO module_mercadopago_payments
            (public_id, workspace_id, external_reference, mp_preference_id, init_point_url, title, amount, currency_id, status, created_at)
            VALUES (:pid, :ws_id, :ext_ref, :pref_id, :init_point, :title, :amount, :cur, 'pending', CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            'pid' => $publicId,
            'ws_id' => $this->workspaceId,
            'ext_ref' => $externalRef,
            'pref_id' => $prefId,
            'init_point' => $initPointUrl,
            'title' => $title,
            'amount' => $amount,
            'cur' => $currencyId
        ]);

        $this->logAudit($publicId, null, 'pending', ['action' => 'preference_created']);

        return [
            'payment_public_id' => $publicId,
            'preference_id' => $prefId,
            'init_point_url' => $initPointUrl,
            'status' => 'pending',
            'amount' => $amount,
            'currency' => $currencyId,
            'external_reference' => $externalRef
        ];
    }

    /**
     * Verify HMAC signature of Mercado Pago webhook
     */
    public function verifyWebhookSignature(string $xSignature, string $dataId, string $secret): bool
    {
        // Parse parts: ts=123456,v1=hash
        $parts = [];
        foreach (explode(',', $xSignature) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        $ts = $parts['ts'] ?? '';
        $hash = $parts['v1'] ?? '';

        if ($ts === '' || $hash === '') {
            return false;
        }

        $manifest = "id:{$dataId};request-id:;ts:{$ts};";
        $computedHash = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($computedHash, $hash);
    }

    /**
     * Update payment status and record audit transition
     */
    public function updatePaymentStatus(string $paymentPublicId, string $newStatus, ?string $mpPaymentId = null, array $rawPayload = []): array
    {
        $stmt = $this->db->prepare("
            SELECT id, status FROM module_mercadopago_payments
            WHERE workspace_id = :ws_id AND public_id = :pid
            LIMIT 1
        ");
        $stmt->execute([
            'ws_id' => $this->workspaceId,
            'pid' => $paymentPublicId
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \RuntimeException("Payment not found");
        }

        $currentStatus = $row['status'];
        if ($currentStatus === $newStatus) {
            return ['status' => $currentStatus, 'unchanged' => true];
        }

        $updateStmt = $this->db->prepare("
            UPDATE module_mercadopago_payments
            SET status = :status, mp_payment_id = COALESCE(:mp_id, mp_payment_id), updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $updateStmt->execute([
            'status' => $newStatus,
            'mp_id' => $mpPaymentId,
            'id' => $row['id']
        ]);

        $this->logAudit($paymentPublicId, $currentStatus, $newStatus, $rawPayload);

        return [
            'payment_public_id' => $paymentPublicId,
            'from_status' => $currentStatus,
            'to_status' => $newStatus,
            'mp_payment_id' => $mpPaymentId
        ];
    }

    private function logAudit(string $publicId, ?string $from, string $to, array $payload): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO module_mercadopago_audit_log
            (payment_public_id, from_status, to_status, raw_webhook_payload, created_at)
            VALUES (:pid, :from_s, :to_s, :raw, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            'pid' => $publicId,
            'from_s' => $from,
            'to_s' => $to,
            'raw' => json_encode($payload, JSON_UNESCAPED_UNICODE)
        ]);
    }
}
