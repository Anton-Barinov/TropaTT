<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Service;

use PDO;
use RuntimeException;

/**
 * Service managing workspace starter kits: catalog, preview/diff, conflict checking, and idempotent application.
 */
final class StarterKitService
{
    private PDO $db;
    private string $kitsDir;

    public function __construct(PDO $db, ?string $kitsDir = null)
    {
        $this->db = $db;
        $this->kitsDir = $kitsDir ?? dirname(__DIR__, 2) . '/resources/kits';
    }

    /**
     * @return array<int, array{id: string, version: string, title: string, description: string, components_count: array<string, int>}>
     */
    public function listKits(string $locale = 'ru-ru'): array
    {
        $result = [];
        $files = glob($this->kitsDir . '/*.json') ?: [];
        sort($files);

        foreach ($files as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data) || !isset($data['id'])) {
                continue;
            }

            $result[] = [
                'id' => (string)$data['id'],
                'version' => (string)($data['version'] ?? '1.0.0'),
                'title' => $this->resolveLocaleText($data['titles'] ?? [], $locale, (string)$data['id']),
                'description' => $this->resolveLocaleText($data['descriptions'] ?? [], $locale, ''),
                'components_count' => [
                    'statuses' => count($data['statuses'] ?? []),
                    'roles' => count($data['roles'] ?? []),
                    'project_templates' => count($data['project_templates'] ?? []),
                    'task_templates' => count($data['task_templates'] ?? []),
                    'knowledge_spaces' => count($data['knowledge_spaces'] ?? []),
                ],
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getKit(string $kitId): ?array
    {
        $cleanId = basename($kitId);
        $file = $this->kitsDir . '/' . str_replace('kit_', '', $cleanId) . '.json';
        if (!file_exists($file)) {
            $file = $this->kitsDir . '/' . $cleanId . '.json';
        }
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Preview diff and detect conflicts for a target organization workspace.
     *
     * @return array{kit_id: string, version: string, title: string, will_create: array<string, array>, conflicts: array<string, array>, is_already_applied: bool}
     */
    public function preview(string $kitId, int $organizationId, string $locale = 'ru-ru'): array
    {
        $kit = $this->getKit($kitId);
        if ($kit === null) {
            throw new RuntimeException("Starter kit '{$kitId}' not found.");
        }

        // Check if previously applied
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM crm_starter_kits_applied WHERE organization_id = :org_id AND kit_id = :kit_id");
        $stmt->execute([':org_id' => $organizationId, ':kit_id' => $kit['id']]);
        $alreadyApplied = ((int)$stmt->fetchColumn()) > 0;

        $willCreate = [
            'statuses' => [],
            'roles' => [],
            'project_templates' => [],
            'task_templates' => [],
            'knowledge_spaces' => [],
        ];
        $conflicts = [
            'statuses' => [],
            'roles' => [],
            'project_templates' => [],
            'task_templates' => [],
            'knowledge_spaces' => [],
        ];

        // 1. Statuses check
        $existingStatuses = $this->fetchExistingCodes('statuses', 'code', $organizationId);
        foreach ($kit['statuses'] ?? [] as $st) {
            $code = (string)$st['code'];
            $title = $this->resolveLocaleText($st['titles'] ?? [], $locale, $code);
            if (in_array($code, $existingStatuses, true)) {
                $conflicts['statuses'][] = ['code' => $code, 'title' => $title, 'reason' => 'Status code already exists in workspace.'];
            } else {
                $willCreate['statuses'][] = ['code' => $code, 'title' => $title, 'color' => $st['color'] ?? '#64748b'];
            }
        }

        // 2. Roles check
        $existingRoles = $this->fetchExistingCodes('roles', 'name', null); // global roles or workspace roles
        foreach ($kit['roles'] ?? [] as $r) {
            $name = (string)$r['name'];
            $title = $this->resolveLocaleText($r['titles'] ?? [], $locale, $name);
            if (in_array($name, $existingRoles, true)) {
                $conflicts['roles'][] = ['name' => $name, 'title' => $title, 'reason' => 'Role name already exists.'];
            } else {
                $willCreate['roles'][] = ['name' => $name, 'title' => $title, 'permissions' => $r['permissions'] ?? []];
            }
        }

        // 3. Project templates check
        $existingProjTpl = $this->fetchExistingTitles('project_templates', $organizationId);
        foreach ($kit['project_templates'] ?? [] as $pt) {
            $title = $this->resolveLocaleText($pt['titles'] ?? [], $locale, (string)$pt['code']);
            if (in_array(mb_strtolower($title), $existingProjTpl, true)) {
                $conflicts['project_templates'][] = ['title' => $title, 'reason' => 'Template with identical title exists.'];
            } else {
                $willCreate['project_templates'][] = ['title' => $title, 'tasks_count' => count($pt['tasks'] ?? [])];
            }
        }

        // 4. Task templates check
        $existingTaskTpl = $this->fetchExistingTitles('task_templates', $organizationId);
        foreach ($kit['task_templates'] ?? [] as $tt) {
            $title = $this->resolveLocaleText($tt['titles'] ?? [], $locale, (string)$tt['code']);
            if (in_array(mb_strtolower($title), $existingTaskTpl, true)) {
                $conflicts['task_templates'][] = ['title' => $title, 'reason' => 'Task template with identical title exists.'];
            } else {
                $willCreate['task_templates'][] = ['title' => $title, 'checklist_count' => count($tt['checklist'] ?? [])];
            }
        }

        // 5. Knowledge spaces check
        $existingSpaces = $this->fetchExistingCodes('knowledge_spaces', 'slug', $organizationId);
        foreach ($kit['knowledge_spaces'] ?? [] as $ks) {
            $slug = (string)$ks['slug'];
            $title = $this->resolveLocaleText($ks['titles'] ?? [], $locale, $slug);
            if (in_array($slug, $existingSpaces, true)) {
                $conflicts['knowledge_spaces'][] = ['slug' => $slug, 'title' => $title, 'reason' => 'Space with slug exists.'];
            } else {
                $willCreate['knowledge_spaces'][] = ['slug' => $slug, 'title' => $title, 'pages_count' => count($ks['pages'] ?? [])];
            }
        }

        return [
            'kit_id' => (string)$kit['id'],
            'version' => (string)($kit['version'] ?? '1.0.0'),
            'title' => $this->resolveLocaleText($kit['titles'] ?? [], $locale, (string)$kit['id']),
            'will_create' => $willCreate,
            'conflicts' => $conflicts,
            'is_already_applied' => $alreadyApplied,
        ];
    }

    /**
     * Apply kit components idempotently.
     *
     * @param array<int, string>|null $selectedComponents
     * @return array{ok: bool, kit_id: string, applied_objects: array<string, int>, conflicts_skipped: int}
     */
    public function apply(string $kitId, int $organizationId, ?int $userId = null, ?array $selectedComponents = null, string $locale = 'ru-ru'): array
    {
        $preview = $this->preview($kitId, $organizationId, $locale);
        $kit = $this->getKit($kitId);
        if ($kit === null) {
            throw new RuntimeException("Starter kit '{$kitId}' not found.");
        }

        $allComponents = ['statuses', 'roles', 'project_templates', 'task_templates', 'knowledge_spaces'];
        $components = $selectedComponents !== null ? array_intersect($allComponents, $selectedComponents) : $allComponents;

        $createdCounts = [
            'statuses' => 0,
            'roles' => 0,
            'project_templates' => 0,
            'task_templates' => 0,
            'knowledge_spaces' => 0,
        ];
        $conflictsSkipped = 0;
        $createdObjectIds = [];

        $this->db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');

            // 1. Apply Statuses
            if (in_array('statuses', $components, true)) {
                $existing = $this->fetchExistingCodes('statuses', 'code', $organizationId);
                foreach ($kit['statuses'] ?? [] as $st) {
                    $code = (string)$st['code'];
                    if (in_array($code, $existing, true)) {
                        $conflictsSkipped++;
                        continue;
                    }
                    $pubId = 'sts_' . bin2hex(random_bytes(10));
                    $title = $this->resolveLocaleText($st['titles'] ?? [], $locale, $code);
                    $stmt = $this->db->prepare(
                        "INSERT INTO `statuses` (public_id, scope, code, title, color, sort_order, is_active, is_closed, organization_id, created_at, updated_at)
                         VALUES (:pub_id, :scope, :code, :title, :color, :sort_order, 1, :is_closed, :org_id, :now, :now)"
                    );
                    $stmt->execute([
                        ':pub_id' => $pubId,
                        ':scope' => (string)($st['scope'] ?? 'task'),
                        ':code' => $code,
                        ':title' => $title,
                        ':color' => (string)($st['color'] ?? '#64748b'),
                        ':sort_order' => (int)($st['sort_order'] ?? 10),
                        ':is_closed' => (int)($st['is_closed'] ?? 0),
                        ':org_id' => $organizationId,
                        ':now' => $now,
                    ]);
                    $createdCounts['statuses']++;
                    $createdObjectIds[] = ['type' => 'status', 'public_id' => $pubId, 'code' => $code];
                }
            }

            // 2. Apply Roles
            if (in_array('roles', $components, true)) {
                $existing = $this->fetchExistingCodes('roles', 'name', null);
                foreach ($kit['roles'] ?? [] as $r) {
                    $name = (string)$r['name'];
                    if (in_array($name, $existing, true)) {
                        $conflictsSkipped++;
                        continue;
                    }
                    $title = $this->resolveLocaleText($r['titles'] ?? [], $locale, $name);
                    $stmt = $this->db->prepare(
                        "INSERT INTO `roles` (name, title, description, is_system, created_at, updated_at)
                         VALUES (:name, :title, :desc, 0, :now, :now)"
                    );
                    $stmt->execute([
                        ':name' => $name,
                        ':title' => $title,
                        ':desc' => "Created from starter kit {$kit['id']}",
                        ':now' => $now,
                    ]);
                    $roleId = (int)$this->db->lastInsertId();

                    // Insert role permissions if permissions table has matching codes
                    if (!empty($r['permissions'])) {
                        foreach ($r['permissions'] as $permCode) {
                            $pStmt = $this->db->prepare("SELECT id FROM permissions WHERE code = :code");
                            $pStmt->execute([':code' => $permCode]);
                            $permId = $pStmt->fetchColumn();
                            if ($permId) {
                                $rpStmt = $this->db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :perm_id)");
                                $rpStmt->execute([':role_id' => $roleId, ':perm_id' => $permId]);
                            }
                        }
                    }

                    $createdCounts['roles']++;
                    $createdObjectIds[] = ['type' => 'role', 'name' => $name];
                }
            }

            // 3. Apply Project Templates
            if (in_array('project_templates', $components, true)) {
                $existing = $this->fetchExistingTitles('project_templates', $organizationId);
                foreach ($kit['project_templates'] ?? [] as $pt) {
                    $title = $this->resolveLocaleText($pt['titles'] ?? [], $locale, (string)$pt['code']);
                    if (in_array(mb_strtolower($title), $existing, true)) {
                        $conflictsSkipped++;
                        continue;
                    }
                    $pubId = 'ptp_' . bin2hex(random_bytes(10));
                    $payload = json_encode([
                        'source_kit' => $kit['id'],
                        'tasks' => $pt['tasks'] ?? [],
                        'description' => $pt['description'] ?? '',
                    ], JSON_UNESCAPED_UNICODE);

                    $stmt = $this->db->prepare(
                        "INSERT INTO `project_templates` (public_id, title, payload, is_active, created_by_user_id, organization_id, created_at, updated_at)
                         VALUES (:pub_id, :title, :payload, 1, :user_id, :org_id, :now, :now)"
                    );
                    $stmt->execute([
                        ':pub_id' => $pubId,
                        ':title' => $title,
                        ':payload' => $payload,
                        ':user_id' => $userId,
                        ':org_id' => $organizationId,
                        ':now' => $now,
                    ]);
                    $createdCounts['project_templates']++;
                    $createdObjectIds[] = ['type' => 'project_template', 'public_id' => $pubId, 'title' => $title];
                }
            }

            // 4. Apply Task Templates
            if (in_array('task_templates', $components, true)) {
                $existing = $this->fetchExistingTitles('task_templates', $organizationId);
                foreach ($kit['task_templates'] ?? [] as $tt) {
                    $title = $this->resolveLocaleText($tt['titles'] ?? [], $locale, (string)$tt['code']);
                    if (in_array(mb_strtolower($title), $existing, true)) {
                        $conflictsSkipped++;
                        continue;
                    }
                    $pubId = 'ttp_' . bin2hex(random_bytes(10));
                    $payload = json_encode([
                        'source_kit' => $kit['id'],
                        'checklist' => $tt['checklist'] ?? [],
                    ], JSON_UNESCAPED_UNICODE);

                    $stmt = $this->db->prepare(
                        "INSERT INTO `task_templates` (public_id, title, payload, is_active, created_by_user_id, organization_id, created_at, updated_at)
                         VALUES (:pub_id, :title, :payload, 1, :user_id, :org_id, :now, :now)"
                    );
                    $stmt->execute([
                        ':pub_id' => $pubId,
                        ':title' => $title,
                        ':payload' => $payload,
                        ':user_id' => $userId,
                        ':org_id' => $organizationId,
                        ':now' => $now,
                    ]);
                    $createdCounts['task_templates']++;
                    $createdObjectIds[] = ['type' => 'task_template', 'public_id' => $pubId, 'title' => $title];
                }
            }

            // 5. Apply Knowledge Spaces
            if (in_array('knowledge_spaces', $components, true)) {
                $existing = $this->fetchExistingCodes('knowledge_spaces', 'slug', $organizationId);
                foreach ($kit['knowledge_spaces'] ?? [] as $ks) {
                    $slug = (string)$ks['slug'];
                    if (in_array($slug, $existing, true)) {
                        $conflictsSkipped++;
                        continue;
                    }
                    $pubId = 'kbs_' . bin2hex(random_bytes(10));
                    $title = $this->resolveLocaleText($ks['titles'] ?? [], $locale, $slug);
                    $stmt = $this->db->prepare(
                        "INSERT INTO `knowledge_spaces` (public_id, title, slug, description, visibility, default_access_level, owner_user_id, organization_id, source_type, source_id, created_at, updated_at)
                         VALUES (:pub_id, :title, :slug, :desc, 'public', 'view', :user_id, :org_id, 'starter_kit', :kit_id, :now, :now)"
                    );
                    $stmt->execute([
                        ':pub_id' => $pubId,
                        ':title' => $title,
                        ':slug' => $slug,
                        ':desc' => (string)($ks['description'] ?? ''),
                        ':user_id' => $userId,
                        ':org_id' => $organizationId,
                        ':kit_id' => $kit['id'],
                        ':now' => $now,
                    ]);
                    $spaceId = (int)$this->db->lastInsertId();

                    // Insert child pages
                    foreach ($ks['pages'] ?? [] as $p) {
                        $pagePubId = 'kbp_' . bin2hex(random_bytes(10));
                        $pageTitle = $this->resolveLocaleText($p['titles'] ?? [], $locale, (string)$p['slug']);
                        $content = (string)($p['content'] ?? '');
                        $pStmt = $this->db->prepare(
                            "INSERT INTO `knowledge_pages` (public_id, space_id, title, slug, content_text, content_html, status, author_user_id, created_at, updated_at)
                             VALUES (:pub_id, :space_id, :title, :slug, :content_text, :content_html, 'published', :user_id, :now, :now)"
                        );
                        $pStmt->execute([
                            ':pub_id' => $pagePubId,
                            ':space_id' => $spaceId,
                            ':title' => $pageTitle,
                            ':slug' => (string)$p['slug'],
                            ':content_text' => strip_tags($content),
                            ':content_html' => nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')),
                            ':user_id' => $userId,
                            ':now' => $now,
                        ]);
                    }

                    $createdCounts['knowledge_spaces']++;
                    $createdObjectIds[] = ['type' => 'knowledge_space', 'public_id' => $pubId, 'slug' => $slug];
                }
            }

            // Record applied kit
            $appliedPubId = 'ska_' . bin2hex(random_bytes(10));
            $logStmt = $this->db->prepare(
                "INSERT INTO `crm_starter_kits_applied` (public_id, organization_id, kit_id, kit_version, applied_by_user_id, components_json, created_objects_json, created_at)
                 VALUES (:pub_id, :org_id, :kit_id, :kit_version, :user_id, :comp_json, :obj_json, :now)"
            );
            $logStmt->execute([
                ':pub_id' => $appliedPubId,
                ':org_id' => $organizationId,
                ':kit_id' => (string)$kit['id'],
                ':kit_version' => (string)($kit['version'] ?? '1.0.0'),
                ':user_id' => $userId,
                ':comp_json' => json_encode($components),
                ':obj_json' => json_encode($createdObjectIds, JSON_UNESCAPED_UNICODE),
                ':now' => $now,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'ok' => true,
            'kit_id' => (string)$kit['id'],
            'applied_objects' => $createdCounts,
            'conflicts_skipped' => $conflictsSkipped,
        ];
    }

    /**
     * @param array<string, string> $titles
     */
    private function resolveLocaleText(array $titles, string $targetLocale, string $fallback): string
    {
        if (isset($titles[$targetLocale]) && trim((string)$titles[$targetLocale]) !== '') {
            return (string)$titles[$targetLocale];
        }
        if (isset($titles['en-gb']) && trim((string)$titles['en-gb']) !== '') {
            return (string)$titles['en-gb'];
        }
        if (isset($titles['ru-ru']) && trim((string)$titles['ru-ru']) !== '') {
            return (string)$titles['ru-ru'];
        }
        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function fetchExistingCodes(string $table, string $column, ?int $orgId): array
    {
        $sql = "SELECT DISTINCT `{$column}` FROM `{$table}` WHERE 1=1";
        $params = [];
        if ($orgId !== null) {
            $sql .= " AND (`organization_id` = :org_id OR `organization_id` IS NULL)";
            $params[':org_id'] = $orgId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * @return array<int, string>
     */
    private function fetchExistingTitles(string $table, int $orgId): array
    {
        $stmt = $this->db->prepare("SELECT title FROM `{$table}` WHERE (`organization_id` = :org_id OR `organization_id` IS NULL)");
        $stmt->execute([':org_id' => $orgId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_map(static fn($t) => mb_strtolower(trim((string)$t)), $rows);
    }
}
