<?php
declare(strict_types=1);

namespace Module\Crm\AutomationHub\Service;

use Api\System\Library\Security\KeyGuard;
use PDO;
use RuntimeException;

/**
 * Service managing n8n and Make automation recipes, HMAC webhooks and delivery auditing.
 */
final class AutomationHubService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecipes(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT public_id, platform, recipe_key, title, webhook_endpoint_url, is_active, created_at, updated_at
             FROM crm_automation_recipes
             WHERE organization_id = :org_id
             ORDER BY id ASC"
        );
        $stmt->execute([':org_id' => $organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveRecipe(
        int $organizationId,
        string $platform,
        string $recipeKey,
        string $title,
        string $webhookEndpointUrl,
        string $secretToken,
        array $fieldMappings = []
    ): string {
        $now = date('Y-m-d H:i:s');
        $encryptedSecret = class_exists(KeyGuard::class) ? KeyGuard::encrypt($secretToken) : base64_encode($secretToken);
        $mappingJson = json_encode($fieldMappings, JSON_UNESCAPED_UNICODE);

        $pubId = 'rcp_' . bin2hex(random_bytes(10));
        $stmt = $this->db->prepare(
            "INSERT INTO crm_automation_recipes (public_id, organization_id, platform, recipe_key, title, webhook_endpoint_url, secret_token_encrypted, field_mappings_json, is_active, created_at, updated_at)
             VALUES (:pub_id, :org_id, :platform, :key, :title, :url, :sec, 1, :map, :now, :now)"
        );
        $stmt->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':platform' => $platform,
            ':key' => $recipeKey,
            ':title' => $title,
            ':url' => $webhookEndpointUrl,
            ':sec' => $encryptedSecret,
            ':map' => $mappingJson,
            ':now' => $now,
        ]);

        return $pubId;
    }

    /**
     * Dispatch webhook with HMAC-SHA256 signature and record delivery log.
     *
     * @param array<string, mixed> $payload
     * @return array{ok: bool, http_code: int, correlation_id: string, delivery_id: string}
     */
    public function dispatch(string $recipePublicId, string $eventType, array $payload, ?string $correlationId = null): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, organization_id, webhook_endpoint_url, secret_token_encrypted, is_active
             FROM crm_automation_recipes
             WHERE public_id = :pub_id"
        );
        $stmt->execute([':pub_id' => $recipePublicId]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipe || empty($recipe['is_active'])) {
            throw new RuntimeException("Automation recipe not found or inactive.");
        }

        $corrId = $correlationId ?? ('corr_' . bin2hex(random_bytes(12)));
        $now = date('Y-m-d H:i:s');

        // Check deduplication in last 60 seconds
        $dupCheck = $this->db->prepare(
            "SELECT id FROM crm_automation_delivery_logs WHERE recipe_id = :rid AND correlation_id = :cid LIMIT 1"
        );
        $dupCheck->execute([':rid' => $recipe['id'], ':cid' => $corrId]);
        if ($dupCheck->fetchColumn()) {
            return [
                'ok' => true,
                'http_code' => 200,
                'correlation_id' => $corrId,
                'delivery_id' => 'deduplicated_cached',
            ];
        }

        $rawSecret = (string)$recipe['secret_token_encrypted'];
        $secret = class_exists(KeyGuard::class) ? KeyGuard::decrypt($rawSecret) : base64_decode($rawSecret);

        $bodyJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$bodyJson}", (string)$secret);

        $ch = curl_init((string)$recipe['webhook_endpoint_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $bodyJson,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "X-Signature-SHA256: {$signature}",
                "X-Signature-Timestamp: {$timestamp}",
                "X-Correlation-ID: {$corrId}",
                "X-Event-Type: {$eventType}",
            ],
        ]);

        $res = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $success = ($err === '' && $httpCode >= 200 && $httpCode < 300);
        $delPubId = 'del_' . bin2hex(random_bytes(10));

        $logStmt = $this->db->prepare(
            "INSERT INTO crm_automation_delivery_logs (public_id, organization_id, recipe_id, correlation_id, event_type, payload_json, response_code, status, error_message, created_at)
             VALUES (:pub_id, :org_id, :rid, :cid, :event, :body, :code, :status, :err, :now)"
        );
        $logStmt->execute([
            ':pub_id' => $delPubId,
            ':org_id' => $recipe['organization_id'],
            ':rid' => $recipe['id'],
            ':cid' => $corrId,
            ':event' => $eventType,
            ':body' => $bodyJson,
            ':code' => $httpCode,
            ':status' => $success ? 'delivered' : 'failed',
            ':err' => $err !== '' ? $err : ($success ? null : substr((string)$res, 0, 500)),
            ':now' => $now,
        ]);

        return [
            'ok' => $success,
            'http_code' => $httpCode,
            'correlation_id' => $corrId,
            'delivery_id' => $delPubId,
        ];
    }
}
