<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Api\System\Library\Support\Ulid;
use PDO;

/**
 * Manages two-way status mapping between CRM and CMS (E-COM-04).
 *
 * Backed by `ecommerce_status_mappings` table.
 */
final class StatusMappingService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Maps a CRM status code to the external CMS status configured for the store.
     */
    public function mapCrmToExternal(int $storeId, string $crmStatus, string $scope = 'order'): string
    {
        $normalized = OrderStatusStateMachine::normalize($crmStatus);

        $stmt = $this->pdo->prepare(
            'SELECT external_status FROM ecommerce_status_mappings
             WHERE store_id = :store_id AND (entity_scope = :scope OR entity_scope = "all")
               AND (crm_status_code = :raw_code OR crm_status_code = :norm_code)
             ORDER BY CASE WHEN crm_status_code = :raw_code THEN 0 ELSE 1 END ASC
             LIMIT 1'
        );
        $stmt->execute([
            'store_id' => $storeId,
            'scope' => $scope,
            'raw_code' => $crmStatus,
            'norm_code' => $normalized,
        ]);

        $external = $stmt->fetchColumn();
        if (is_string($external) && trim($external) !== '') {
            return trim($external);
        }

        // Default fallbacks if no explicit mapping configured
        return $normalized;
    }

    /**
     * Maps an external CMS status to the corresponding CRM status code.
     */
    public function mapExternalToCrm(int $storeId, string $externalStatus, string $scope = 'order'): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT crm_status_code FROM ecommerce_status_mappings
             WHERE store_id = :store_id AND (entity_scope = :scope OR entity_scope = "all")
               AND external_status = :external_status
             LIMIT 1'
        );
        $stmt->execute([
            'store_id' => $storeId,
            'scope' => $scope,
            'external_status' => trim($externalStatus),
        ]);

        $crmStatus = $stmt->fetchColumn();
        if (is_string($crmStatus) && trim($crmStatus) !== '') {
            return trim($crmStatus);
        }

        return OrderStatusStateMachine::normalize($externalStatus);
    }

    /**
     * @return list<array{public_id: string, store_id: int, entity_scope: string, external_status: string, crm_status_code: string}>
     */
    public function getStoreMappings(int $storeId, string $scope = 'order'): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT public_id, store_id, entity_scope, external_status, crm_status_code
             FROM ecommerce_status_mappings
             WHERE store_id = :store_id AND (entity_scope = :scope OR :scope = "all")
             ORDER BY id ASC'
        );
        $stmt->execute([
            'store_id' => $storeId,
            'scope' => $scope,
        ]);

        /** @var list<array{public_id: string, store_id: int, entity_scope: string, external_status: string, crm_status_code: string}> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function setMapping(int $storeId, string $crmStatusCode, string $externalStatus, string $scope = 'order'): string
    {
        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('map');

        // Check if existing mapping exists
        $stmt = $this->pdo->prepare(
            'SELECT public_id FROM ecommerce_status_mappings
             WHERE store_id = :store_id AND entity_scope = :scope AND external_status = :external_status
             LIMIT 1'
        );
        $stmt->execute([
            'store_id' => $storeId,
            'scope' => $scope,
            'external_status' => trim($externalStatus),
        ]);
        $existing = $stmt->fetchColumn();

        if (is_string($existing) && $existing !== '') {
            $updateStmt = $this->pdo->prepare(
                'UPDATE ecommerce_status_mappings
                 SET crm_status_code = :crm_code, updated_at = :updated_at
                 WHERE public_id = :public_id'
            );
            $updateStmt->execute([
                'crm_code' => trim($crmStatusCode),
                'updated_at' => $now,
                'public_id' => $existing,
            ]);
            return $existing;
        }

        $insertStmt = $this->pdo->prepare(
            'INSERT INTO ecommerce_status_mappings (
                public_id, store_id, entity_scope, external_status, crm_status_code, created_at, updated_at
            ) VALUES (
                :public_id, :store_id, :scope, :external_status, :crm_code, :created_at, :updated_at
            )'
        );
        $insertStmt->execute([
            'public_id' => $publicId,
            'store_id' => $storeId,
            'scope' => $scope,
            'external_status' => trim($externalStatus),
            'crm_code' => trim($crmStatusCode),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }
}
