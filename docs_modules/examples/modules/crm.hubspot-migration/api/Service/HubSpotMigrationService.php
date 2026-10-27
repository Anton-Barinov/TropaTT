<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Service;

use Api\System\Library\Service\ClientService;
use PDO;
use RuntimeException;
use Throwable;

/** Bounded HubSpot contacts JSON-export importer. No HubSpot API connection is implied. */
final class HubSpotMigrationService
{
    private const MAX_RECORDS = 100;
    private const MAX_SESSION_RECORDS = 1000;
    private const PREVIEW_PAGE_SIZE = 25;
    private const MAX_PREVIEW_PAGE_SIZE = 50;
    private const MAX_RECORD_BYTES = 16384;
    private const MAX_BATCH_BYTES = 524288;

    public function __construct(
        private readonly PDO $db,
        private readonly int $workspaceId,
        private readonly ClientService $clients,
        private readonly array $actor
    ) {
        if ($workspaceId < 1 || (int)($actor['organization_id'] ?? 0) !== $workspaceId || (int)($actor['id'] ?? 0) < 1) {
            throw new RuntimeException('An authenticated actor and matching workspace are required.');
        }
    }

    public function createSession(): array
    {
        $publicId = 'hsm_' . bin2hex(random_bytes(16));
        $stmt = $this->db->prepare("INSERT INTO module_hubspot_migration_sessions (public_id, workspace_id, status, created_by_user_id) VALUES (:public_id, :workspace, 'draft', :user_id)");
        $stmt->execute(['public_id' => $publicId, 'workspace' => $this->workspaceId, 'user_id' => (int)$this->actor['id']]);
        return ['session_id' => $publicId, 'status' => 'draft'];
    }

    /** Stage only HubSpot Contacts JSON exports; allowed fields are deliberately explicit. */
    public function stageContacts(string $sessionId, array $records, array $mapping = []): array
    {
        $session = $this->session($sessionId);
        if (!in_array($session['status'], ['draft', 'staging'], true)) {
            throw new RuntimeException('SESSION_NOT_EDITABLE');
        }
        if ($records === [] || count($records) > self::MAX_RECORDS) {
            throw new RuntimeException('BATCH_MUST_CONTAIN_1_TO_100_CONTACTS');
        }
        $mapping = $this->validateMapping($mapping);
        $prepared = [];
        $bytes = 0;
        foreach ($records as $record) {
            if (!is_array($record) || array_is_list($record)) {
                throw new RuntimeException('EACH_RECORD_MUST_BE_AN_OBJECT');
            }
            $raw = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $bytes += strlen($raw);
            if (strlen($raw) > self::MAX_RECORD_BYTES || $bytes > self::MAX_BATCH_BYTES) {
                throw new RuntimeException('PAYLOAD_LIMIT_EXCEEDED');
            }
            $props = $record['properties'] ?? $record;
            if (!is_array($props) || count($props) > 100) {
                throw new RuntimeException('INVALID_HUBSPOT_CONTACT');
            }
            foreach ($props as $value) {
                if (!is_scalar($value) && $value !== null) {
                    throw new RuntimeException('NESTED_PROPERTIES_NOT_SUPPORTED');
                }
            }
            $hubspotId = trim((string)($record['id'] ?? $props['hs_object_id'] ?? ''));
            if ($hubspotId === '' || strlen($hubspotId) > 128 || preg_match('/^[A-Za-z0-9_-]+$/D', $hubspotId) !== 1) {
                throw new RuntimeException('INVALID_HUBSPOT_CONTACT_ID');
            }
            $normalized = $this->mapContact($hubspotId, $props, $mapping);
            if ($normalized['title'] === '' || strlen($normalized['title']) > 255) {
                throw new RuntimeException('CONTACT_NAME_REQUIRED_OR_TOO_LONG');
            }
            if ($normalized['email'] !== '' && (strlen($normalized['email']) > 255 || filter_var($normalized['email'], FILTER_VALIDATE_EMAIL) === false)) {
                throw new RuntimeException('INVALID_EMAIL');
            }
            foreach (['phone' => 64, 'notes' => 4000] as $field => $max) {
                if (strlen($normalized[$field]) > $max) {
                    throw new RuntimeException(strtoupper($field) . '_TOO_LONG');
                }
            }
            $prepared[] = [$hubspotId, $raw, json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];
        }

        $this->db->beginTransaction();
        try {
            $lockedSession = $this->lockSession($sessionId);
            if (!in_array($lockedSession['status'], ['draft', 'staging'], true)) {
                throw new RuntimeException('SESSION_NOT_EDITABLE');
            }
            $count = $this->db->prepare('SELECT COUNT(*) FROM module_hubspot_migration_records WHERE workspace_id=:workspace AND session_public_id=:session_id');
            $count->execute(['workspace' => $this->workspaceId, 'session_id' => $sessionId]);
            if ((int)$count->fetchColumn() + count($prepared) > self::MAX_SESSION_RECORDS) {
                throw new RuntimeException('SESSION_RECORD_LIMIT_EXCEEDED');
            }
            $insert = $this->db->prepare("INSERT INTO module_hubspot_migration_records (workspace_id, session_public_id, object_type, hubspot_id, contact_label, raw_payload_json, normalized_payload_json, status) VALUES (:workspace, :session_id, 'contacts', :hubspot_id, :label, '{}', :normalized, 'pending')");
            foreach ($prepared as [$hubspotId, $raw, $normalized]) {
                $contact = json_decode($normalized, true, 16, JSON_THROW_ON_ERROR);
                $insert->execute(['workspace' => $this->workspaceId, 'session_id' => $sessionId, 'hubspot_id' => $hubspotId, 'label' => $contact['title'], 'normalized' => $normalized]);
            }
            $update = $this->db->prepare("UPDATE module_hubspot_migration_sessions SET status = 'staging', total_records = (SELECT COUNT(*) FROM module_hubspot_migration_records WHERE workspace_id = :workspace_sub AND session_public_id = :session_id) WHERE id = :id AND workspace_id = :workspace_outer");
            $update->execute(['workspace_sub' => $this->workspaceId, 'workspace_outer' => $this->workspaceId, 'session_id' => $sessionId, 'id' => (int)$session['id']]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($e instanceof \PDOException && (string)$e->getCode() === '23000') {
                throw new RuntimeException('DUPLICATE_HUBSPOT_ID_IN_SESSION');
            }
            throw $e;
        }
        return $this->preview($sessionId);
    }

    public function preview(string $sessionId, int $page = 1, int $perPage = self::PREVIEW_PAGE_SIZE): array
    {
        $session = $this->session($sessionId);
        $page = max(1, $page);
        $perPage = max(1, min(self::MAX_PREVIEW_PAGE_SIZE, $perPage));
        $countStmt = $this->db->prepare("SELECT s.status, COUNT(*) AS total, SUM(CASE WHEN m.crm_entity_id IS NOT NULL THEN 1 ELSE 0 END) AS mapped FROM module_hubspot_migration_records s LEFT JOIN module_hubspot_migration_id_map m ON m.workspace_id=s.workspace_id AND m.object_type=s.object_type AND m.hubspot_id=s.hubspot_id WHERE s.workspace_id=:workspace AND s.session_public_id=:session_id GROUP BY s.status");
        $countStmt->execute(['workspace' => $this->workspaceId, 'session_id' => $sessionId]);
        $counts = ['ready' => 0, 'already_imported' => 0, 'imported_this_session' => 0, 'failed' => 0, 'pending' => 0];
        $total = 0;
        foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $n = (int)$row['total'];
            $total += $n;
            if ($row['status'] === 'imported') $counts['imported_this_session'] += $n;
            elseif ($row['status'] === 'error') $counts['failed'] += $n;
            elseif ($row['status'] === 'pending') {
                $counts['pending'] += $n;
                $counts['ready'] += max(0, $n - (int)$row['mapped']);
                $counts['already_imported'] += (int)$row['mapped'];
            } elseif ($row['status'] === 'skipped_duplicate') $counts['already_imported'] += $n;
        }
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $stmt = $this->db->prepare("SELECT s.hubspot_id, s.normalized_payload_json, s.contact_label, s.status, m.crm_entity_id FROM module_hubspot_migration_records s LEFT JOIN module_hubspot_migration_id_map m ON m.workspace_id = s.workspace_id AND m.object_type = s.object_type AND m.hubspot_id = s.hubspot_id WHERE s.workspace_id = :workspace AND s.session_public_id = :session_id ORDER BY s.id ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':workspace', $this->workspaceId, PDO::PARAM_INT);
        $stmt->bindValue(':session_id', $sessionId);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = ['hubspot_id' => $row['hubspot_id'], 'contact' => json_decode((string)($row['normalized_payload_json'] ?? ''), true) ?: ['title' => (string)($row['contact_label'] ?? '')], 'status' => $row['status'], 'already_imported' => $row['crm_entity_id'] !== null];
        }
        return ['session_id' => $sessionId, 'status' => $session['status'], 'counts' => $counts, 'items' => $items, 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total_records' => $total, 'total_pages' => $pages], 'limit' => self::MAX_RECORDS, 'max_session_records' => self::MAX_SESSION_RECORDS, 'supported_object' => 'contacts'];
    }

    public function importBatch(string $sessionId, int $limit = 25): array
    {
        $session = $this->session($sessionId);
        if (!in_array($session['status'], ['staging', 'importing', 'partial', 'completed'], true)) throw new RuntimeException('SESSION_NOT_READY');
        $limit = max(1, min(25, $limit));
        $markDuplicates = $this->db->prepare("UPDATE module_hubspot_migration_records SET status='skipped_duplicate', crm_target_entity=(SELECT m.crm_entity_type FROM module_hubspot_migration_id_map m WHERE m.workspace_id=:workspace_map AND m.object_type=module_hubspot_migration_records.object_type AND m.hubspot_id=module_hubspot_migration_records.hubspot_id LIMIT 1), crm_target_id=(SELECT m.crm_entity_id FROM module_hubspot_migration_id_map m WHERE m.workspace_id=:workspace_target AND m.object_type=module_hubspot_migration_records.object_type AND m.hubspot_id=module_hubspot_migration_records.hubspot_id LIMIT 1), error_message='Already imported in this workspace', processed_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_outer AND session_public_id=:session_id AND status='pending' AND EXISTS (SELECT 1 FROM module_hubspot_migration_id_map m WHERE m.workspace_id=:workspace_exists AND m.object_type=module_hubspot_migration_records.object_type AND m.hubspot_id=module_hubspot_migration_records.hubspot_id)");
        $markDuplicates->execute(['workspace_map' => $this->workspaceId, 'workspace_target' => $this->workspaceId, 'workspace_outer' => $this->workspaceId, 'workspace_exists' => $this->workspaceId, 'session_id' => $sessionId]);
        $stmt = $this->db->prepare("SELECT s.id, s.hubspot_id, s.normalized_payload_json FROM module_hubspot_migration_records s LEFT JOIN module_hubspot_migration_id_map m ON m.workspace_id=s.workspace_id AND m.object_type=s.object_type AND m.hubspot_id=s.hubspot_id WHERE s.workspace_id=:workspace AND s.session_public_id=:session_id AND s.status='pending' AND m.id IS NULL ORDER BY s.id LIMIT :limit");
        $stmt->bindValue(':workspace', $this->workspaceId, PDO::PARAM_INT); $stmt->bindValue(':session_id', $sessionId); $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $imported = $errors = 0;
        $skipped = $markDuplicates->rowCount();
        $processedCount = count($rows) + $skipped;
        foreach ($rows as $row) {
            try {
                $contact = json_decode((string)$row['normalized_payload_json'], true, 16, JSON_THROW_ON_ERROR);
                $this->db->beginTransaction();
                $existing = $this->db->prepare('SELECT crm_entity_id FROM module_hubspot_migration_id_map WHERE workspace_id=:workspace AND object_type=\'contacts\' AND hubspot_id=:hubspot_id LIMIT 1');
                $existing->execute(['workspace' => $this->workspaceId, 'hubspot_id' => $row['hubspot_id']]);
                if ($existing->fetchColumn() !== false) {
                    $this->db->prepare("UPDATE module_hubspot_migration_records SET status='skipped_duplicate', processed_at=CURRENT_TIMESTAMP WHERE id=:id AND workspace_id=:workspace")->execute(['id' => $row['id'], 'workspace' => $this->workspaceId]);
                    $this->db->commit(); $skipped++; continue;
                }
                $created = $this->clients->create(['title' => $contact['title'], 'email' => $contact['email'], 'phone' => $contact['phone'], 'notes' => $contact['notes'], 'status' => 'active', 'client_type' => 'individual'], $this->actor);
                $targetId = (string)($created['public_id'] ?? '');
                if ($targetId === '') throw new RuntimeException('CRM_CONTACT_CREATE_FAILED');
                $map = $this->db->prepare("INSERT INTO module_hubspot_migration_id_map (workspace_id, object_type, hubspot_id, crm_entity_type, crm_entity_id, imported_session_id) VALUES (:workspace, 'contacts', :hubspot_id, 'client', :entity_id, :session_id)");
                $map->execute(['workspace' => $this->workspaceId, 'hubspot_id' => $row['hubspot_id'], 'entity_id' => $targetId, 'session_id' => $sessionId]);
                $createdClient = $this->clients->get($targetId, $this->actor);
                if (!is_array($createdClient)) throw new RuntimeException('CRM_CONTACT_READBACK_FAILED');
                $fingerprint = self::clientFingerprint($createdClient);
                $mark = $this->db->prepare("UPDATE module_hubspot_migration_records SET status='imported', crm_target_entity='client', crm_target_id=:entity_id, contact_label=:label, contact_fingerprint=:fingerprint, raw_payload_json='{}', normalized_payload_json=NULL, processed_at=CURRENT_TIMESTAMP WHERE id=:id AND workspace_id=:workspace");
                $mark->execute(['entity_id' => $targetId, 'label' => $contact['title'], 'fingerprint' => $fingerprint, 'id' => $row['id'], 'workspace' => $this->workspaceId]);
                $this->db->commit(); $imported++;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                // Keep the normalized row in the workspace-scoped staging table so
                // retrying a transient CRM/database failure does not require the
                // customer to re-export and re-upload the source data. Successful
                // imports still clear staged payloads below; discardPending() is
                // the explicit cleanup path for failed rows.
                $this->db->prepare("UPDATE module_hubspot_migration_records SET status='error', error_message=:error, processed_at=CURRENT_TIMESTAMP WHERE id=:id AND workspace_id=:workspace")->execute(['error' => 'Импорт записи не выполнен; проверьте данные и повторите после исправления.', 'id' => $row['id'], 'workspace' => $this->workspaceId]);
                $errors++;
            }
        }
        $this->refreshSessionCounters($sessionId);
        return ['session_id' => $sessionId, 'processed' => $processedCount, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'report' => $this->preview($sessionId)];
    }

    /** Requeue retained failed rows and retry one bounded batch in this workspace. */
    public function retryFailedRecords(string $sessionId, int $limit = 25): array
    {
        $session = $this->session($sessionId);
        if (!in_array($session['status'], ['partial', 'importing', 'staging'], true)) {
            throw new RuntimeException('SESSION_NOT_READY');
        }

        $reset = $this->db->prepare("UPDATE module_hubspot_migration_records SET status='pending', error_message=NULL, processed_at=NULL WHERE workspace_id=:workspace AND session_public_id=:session_id AND status='error' AND normalized_payload_json IS NOT NULL AND normalized_payload_json <> ''");
        $reset->execute(['workspace' => $this->workspaceId, 'session_id' => $sessionId]);
        if ($reset->rowCount() === 0) {
            throw new RuntimeException('NO_RETRYABLE_RECORDS');
        }
        $this->db->prepare("UPDATE module_hubspot_migration_sessions SET status='importing' WHERE workspace_id=:workspace AND public_id=:session_id")->execute(['workspace' => $this->workspaceId, 'session_id' => $sessionId]);

        return $this->importBatch($sessionId, $limit);
    }

    /** Delete only unchanged client records created by this exact import session. */
    public function rollback(string $sessionId): array
    {
        $this->session($sessionId);
        $stmt = $this->db->prepare("SELECT s.id, s.hubspot_id, s.contact_fingerprint, m.crm_entity_id FROM module_hubspot_migration_records s INNER JOIN module_hubspot_migration_id_map m ON m.workspace_id=s.workspace_id AND m.object_type=s.object_type AND m.hubspot_id=s.hubspot_id AND m.imported_session_id=s.session_public_id WHERE s.workspace_id=:workspace AND s.session_public_id=:session_id AND s.status='imported'");
        $stmt->execute(['workspace' => $this->workspaceId, 'session_id' => $sessionId]);
        $deleted = $retained = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->db->beginTransaction();
            try {
                if (!$this->lockCounterparty((string)$row['crm_entity_id'])) {
                    $this->db->commit();
                    $retained++;
                    continue;
                }
                $current = $this->clients->get((string)$row['crm_entity_id'], $this->actor);
                $currentFingerprint = is_array($current) ? self::clientFingerprint($current) : '';
                $unchanged = is_array($current) && is_string($row['contact_fingerprint']) && hash_equals((string)$row['contact_fingerprint'], $currentFingerprint);
                if (!$unchanged) {
                    $this->db->commit();
                    $retained++;
                    continue;
                }
                if (!$this->clients->delete((string)$row['crm_entity_id'], $this->actor)) throw new RuntimeException('ROLLBACK_DELETE_FAILED');
                $this->db->prepare('DELETE FROM module_hubspot_migration_id_map WHERE workspace_id=:workspace AND object_type=\'contacts\' AND hubspot_id=:hubspot_id AND imported_session_id=:session_id')->execute(['workspace' => $this->workspaceId, 'hubspot_id' => $row['hubspot_id'], 'session_id' => $sessionId]);
                $this->db->prepare("UPDATE module_hubspot_migration_records SET status='rolled_back', crm_target_id=NULL, contact_fingerprint=NULL WHERE id=:id AND workspace_id=:workspace")->execute(['id' => $row['id'], 'workspace' => $this->workspaceId]);
                $this->db->commit(); $deleted++;
            } catch (Throwable) {
                if ($this->db->inTransaction()) $this->db->rollBack(); $retained++;
            }
        }
        $this->refreshSessionCounters($sessionId);
        return ['session_id' => $sessionId, 'deleted_unchanged_records' => $deleted, 'retained_changed_or_unavailable_records' => $retained, 'report' => $this->preview($sessionId)];
    }


    /** Remove unimported staged contact data when a session is abandoned. */
    public function discardPending(string $sessionId): array
    {
        $this->session($sessionId);
        $delete = $this->db->prepare("DELETE FROM module_hubspot_migration_records WHERE workspace_id=:workspace AND session_public_id=:session_id AND status IN ('pending','error','skipped_duplicate')");
        $delete->execute(['workspace' => $this->workspaceId, 'session_id' => $sessionId]);
        $remaining = $this->db->prepare("SELECT COUNT(*) FROM module_hubspot_migration_records WHERE workspace_id=:workspace AND session_public_id=:session_id AND status='imported'");
        $remaining->execute(['workspace' => $this->workspaceId, 'session_id' => $sessionId]);
        $newStatus = (int)$remaining->fetchColumn() > 0 ? 'partial' : 'cancelled';
        $this->db->prepare('UPDATE module_hubspot_migration_sessions SET status=:status WHERE public_id=:session_id AND workspace_id=:workspace')->execute(['status' => $newStatus, 'session_id' => $sessionId, 'workspace' => $this->workspaceId]);
        return ['session_id' => $sessionId, 'discarded_staged_records' => $delete->rowCount(), 'status' => $newStatus, 'report' => $this->preview($sessionId)];
    }

    public function status(): array
    {
        $stmt = $this->db->prepare('SELECT status, COUNT(*) AS count FROM module_hubspot_migration_sessions WHERE workspace_id=:workspace GROUP BY status');
        $stmt->execute(['workspace' => $this->workspaceId]);
        return ['ready' => true, 'source' => 'pasted HubSpot Contacts JSON export', 'supported_objects' => ['contacts'], 'unsupported_objects' => ['companies', 'deals', 'activities', 'associations'], 'sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'max_records_per_import_call' => 25];
    }

    private function mapContact(string $hubspotId, array $props, array $mapping): array
    {
        $read = static fn(string $target): string => trim((string)($props[$mapping[$target]] ?? ''));
        $name = trim($read('firstname') . ' ' . $read('lastname'));
        if ($name === '') $name = $read('email');
        return ['hubspot_id' => $hubspotId, 'title' => $name, 'email' => strtolower($read('email')), 'phone' => $read('phone'), 'notes' => $read('notes')];
    }

    private function validateMapping(array $mapping): array
    {
        $defaults = ['firstname' => 'firstname', 'lastname' => 'lastname', 'email' => 'email', 'phone' => 'phone', 'notes' => ''];
        $allowed = ['firstname', 'lastname', 'email', 'phone', 'mobilephone', 'notes', 'hs_object_id'];
        foreach (array_keys($mapping) as $key) if (!is_string($key) || !array_key_exists($key, $defaults)) throw new RuntimeException('INVALID_FIELD_MAPPING');
        foreach ($defaults as $target => $_) {
            if (isset($mapping[$target]) && (!is_string($mapping[$target]) || !in_array($mapping[$target], $allowed, true))) throw new RuntimeException('INVALID_FIELD_MAPPING');
            if (isset($mapping[$target]) && $mapping[$target] === 'hs_object_id') throw new RuntimeException('ID_CANNOT_BE_MAPPED_AS_CONTACT_DATA');
        }
        return array_replace($defaults, $mapping);
    }

    private function session(string $sessionId): array
    {
        if (!preg_match('/^hsm_[a-f0-9]{32}$/D', $sessionId)) throw new RuntimeException('INVALID_SESSION_ID');
        $stmt = $this->db->prepare('SELECT id, public_id, workspace_id, status FROM module_hubspot_migration_sessions WHERE public_id=:session_id AND workspace_id=:workspace LIMIT 1');
        $stmt->execute(['session_id' => $sessionId, 'workspace' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('SESSION_NOT_FOUND');
        return $row;
    }

    /** Stable hash of every client field that a user can meaningfully edit. */
    public static function clientFingerprint(array $client): string
    {
        $fields = ['title','legal_name','person_last_name','person_first_name','person_middle_name','person_birth_date','tax_inn','tax_kpp','tax_ogrn','tax_ogrnip','bank_account','bank_name','bank_bik','bank_corr_account','website','messenger','address_legal','address_postal','address_actual','notes','email','phone','status','counterparty_type','client_type','extra_attributes'];
        $semantic = [];
        foreach ($fields as $field) $semantic[$field] = $client[$field] ?? null;
        if (!array_key_exists('counterparty_type', $client)) $semantic['counterparty_type'] = $client['client_type'] ?? null;
        if (is_string($semantic['extra_attributes'])) {
            $decoded = json_decode($semantic['extra_attributes'], true);
            if (json_last_error() === JSON_ERROR_NONE) $semantic['extra_attributes'] = $decoded;
        }
        return hash('sha256', json_encode($semantic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function lockSession(string $sessionId): array
    {
        $stmt = $this->db->prepare('SELECT id, public_id, workspace_id, status FROM module_hubspot_migration_sessions WHERE public_id=:session_id AND workspace_id=:workspace LIMIT 1' . $this->forUpdate());
        $stmt->execute(['session_id' => $sessionId, 'workspace' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('SESSION_NOT_FOUND');
        return $row;
    }

    private function lockCounterparty(string $publicId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM counterparties WHERE public_id=:public_id AND organization_id=:workspace LIMIT 1' . $this->forUpdate());
        $stmt->execute(['public_id' => $publicId, 'workspace' => $this->workspaceId]);
        return $stmt->fetchColumn() !== false;
    }

    private function forUpdate(): string
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function refreshSessionCounters(string $sessionId): void
    {
        $stmt = $this->db->prepare("SELECT status, COUNT(*) AS total FROM module_hubspot_migration_records WHERE workspace_id=:workspace AND session_public_id=:session_id GROUP BY status");
        $stmt->execute(['workspace' => $this->workspaceId, 'session_id' => $sessionId]);
        $counts = ['pending' => 0, 'imported' => 0, 'skipped_duplicate' => 0, 'error' => 0, 'rolled_back' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $counts[(string)$row['status']] = (int)$row['total'];
        $processed = $counts['imported'] + $counts['skipped_duplicate'] + $counts['error'] + $counts['rolled_back'];
        $status = $counts['pending'] > 0 || $counts['error'] > 0 ? 'partial' : 'completed';
        $update = $this->db->prepare('UPDATE module_hubspot_migration_sessions SET processed_records=:processed, imported_records=:imported, skipped_records=:skipped, error_records=:errors, status=:status WHERE public_id=:session_id AND workspace_id=:workspace');
        $update->execute(['processed' => $processed, 'imported' => $counts['imported'], 'skipped' => $counts['skipped_duplicate'], 'errors' => $counts['error'], 'status' => $status, 'session_id' => $sessionId, 'workspace' => $this->workspaceId]);
    }
}
