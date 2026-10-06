<?php
declare(strict_types=1);

namespace Module\Crm\PublicForms\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use PDO;

final class ListFormsTool
{
    public function handle(array $arguments, array $context): array
    {
        $orgId = (int)($context['user']['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();

        $stmt = $db->prepare(
            "SELECT public_id, slug, title, form_type, is_published, created_at
             FROM crm_public_forms
             WHERE organization_id = :org_id
             ORDER BY id DESC"
        );
        $stmt->execute([':org_id' => $orgId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'forms' => $items,
            'count' => count($items),
        ];
    }
}
