<?php
declare(strict_types=1);

namespace Module\Crm\StarterKits\Service;

use PDO;
use RuntimeException;
use InvalidArgumentException;
use Api\Model\Knowledge\KnowledgeRepository;
use Api\System\Library\Module\DomainEventPublisher;
use Api\System\Library\Module\ModuleEvents;

/**
 * Service managing workspace starter kits: catalog, preview/diff, conflict checking, and idempotent application.
 */
final class StarterKitService
{
    private PDO $db;
    private string $kitsDir;
    private KnowledgeRepository $knowledge;
    private ?DomainEventPublisher $events;

    public function __construct(PDO $db, ?string $kitsDir = null, ?DomainEventPublisher $events = null)
    {
        $this->db = $db;
        $this->kitsDir = $kitsDir ?? dirname(__DIR__, 2) . '/resources/kits';
        $this->knowledge = new KnowledgeRepository($db);
        $this->events = $events;
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
        $existingRoles = $this->fetchExistingCodes('roles', 'code', null); // Roles are global in the CRM core.
        foreach ($kit['roles'] ?? [] as $r) {
            $name = $this->starterRoleCode($r);
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
        // The core schema has a global UNIQUE constraint on knowledge_spaces.slug.
        $existingSpaces = $this->fetchExistingCodes('knowledge_spaces', 'slug', null);
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
    public function apply(string $kitId, int $organizationId, ?int $userId = null, ?array $selectedComponents = null, string $locale = 'ru-ru', bool $actorIsRoot = false): array
    {
        $allComponents = ['statuses', 'roles', 'project_templates', 'task_templates', 'knowledge_spaces'];
        if ($organizationId <= 0 || $userId === null || $userId <= 0) {
            throw new InvalidArgumentException('A valid workspace and authenticated user are required.');
        }
        if ($selectedComponents === null || $selectedComponents === [] || count($selectedComponents) > count($allComponents)) {
            throw new InvalidArgumentException('Select one or more starter kit components.');
        }
        foreach ($selectedComponents as $component) {
            if (!is_string($component) || !in_array($component, $allComponents, true)) {
                throw new InvalidArgumentException('Unknown starter kit component.');
            }
        }
        $components = array_values(array_unique($selectedComponents));
        if (in_array('roles', $components, true) && !$actorIsRoot) {
            throw new RuntimeException('Only a root administrator can create global roles.');
        }
        $preview = $this->preview($kitId, $organizationId, $locale);
        $kit = $this->getKit($kitId);
        if ($kit === null) {
            throw new RuntimeException("Starter kit '{$kitId}' not found.");
        }

        $createdCounts = [
            'statuses' => 0,
            'roles' => 0,
            'project_templates' => 0,
            'task_templates' => 0,
            'knowledge_spaces' => 0,
        ];
        $conflictsSkipped = 0;
        $createdObjectIds = [];
        $createdKnowledgePageIds = [];
        $createdKnowledgeEventIds = [];
        $repairedKnowledgeEventIds = [];

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
                         VALUES (:pub_id, :scope, :code, :title, :color, :sort_order, 1, :is_closed, :org_id, :created_at, :updated_at)"
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
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]);
                    $createdCounts['statuses']++;
                    $createdObjectIds[] = ['type' => 'status', 'public_id' => $pubId, 'code' => $code];
                }
            }

            // 2. Apply Roles
            if (in_array('roles', $components, true)) {
                $existing = $this->fetchExistingCodes('roles', 'code', null);
                foreach ($kit['roles'] ?? [] as $r) {
                    $name = $this->starterRoleCode($r);
                    if (in_array($name, $existing, true)) {
                        $conflictsSkipped++;
                        continue;
                    }
                    $title = $this->resolveLocaleText($r['titles'] ?? [], $locale, $name);
                    $stmt = $this->db->prepare(
                        "INSERT INTO `roles` (public_id, code, title, is_system, created_at, updated_at)
                         VALUES (:public_id, :code, :title, 0, :created_at, :updated_at)"
                    );
                    $stmt->execute([
                        ':public_id' => 'rol_' . bin2hex(random_bytes(10)),
                        ':code' => $name,
                        ':title' => $title,
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]);
                    $roleId = (int)$this->db->lastInsertId();

                    // Add every declared permission or fail the transaction rather than creating a misleading partial role.
                    if (!empty($r['permissions'])) {
                        $permissionRows = [];
                        foreach ($r['permissions'] as $permCode) {
                            $pStmt = $this->db->prepare("SELECT id FROM permissions WHERE code = :code");
                            $pStmt->execute([':code' => $permCode]);
                            $permId = $pStmt->fetchColumn();
                            if (!$permId) {
                                throw new RuntimeException('A required starter kit permission is unavailable.');
                            }
                            $permissionRows[] = (int)$permId;
                        }
                        $rpStmt = $this->db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:role_id, :perm_id, :created_at)");
                        foreach ($permissionRows as $permId) {
                            $rpStmt->execute([':role_id' => $roleId, ':perm_id' => $permId, ':created_at' => $now]);
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
                         VALUES (:pub_id, :title, :payload, 1, :user_id, :org_id, :created_at, :updated_at)"
                    );
                    $stmt->execute([
                        ':pub_id' => $pubId,
                        ':title' => $title,
                        ':payload' => $payload,
                        ':user_id' => $userId,
                        ':org_id' => $organizationId,
                        ':created_at' => $now,
                        ':updated_at' => $now,
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
                         VALUES (:pub_id, :title, :payload, 1, :user_id, :org_id, :created_at, :updated_at)"
                    );
                    $stmt->execute([
                        ':pub_id' => $pubId,
                        ':title' => $title,
                        ':payload' => $payload,
                        ':user_id' => $userId,
                        ':org_id' => $organizationId,
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]);
                    $createdCounts['task_templates']++;
                    $createdObjectIds[] = ['type' => 'task_template', 'public_id' => $pubId, 'title' => $title];
                }
            }

            // 5. Apply Knowledge Spaces
            if (in_array('knowledge_spaces', $components, true)) {
                // Slugs are globally unique in the core schema, not per workspace.
                $existing = $this->fetchExistingCodes('knowledge_spaces', 'slug', null);
                foreach ($kit['knowledge_spaces'] ?? [] as $ks) {
                    $slug = (string)$ks['slug'];
                    if (in_array($slug, $existing, true)) {
                        $this->repairLegacyKnowledgeSpace($ks, $kit['id'], $slug, $organizationId, $createdKnowledgePageIds, $repairedKnowledgeEventIds);
                        $conflictsSkipped++;
                        continue;
                    }
                    $title = $this->resolveLocaleText($ks['titles'] ?? [], $locale, $slug);
                    $actor = ['id' => $userId, 'organization_id' => $organizationId, 'is_root' => $actorIsRoot];
                    $space = $this->knowledge->createSpace([
                        'title' => $title,
                        'slug' => $slug,
                        'description' => (string)($ks['description'] ?? ''),
                        'visibility' => 'public',
                        'default_access_level' => 'view',
                    ], $userId, $actor);
                    $pubId = (string)($space['public_id'] ?? '');
                    $spaceId = (int)($space['id'] ?? 0);
                    if ($pubId === '' || $spaceId <= 0) {
                        throw new RuntimeException('Knowledge space could not be created.');
                    }
                    $sourceStmt = $this->db->prepare(
                        "UPDATE knowledge_spaces SET source_type = :source_type, source_id = :source_id WHERE id = :id"
                    );
                    $sourceStmt->execute([':source_type' => 'starter_kit', ':source_id' => (string)$kit['id'], ':id' => $spaceId]);

                    // Insert child pages
                    foreach ($ks['pages'] ?? [] as $p) {
                        $pageTitle = $this->resolveLocaleText($p['titles'] ?? [], $locale, (string)$p['slug']);
                        $content = (string)($p['content'] ?? '');
                        $page = $this->knowledge->createPage([
                            'space_public_id' => $pubId,
                            'title' => $pageTitle,
                            'slug' => (string)$p['slug'],
                            'page_type' => 'article',
                            'status' => 'draft',
                            'content_html' => $this->markdownToSafeHtml($content),
                        ], $userId, $actor);
                        $pageId = (int)($page['id'] ?? 0);
                        if ($pageId <= 0 || trim((string)($page['path'] ?? '')) === '') {
                            throw new RuntimeException('Knowledge page could not be created.');
                        }
                        $createdKnowledgePageIds[] = $pageId;
                        $createdKnowledgeEventIds[] = $pageId;
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

            foreach (array_values(array_unique($createdKnowledgePageIds)) as $pageId) {
                $this->knowledge->reindexPage($pageId);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->publishKnowledgePageEvents($createdKnowledgeEventIds, $userId, $organizationId);
        $this->publishKnowledgePageEvents($repairedKnowledgeEventIds, $userId, $organizationId, ModuleEvents::KNOWLEDGE_PAGE_UPDATED);

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
     * Starter kit roles are global templates. Keep them out of the implicit
     * default-role candidate set used when creating ordinary CRM users.
     */
    private function starterRoleCode(array $role): string
    {
        $code = strtolower(trim((string)($role['name'] ?? '')));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code) ?? '';
        return 'starterkit_' . $code;
    }

    private function markdownToSafeHtml(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $blocks = preg_split('/\n\s*\n/u', trim($markdown)) ?: [];
        $html = [];
        foreach ($blocks as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), static fn(string $line): bool => $line !== ''));
            if ($lines === []) {
                continue;
            }
            $escaped = array_map(static fn(string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $lines);
            if (preg_match('/^#{1,6}\s/', $lines[0]) === 1 && count($lines) === 1) {
                preg_match('/^(#{1,6})\s+(.*)$/u', $escaped[0], $match);
                $level = strlen($match[1] ?? '#');
                $html[] = '<h' . $level . '>' . ($match[2] ?? '') . '</h' . $level . '>';
                continue;
            }
            $isList = count(array_filter($lines, static fn(string $line): bool => preg_match('/^\s*[-*]\s+/', $line) === 1)) === count($lines);
            if ($isList) {
                $items = array_map(static fn(string $line): string => '<li>' . preg_replace('/^\s*[-*]\s+/', '', htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</li>', $lines);
                $html[] = '<ul>' . implode('', $items) . '</ul>';
                continue;
            }
            $html[] = '<p>' . implode('<br>', $escaped) . '</p>';
        }
        return implode("\n", $html);
    }

    /**
     * Repair only pages created by an earlier Starter Kits version in this workspace.
     * User-edited pages are left untouched; the legacy body is replaced only when it
     * still exactly matches the original escaped fixture.
     *
     * @param array<int, int> $pageIds
     * @param array<int, int> $updatedPageIds
     */
    private function repairLegacyKnowledgeSpace(array $spaceDefinition, string $kitId, string $slug, int $organizationId, array &$pageIds, array &$updatedPageIds): void
    {
        $spaceStmt = $this->db->prepare(
            "SELECT id, public_id FROM knowledge_spaces WHERE slug = :slug AND organization_id = :organization_id AND source_type = 'starter_kit' AND source_id = :source_id LIMIT 1"
        );
        $spaceStmt->execute([':slug' => $slug, ':organization_id' => $organizationId, ':source_id' => $kitId]);
        $space = $spaceStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($space)) {
            return;
        }

        foreach ($spaceDefinition['pages'] ?? [] as $definition) {
            $pageStmt = $this->db->prepare('SELECT id, public_id, slug, path, parent_id, content_html FROM knowledge_pages WHERE space_id = :space_id AND slug = :slug AND deleted_at IS NULL LIMIT 1');
            $pageStmt->execute([':space_id' => (int)$space['id'], ':slug' => (string)$definition['slug']]);
            $page = $pageStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($page)) {
                continue;
            }

            $pageId = (int)$page['id'];
            $fixture = (string)($definition['content'] ?? '');
            $legacyHtml = nl2br(htmlspecialchars($fixture, ENT_QUOTES, 'UTF-8'));
            $updates = [];
            $params = [':id' => $pageId];
            if ((string)($page['content_html'] ?? '') === $legacyHtml) {
                $html = $this->markdownToSafeHtml($fixture);
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('#</(p|h[1-6]|li|ul|ol|br)>#i', ' ', $html) ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
                $updates[] = 'content_html = :content_html';
                $updates[] = 'content_text = :content_text';
                $updates[] = 'excerpt = :excerpt';
                $params[':content_html'] = $html;
                $params[':content_text'] = $text;
                $params[':excerpt'] = mb_substr($text, 0, 260);
                $pageIds[] = $pageId;
            }

            if (($page['parent_id'] ?? null) === null && trim((string)($page['path'] ?? '')) === '') {
                $updates[] = 'path = :path';
                $updates[] = 'depth = 0';
                $params[':path'] = '/' . (string)$page['slug'];
                $pageIds[] = $pageId;
            }
            if ($updates !== []) {
                $updates[] = 'row_version = row_version + 1';
                $updates[] = 'updated_at = :updated_at';
                $params[':updated_at'] = gmdate('Y-m-d H:i:s');
                $this->db->prepare('UPDATE knowledge_pages SET ' . implode(', ', $updates) . ' WHERE id = :id')->execute($params);
                $updatedPageIds[] = $pageId;
            }
        }
    }

    /** @param array<int, int> $pageIds */
    private function publishKnowledgePageEvents(array $pageIds, int $userId, int $organizationId, string $event = ModuleEvents::KNOWLEDGE_PAGE_CREATED): void
    {
        if ($this->events === null || $pageIds === []) {
            return;
        }
        $actorStmt = $this->db->prepare('SELECT public_id FROM users WHERE id = :id LIMIT 1');
        $actorStmt->execute([':id' => $userId]);
        $actorPublicId = (string)($actorStmt->fetchColumn() ?: '');
        $actor = ['id' => $userId, 'public_id' => $actorPublicId, 'organization_id' => $organizationId];

        $pageStmt = $this->db->prepare(
            'SELECT p.public_id, p.title, p.slug, p.page_type, p.status, p.owner_user_id, p.last_editor_user_id, p.updated_at, s.public_id AS space_public_id '
            . 'FROM knowledge_pages p JOIN knowledge_spaces s ON s.id = p.space_id WHERE p.id = :id LIMIT 1'
        );
        foreach (array_values(array_unique($pageIds)) as $pageId) {
            $pageStmt->execute([':id' => $pageId]);
            $page = $pageStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($page)) {
                continue;
            }
            try {
                $payload = [
                    'page_public_id' => (string)$page['public_id'],
                    'space_public_id' => (string)$page['space_public_id'],
                    'title' => (string)$page['title'],
                    'slug' => (string)$page['slug'],
                    'page_type' => (string)$page['page_type'],
                    'status' => (string)$page['status'],
                    'owner_user_id' => (int)$page['owner_user_id'],
                    'last_editor_user_id' => (int)$page['last_editor_user_id'],
                    'updated_at' => (string)$page['updated_at'],
                ];
                if ($event === ModuleEvents::KNOWLEDGE_PAGE_UPDATED) {
                    $payload['changed_fields'] = ['content_html', 'content_text', 'excerpt', 'path', 'depth'];
                }
                $this->events->publish($event, $payload, $actor, $organizationId, 'module');
            } catch (\Throwable) {
            }
        }
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
