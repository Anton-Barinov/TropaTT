<?php
declare(strict_types=1);

namespace Module\Crm\ClientPortal\Service;

use PDO;
use RuntimeException;

/**
 * Service managing client portal service requests, messages with visibility controls, and approvals.
 */
final class ClientPortalService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Submit a new service request from the client portal.
     */
    public function submitRequest(
        int $organizationId,
        string $clientPublicId,
        string $category,
        string $title,
        string $description,
        string $userPublicId
    ): string {
        $now = date('Y-m-d H:i:s');
        $pubId = 'srq_' . bin2hex(random_bytes(10));

        // Default 24h response SLA, 72h resolution SLA
        $responseDue = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $resolveDue = date('Y-m-d H:i:s', strtotime('+72 hours'));

        $stmt = $this->db->prepare(
            "INSERT INTO crm_client_service_requests
             (public_id, organization_id, client_public_id, category, title, description, service_status, sla_response_due_at, sla_resolution_due_at, sla_status, created_by_user_public_id, created_at, updated_at)
             VALUES (:pub_id, :org_id, :client_id, :cat, :title, :descr, 'submitted', :resp_due, :res_due, 'on_track', :usr_id, :now, :now)"
        );
        $stmt->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':client_id' => $clientPublicId,
            ':cat' => $category,
            ':title' => $title,
            ':descr' => $description,
            ':resp_due' => $responseDue,
            ':res_due' => $resolveDue,
            ':usr_id' => $userPublicId,
            ':now' => $now,
        ]);

        return $pubId;
    }

    /**
     * List requests for a specific client (Client View) or all in organization (Staff View).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRequests(int $organizationId, ?string $clientPublicId = null): array
    {
        if ($clientPublicId !== null) {
            $stmt = $this->db->prepare(
                "SELECT public_id, category, title, service_status, sla_status, sla_response_due_at, created_at, updated_at
                 FROM crm_client_service_requests
                 WHERE organization_id = :org_id AND client_public_id = :client_id
                 ORDER BY id DESC"
            );
            $stmt->execute([':org_id' => $organizationId, ':client_id' => $clientPublicId]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT public_id, client_public_id, category, title, service_status, sla_status, sla_response_due_at, created_at, updated_at
                 FROM crm_client_service_requests
                 WHERE organization_id = :org_id
                 ORDER BY id DESC"
            );
            $stmt->execute([':org_id' => $organizationId]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get single request details with client isolation check.
     *
     * @return array<string, mixed>|null
     */
    public function getRequest(int $organizationId, string $requestPublicId, ?string $clientPublicId = null): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_client_service_requests
             WHERE public_id = :pub_id AND organization_id = :org_id"
        );
        $stmt->execute([':pub_id' => $requestPublicId, ':org_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if ($clientPublicId !== null && (string)$row['client_public_id'] !== $clientPublicId) {
            return null; // Fail-closed on foreign client
        }

        return $row;
    }

    /**
     * Post a message on a service request. Staff can mark isClientVisible=false for internal notes.
     */
    public function postMessage(
        int $organizationId,
        string $requestPublicId,
        string $authorType,
        string $authorPublicId,
        string $text,
        bool $isClientVisible = true
    ): string {
        $now = date('Y-m-d H:i:s');
        $pubId = 'msg_' . bin2hex(random_bytes(10));

        // Verify request existence and org
        $req = $this->getRequest($organizationId, $requestPublicId);
        if (!$req) {
            throw new RuntimeException("Service request not found in organization.");
        }

        // Client can only post client-visible messages
        if ($authorType === 'client') {
            $isClientVisible = true;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO crm_client_service_messages
             (public_id, organization_id, request_public_id, author_type, author_public_id, is_client_visible, message_text, created_at)
             VALUES (:pub_id, :org_id, :req_id, :author_type, :author_id, :visible, :txt, :now)"
        );
        $stmt->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':req_id' => $requestPublicId,
            ':author_type' => $authorType,
            ':author_id' => $authorPublicId,
            ':visible' => $isClientVisible ? 1 : 0,
            ':txt' => $text,
            ':now' => $now,
        ]);

        return $pubId;
    }

    /**
     * Get messages for request, strictly filtering out internal messages when called by a client.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMessages(int $organizationId, string $requestPublicId, bool $isClientCaller = false): array
    {
        if ($isClientCaller) {
            $stmt = $this->db->prepare(
                "SELECT public_id, author_type, author_public_id, message_text, created_at
                 FROM crm_client_service_messages
                 WHERE organization_id = :org_id AND request_public_id = :req_id AND is_client_visible = 1
                 ORDER BY id ASC"
            );
        } else {
            $stmt = $this->db->prepare(
                "SELECT public_id, author_type, author_public_id, is_client_visible, message_text, created_at
                 FROM crm_client_service_messages
                 WHERE organization_id = :org_id AND request_public_id = :req_id
                 ORDER BY id ASC"
            );
        }
        $stmt->execute([':org_id' => $organizationId, ':req_id' => $requestPublicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Resolve an approval (approve or reject) with idempotency and state protection.
     */
    public function resolveApproval(
        int $organizationId,
        string $approvalPublicId,
        string $clientPublicId,
        string $decision,
        ?string $note = null
    ): bool {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuntimeException("Invalid approval decision.");
        }

        $stmt = $this->db->prepare(
            "SELECT a.*, r.client_public_id as req_client_id
             FROM crm_client_service_approvals a
             JOIN crm_client_service_requests r ON r.public_id = a.request_public_id
             WHERE a.public_id = :pub_id AND a.organization_id = :org_id"
        );
        $stmt->execute([':pub_id' => $approvalPublicId, ':org_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException("Approval request not found.");
        }

        if ((string)$row['req_client_id'] !== $clientPublicId) {
            throw new RuntimeException("Access denied: approval belongs to another client.");
        }

        if ($row['status'] !== 'pending') {
            return false; // Idempotent: already resolved
        }

        $now = date('Y-m-d H:i:s');
        $upd = $this->db->prepare(
            "UPDATE crm_client_service_approvals
             SET status = :status, resolution_note = :note, resolved_at = :now
             WHERE id = :id AND status = 'pending'"
        );
        $upd->execute([
            ':status' => $decision,
            ':note' => $note,
            ':now' => $now,
            ':id' => $row['id'],
        ]);

        return $upd->rowCount() > 0;
    }
}
