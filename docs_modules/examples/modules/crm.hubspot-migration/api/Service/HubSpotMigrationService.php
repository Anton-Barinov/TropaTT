<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Service;

class HubSpotMigrationService
{
    private \PDO $db;
    private int $workspaceId;

    public function __construct(\PDO $db, int $workspaceId = 1)
    {
        $this->db = $db;
        $this->workspaceId = $workspaceId;
    }

    /**
     * Map HubSpot Contact properties to CRM Client / Contact attributes
     */
    public function mapContact(array $hsContact): array
    {
        $props = $hsContact['properties'] ?? $hsContact;
        $firstName = trim((string)($props['firstname'] ?? ''));
        $lastName = trim((string)($props['lastname'] ?? ''));
        $email = strtolower(trim((string)($props['email'] ?? '')));
        $phone = trim((string)($props['phone'] ?? $props['mobilephone'] ?? ''));
        $companyName = trim((string)($props['company'] ?? ''));

        $name = trim($firstName . ' ' . $lastName);
        if ($name === '') {
            $name = $email !== '' ? $email : 'HubSpot Contact ' . ($hsContact['id'] ?? uniqid());
        }

        return [
            'hubspot_id' => (string)($hsContact['id'] ?? $props['hs_object_id'] ?? ''),
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company_name' => $companyName,
            'type' => 'individual',
            'notes' => (string)($props['notes_last_contacted'] ?? ''),
            'tags' => ['hubspot_import']
        ];
    }

    /**
     * Map HubSpot Company properties to CRM Company / Client attributes
     */
    public function mapCompany(array $hsCompany): array
    {
        $props = $hsCompany['properties'] ?? $hsCompany;
        $name = trim((string)($props['name'] ?? ''));
        $domain = strtolower(trim((string)($props['domain'] ?? '')));
        $phone = trim((string)($props['phone'] ?? ''));
        $city = trim((string)($props['city'] ?? ''));
        $industry = trim((string)($props['industry'] ?? ''));

        if ($name === '') {
            $name = $domain !== '' ? $domain : 'HubSpot Company ' . ($hsCompany['id'] ?? uniqid());
        }

        return [
            'hubspot_id' => (string)($hsCompany['id'] ?? $props['hs_object_id'] ?? ''),
            'name' => $name,
            'domain' => $domain,
            'phone' => $phone,
            'city' => $city,
            'industry' => $industry,
            'type' => 'company',
            'tags' => ['hubspot_import']
        ];
    }

    /**
     * Map HubSpot Deal properties to CRM Deal / Project attributes
     */
    public function mapDeal(array $hsDeal): array
    {
        $props = $hsDeal['properties'] ?? $hsDeal;
        $dealName = trim((string)($props['dealname'] ?? ''));
        $amount = (float)($props['amount'] ?? 0.0);
        $stage = (string)($props['dealstage'] ?? 'appointmentscheduled');
        $closeDate = (string)($props['closedate'] ?? '');

        // Map HubSpot stages to CRM standard stages
        $stageMap = [
            'appointmentscheduled' => 'lead',
            'qualifiedtobuy' => 'qualified',
            'presentationscheduled' => 'proposal',
            'decisionmakerboughtin' => 'negotiation',
            'contractsent' => 'contract',
            'closedwon' => 'won',
            'closedlost' => 'lost'
        ];

        return [
            'hubspot_id' => (string)($hsDeal['id'] ?? $props['hs_object_id'] ?? ''),
            'title' => $dealName !== '' ? $dealName : 'HubSpot Deal ' . ($hsDeal['id'] ?? uniqid()),
            'amount' => $amount,
            'stage' => $stageMap[$stage] ?? 'lead',
            'closed_at' => $closeDate !== '' ? substr($closeDate, 0, 19) : null,
            'tags' => ['hubspot_import']
        ];
    }

    /**
     * Preflight check and staging of raw payload
     */
    public function stagePayload(string $sessionPublicId, string $objectType, array $records): array
    {
        $stagedCount = 0;
        $errorCount = 0;

        $stmt = $this->db->prepare("
            INSERT INTO module_hubspot_migration_staging 
            (session_public_id, object_type, hubspot_id, raw_payload_json, normalized_payload_json, status, created_at)
            VALUES (:session_id, :obj_type, :hs_id, :raw_json, :norm_json, 'pending', CURRENT_TIMESTAMP)
        ");

        foreach ($records as $rec) {
            $hsId = (string)($rec['id'] ?? $rec['properties']['hs_object_id'] ?? uniqid('hs_'));
            $norm = match ($objectType) {
                'contacts' => $this->mapContact($rec),
                'companies' => $this->mapCompany($rec),
                'deals' => $this->mapDeal($rec),
                default => $rec
            };

            try {
                $stmt->execute([
                    'session_id' => $sessionPublicId,
                    'obj_type' => $objectType,
                    'hs_id' => $hsId,
                    'raw_json' => json_encode($rec, JSON_UNESCAPED_UNICODE),
                    'norm_json' => json_encode($norm, JSON_UNESCAPED_UNICODE),
                ]);
                $stagedCount++;
            } catch (\Exception $e) {
                $errorCount++;
            }
        }

        return [
            'staged' => $stagedCount,
            'errors' => $errorCount,
            'total' => count($records)
        ];
    }

    /**
     * Process a chunk of staged records with idempotency checks
     */
    public function processBatch(string $sessionPublicId, int $limit = 50, bool $dryRun = true): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM module_hubspot_migration_staging 
            WHERE session_public_id = :session_id AND status = 'pending'
            ORDER BY id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':session_id', $sessionPublicId, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $processed = 0;
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        $updateStmt = $this->db->prepare("
            UPDATE module_hubspot_migration_staging 
            SET status = :status, crm_target_entity = :entity, crm_target_id = :target_id, error_message = :err, processed_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $idMapCheckStmt = $this->db->prepare("
            SELECT crm_entity_id FROM module_hubspot_migration_id_map
            WHERE workspace_id = :ws_id AND object_type = :obj_type AND hubspot_id = :hs_id
            LIMIT 1
        ");

        $idMapInsertStmt = $this->db->prepare("
            INSERT INTO module_hubspot_migration_id_map
            (workspace_id, object_type, hubspot_id, crm_entity_type, crm_entity_id, imported_session_id, created_at)
            VALUES (:ws_id, :obj_type, :hs_id, :crm_ent, :crm_id, :session_id, CURRENT_TIMESTAMP)
        ");

        foreach ($rows as $row) {
            $processed++;
            $objType = $row['object_type'];
            $hsId = $row['hubspot_id'];

            // Check if already imported
            $idMapCheckStmt->execute([
                'ws_id' => $this->workspaceId,
                'obj_type' => $objType,
                'hs_id' => $hsId
            ]);
            $existing = $idMapCheckStmt->fetchColumn();

            if ($existing) {
                $skipped++;
                $updateStmt->execute([
                    'status' => 'skipped_duplicate',
                    'entity' => $objType,
                    'target_id' => $existing,
                    'err' => 'Already imported in prior session',
                    'id' => $row['id']
                ]);
                continue;
            }

            if ($dryRun) {
                $imported++;
                $updateStmt->execute([
                    'status' => 'dry_run_success',
                    'entity' => $objType,
                    'target_id' => 'simulated_' . $hsId,
                    'err' => null,
                    'id' => $row['id']
                ]);
                continue;
            }

            // Live execution: Generate deterministic CRM Entity ID or save to target tables
            $generatedCrmId = 'crm_hs_' . substr(md5($objType . '_' . $hsId), 0, 16);

            try {
                $idMapInsertStmt->execute([
                    'ws_id' => $this->workspaceId,
                    'obj_type' => $objType,
                    'hs_id' => $hsId,
                    'crm_ent' => $objType,
                    'crm_id' => $generatedCrmId,
                    'session_id' => $sessionPublicId
                ]);

                $imported++;
                $updateStmt->execute([
                    'status' => 'imported',
                    'entity' => $objType,
                    'target_id' => $generatedCrmId,
                    'err' => null,
                    'id' => $row['id']
                ]);
            } catch (\Exception $e) {
                $errors++;
                $updateStmt->execute([
                    'status' => 'error',
                    'entity' => $objType,
                    'target_id' => null,
                    'err' => $e->getMessage(),
                    'id' => $row['id']
                ]);
            }
        }

        return [
            'processed' => $processed,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'remaining' => $this->getPendingCount($sessionPublicId)
        ];
    }

    /**
     * Rollback a migration session safely
     */
    public function rollbackSession(string $sessionPublicId): array
    {
        $delMapStmt = $this->db->prepare("
            DELETE FROM module_hubspot_migration_id_map
            WHERE workspace_id = :ws_id AND imported_session_id = :session_id
        ");
        $delMapStmt->execute([
            'ws_id' => $this->workspaceId,
            'session_id' => $sessionPublicId
        ]);
        $revertedCount = $delMapStmt->rowCount();

        $updateStagingStmt = $this->db->prepare("
            UPDATE module_hubspot_migration_staging
            SET status = 'rolled_back'
            WHERE session_public_id = :session_id
        ");
        $updateStagingStmt->execute(['session_id' => $sessionPublicId]);

        return [
            'session_public_id' => $sessionPublicId,
            'reverted_mappings' => $revertedCount,
            'status' => 'rolled_back'
        ];
    }

    public function getPendingCount(string $sessionPublicId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM module_hubspot_migration_staging
            WHERE session_public_id = :session_id AND status = 'pending'
        ");
        $stmt->execute(['session_id' => $sessionPublicId]);
        return (int)$stmt->fetchColumn();
    }
}
