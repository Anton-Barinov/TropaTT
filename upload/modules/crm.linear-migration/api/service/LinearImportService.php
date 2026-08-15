<?php
declare(strict_types=1);

namespace Module\Crm\LinearMigration\Service;

use Api\System\Library\Container;
use Module\Crm\LinearMigration\Repository\LinearRepository;
use PDO;
use RuntimeException;

final class LinearImportService
{
    public function __construct(
        private readonly Container $container,
        private readonly LinearRepository $repo,
        private readonly LinearClient $client,
    ) {
    }

    private function service(string $id): mixed
    {
        return $this->container->get($id);
    }

    private function pdo(): PDO
    {
        return $this->container->get('db.pdo');
    }

    /**
     * Crawl the Linear source graph into job items (idempotent).
     *
     * @return array<string, mixed>
     */
    public function crawl(array $job, string $apiKey): array
    {
        $jobId = (int)$job['id'];
        $connectionId = (int)$job['connection_id'];
        $teamIds = $this->jsonArray($job['source_team_ids_json'] ?? null);
        $options = $this->decodeJson($job['options_json'] ?? null);
        $maxIssues = max(0, (int)($options['max_issues_per_job'] ?? 0));

        $teams = $this->client->listTeams($apiKey);
        $projects = $this->client->listProjects($apiKey);
        $issues = $teamIds !== [] ? $this->client->listIssues($apiKey, $teamIds) : [];

        if ($maxIssues > 0) {
            $issues = array_slice($issues, 0, $maxIssues);
        }

        $counts = ['team' => 0, 'project' => 0, 'label' => 0, 'issue' => 0, 'comment' => 0];

        foreach ($teams as $team) {
            $id = (string)($team['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $this->repo->upsertJobItem($jobId, 'team', $id, [
                'source_parent_id' => null,
                'payload_json' => $team,
                'status' => 'skipped',
            ]);
            $counts['team']++;
        }

        foreach ($projects as $project) {
            $id = (string)($project['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $this->repo->upsertJobItem($jobId, 'project', $id, [
                'source_parent_id' => null,
                'payload_json' => $project,
                'status' => 'pending',
            ]);
            $counts['project']++;
        }

        foreach ($issues as $issue) {
            $id = (string)($issue['id'] ?? '');
            if ($id === '') {
                continue;
            }

            // Labels
            foreach (($issue['labels']['nodes'] ?? []) as $label) {
                $labelId = (string)($label['id'] ?? '');
                if ($labelId === '') {
                    continue;
                }
                $this->repo->upsertJobItem($jobId, 'label', $labelId, [
                    'source_parent_id' => null,
                    'payload_json' => $label,
                    'status' => 'pending',
                ]);
                $counts['label']++;
            }

            // User mapping (assignee)
            $assignee = $issue['assignee'] ?? null;
            if (is_array($assignee) && !empty($assignee['id'])) {
                $this->repo->upsertUserMapping($connectionId, (string)$assignee['id'], [
                    'display_name' => (string)($assignee['name'] ?? ''),
                    'email' => (string)($assignee['email'] ?? ''),
                    'mapping_status' => 'unmapped',
                ]);
            }

            $parentId = is_array($issue['parent'] ?? null) ? (string)($issue['parent']['id'] ?? '') : '';

            $this->repo->upsertJobItem($jobId, 'issue', $id, [
                'source_parent_id' => $parentId !== '' ? $parentId : null,
                'payload_json' => $issue,
                'status' => 'pending',
            ]);
            $counts['issue']++;

            // Comments
            foreach (($issue['comments']['nodes'] ?? []) as $comment) {
                $commentId = (string)($comment['id'] ?? '');
                if ($commentId === '') {
                    continue;
                }
                $this->repo->upsertJobItem($jobId, 'comment', $commentId, [
                    'source_parent_id' => $id,
                    'payload_json' => $comment,
                    'status' => 'pending',
                ]);
                $counts['comment']++;
            }
        }

        return $counts;
    }

    /**
     * Import one bounded chunk of job items.
     *
     * @param array<string, mixed> $job
     * @param array<string, mixed> $actor
     * @return array{done: bool, processed: int, counts: array<string, int>}
     */
    public function importChunk(array $job, array $actor, int $maxItems): array
    {
        $jobId = (int)$job['id'];
        $jobPublicId = (string)$job['public_id'];
        $connectionId = (int)$job['connection_id'];
        $mode = (string)$job['mode'];
        $processed = 0;

        // Import order: project → label → issue → comment → parent-link.
        foreach (['project', 'label', 'issue', 'comment'] as $type) {
            if ($processed >= $maxItems) {
                break;
            }
            $items = $this->repo->listJobItems($jobPublicId, 'pending', $type, $maxItems - $processed);
            foreach ($items as $item) {
                if ($processed >= $maxItems) {
                    break;
                }
                $processed++;
                $payload = json_decode((string)($item['payload_json'] ?? '{}'), true) ?: [];
                try {
                    $result = match ($type) {
                        'project' => $this->importProject($job, $payload, $actor),
                        'label' => $this->importLabel($job, $payload, $actor),
                        'issue' => $this->importIssue($job, $payload, $actor),
                        'comment' => $this->importComment($job, $payload, $actor),
                        default => ['state' => 'skipped', 'target_public_id' => ''],
                    };
                    $target = (string)($result['target_public_id'] ?? '');
                    $this->repo->upsertJobItem($jobId, $type, (string)$item['source_id'], [
                        'target_type' => $result['target_type'] ?? null,
                        'target_public_id' => $target !== '' ? $target : null,
                        'status' => $result['state'],
                        'error_code' => null,
                        'error_message' => null,
                    ]);
                } catch (\Throwable $e) {
                    error_log('[LinearImportService::importChunk] ' . $type . ' ' . $item['source_id'] . ': ' . $e->getMessage());
                    $this->repo->upsertJobItem($jobId, $type, (string)$item['source_id'], [
                        'status' => 'failed',
                        'error_code' => 'IMPORT_FAILED',
                        'error_message' => 'Item import failed. Check the migration log.',
                    ]);
                    $this->repo->addLog($jobPublicId, 'error', 'import_' . $type, 'Source item import failed.');
                }
            }
        }

        $counts = $this->repo->countItemsByStatus($jobId);
        $remaining = (int)($counts['pending'] ?? 0);

        // Parent linking pass once all issues are imported.
        if ($remaining === 0 && $processed < $maxItems && ($counts['imported'] ?? 0) > 0) {
            $this->linkParents($job, $actor);
        }

        $counts = $this->repo->countItemsByStatus($jobId);
        $done = ($mode === 'dry_run') || (($counts['pending'] ?? 0) === 0);

        return ['done' => $done, 'processed' => $processed, 'counts' => $counts];
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    private function importProject(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['id'] ?? '');
        $existing = $this->repo->findJobItem((int)$job['id'], 'project', $source);
        if ($existing && !empty($existing['target_public_id']) && $job['mode'] !== 'sync') {
            return ['target_type' => 'project', 'target_public_id' => $existing['target_public_id'], 'state' => 'skipped'];
        }

        $title = trim((string)($payload['name'] ?? 'Linear project')) ?: 'Linear project';
        if ($existing && !empty($existing['target_public_id'])) {
            $updated = $this->service('service.project')->update((string)$existing['target_public_id'], [
                'title' => $title,
                'description' => trim((string)($payload['description'] ?? '')),
            ], $actor);
            if (!is_array($updated)) {
                throw new RuntimeException('LINEAR_PROJECT_UPDATE_FAILED');
            }
            return ['target_type' => 'project', 'target_public_id' => (string)$existing['target_public_id'], 'state' => 'updated'];
        }

        $created = $this->service('service.project')->create([
            'title' => $title,
            'description' => trim((string)($payload['description'] ?? '')),
            'status' => 'active',
            'priority' => 'normal',
            'task_key_prefix' => 'LN' . strtoupper(substr(hash('sha256', $source), 0, 4)),
        ], $actor);

        if (!is_array($created) || empty($created['public_id'])) {
            throw new RuntimeException('LINEAR_PROJECT_CREATE_FAILED');
        }
        return ['target_type' => 'project', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported'];
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    private function importLabel(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['id'] ?? '');
        $existing = $this->repo->findJobItem((int)$job['id'], 'label', $source);
        if ($existing && !empty($existing['target_public_id'])) {
            return ['target_type' => 'tag', 'target_public_id' => $existing['target_public_id'], 'state' => 'skipped'];
        }

        $title = trim((string)($payload['name'] ?? 'Linear label')) ?: 'Linear label';
        $code = 'linear_' . substr(hash('sha256', (string)$job['connection_id'] . ':' . $source), 0, 24);
        $created = $this->service('service.tag')->create([
            'code' => $code,
            'title' => $title,
            'color' => $this->normalizeColor((string)($payload['color'] ?? '')),
            'description' => 'Imported from Linear label',
        ]);

        if ($created === 'TAG_CODE_EXISTS') {
            $list = $this->service('service.tag')->list(['search' => $code, 'limit' => 5]);
            $created = $list['items'][0] ?? null;
        }
        if (!is_array($created) || empty($created['public_id'])) {
            throw new RuntimeException('LINEAR_TAG_CREATE_FAILED');
        }
        return ['target_type' => 'tag', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported'];
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    private function importIssue(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['id'] ?? '');
        $existing = $this->repo->findJobItem((int)$job['id'], 'issue', $source);

        $projectId = (string)($payload['project']['id'] ?? '');
        $project = $this->repo->findJobItem((int)$job['id'], 'project', $projectId);
        if (!$project || empty($project['target_public_id'])) {
            throw new RuntimeException('LINEAR_ISSUE_PROJECT_NOT_READY');
        }

        $identifier = trim((string)($payload['identifier'] ?? ''));
        $title = trim((string)($payload['title'] ?? 'Linear issue')) ?: 'Linear issue';
        if ($identifier !== '') {
            $title = $identifier . ' — ' . $title;
        }

        $assignee = null;
        $assigneeData = $payload['assignee'] ?? null;
        if (is_array($assigneeData) && !empty($assigneeData['id'])) {
            $mappedPublic = $this->repo->mappedUserPublicId((int)$job['connection_id'], (string)$assigneeData['id']);
            if ($mappedPublic === null && !empty($assigneeData['email'])) {
                $mappedPublic = $this->findCrmUserPublicIdByEmail((string)$assigneeData['email']);
                if ($mappedPublic !== null) {
                    $this->repo->upsertUserMapping((int)$job['connection_id'], (string)$assigneeData['id'], [
                        'display_name' => (string)($assigneeData['name'] ?? ''),
                        'email' => (string)($assigneeData['email'] ?? ''),
                        'crm_user_public_id' => $mappedPublic,
                        'mapping_status' => 'auto',
                    ]);
                }
            }
            if ($mappedPublic !== null) {
                $assignee = $this->findCrmUserIdByPublicId($mappedPublic);
            }
        }

        $input = [
            'project_public_id' => (string)$project['target_public_id'],
            'title' => $title,
            'description' => trim((string)($payload['description'] ?? '')),
            'status' => $this->mapStatus((string)($payload['state']['type'] ?? '')),
            'priority' => $this->mapPriority((int)($payload['priority'] ?? 3)),
            'due_at' => $this->date($payload['dueDate'] ?? null),
            'assignee_user_id' => $assignee,
            'source_type' => 'linear',
            'source_id' => $source,
            'source_url' => $identifier !== '' ? 'https://linear.app/issue/' . $identifier : '',
            'source_payload_json' => $payload,
            'created_at' => $this->date($payload['createdAt'] ?? null) ?? gmdate('Y-m-d H:i:s'),
            'updated_at' => $this->date($payload['updatedAt'] ?? null),
        ];

        if ($existing && !empty($existing['target_public_id'])) {
            if (($job['mode'] ?? 'import') !== 'sync') {
                return ['target_type' => 'task', 'target_public_id' => $existing['target_public_id'], 'state' => 'skipped'];
            }
            $updated = $this->service('service.task')->update((string)$existing['target_public_id'], $input, (int)($actor['id'] ?? 0), $actor);
            if (!is_array($updated)) {
                throw new RuntimeException('LINEAR_TASK_UPDATE_FAILED');
            }
            return ['target_type' => 'task', 'target_public_id' => (string)$existing['target_public_id'], 'state' => 'updated'];
        }

        $created = $this->service('service.task')->create($input, $actor);
        if (!is_array($created) || empty($created['public_id'])) {
            throw new RuntimeException('LINEAR_TASK_CREATE_FAILED');
        }
        $target = (string)$created['public_id'];

        // Attach labels as tags
        foreach (($payload['labels']['nodes'] ?? []) as $label) {
            $labelId = (string)($label['id'] ?? '');
            $labelItem = $this->repo->findJobItem((int)$job['id'], 'label', $labelId);
            if ($labelItem && !empty($labelItem['target_public_id'])) {
                try {
                    $this->service('service.tag')->attachToTask($target, (string)$labelItem['target_public_id'], $actor);
                } catch (\Throwable $e) {
                    // non-fatal
                }
            }
        }

        return ['target_type' => 'task', 'target_public_id' => $target, 'state' => 'imported'];
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    private function importComment(array $job, array $payload, array $actor): array
    {
        $source = (string)($payload['id'] ?? '');
        $existing = $this->repo->findJobItem((int)$job['id'], 'comment', $source);
        if ($existing && !empty($existing['target_public_id'])) {
            return ['target_type' => 'comment', 'target_public_id' => $existing['target_public_id'], 'state' => 'skipped'];
        }

        // The comment's issue id is stored on the item as source_parent_id.
        $item = $this->repo->findJobItem((int)$job['id'], 'comment', $source);
        $issueId = (string)($item['source_parent_id'] ?? '');
        $issue = $this->repo->findJobItem((int)$job['id'], 'issue', $issueId);
        if (!$issue || empty($issue['target_public_id'])) {
            throw new RuntimeException('LINEAR_COMMENT_ISSUE_NOT_READY');
        }

        $body = trim((string)($payload['body'] ?? ''));
        if ($body === '') {
            return ['target_type' => 'comment', 'target_public_id' => '', 'state' => 'skipped'];
        }

        $authorName = (string)($payload['user']['name'] ?? 'Linear user');
        $html = '<p><strong>Linear:</strong> ' . htmlspecialchars($authorName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            . '<p>' . nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';

        $created = $this->service('service.comment')->createByTaskImported((string)$issue['target_public_id'], [
            'body' => $html,
            'created_at' => $this->date($payload['createdAt'] ?? null),
        ], (int)($actor['id'] ?? 0));

        if (!is_array($created) || empty($created['public_id'])) {
            throw new RuntimeException('LINEAR_COMMENT_CREATE_FAILED');
        }
        return ['target_type' => 'comment', 'target_public_id' => (string)$created['public_id'], 'state' => 'imported'];
    }

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $actor
     */
    private function linkParents(array $job, array $actor): void
    {
        $issues = $this->repo->listJobItems((string)$job['public_id'], 'imported', 'issue', 10000);
        foreach ($issues as $issue) {
            $parentId = (string)($issue['source_parent_id'] ?? '');
            if ($parentId === '' || empty($issue['target_public_id'])) {
                continue;
            }
            $parent = $this->repo->findJobItem((int)$job['id'], 'issue', $parentId);
            if (!$parent || empty($parent['target_public_id'])) {
                continue;
            }
            if ((string)$parent['target_public_id'] === (string)$issue['target_public_id']) {
                continue;
            }
            try {
                $this->service('service.task')->update((string)$issue['target_public_id'], [
                    'parent_task_public_id' => (string)$parent['target_public_id'],
                ], (int)($actor['id'] ?? 0), $actor);
            } catch (\Throwable $e) {
                error_log('[LinearImportService::linkParents] ' . $issue['source_id'] . ': ' . $e->getMessage());
            }
        }
    }

    // ── Helpers ──

    /**
     * @return array<int, string>
     */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), static fn(string $v): bool => $v !== ''));
        }
        $decoded = json_decode((string)$value, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $decoded), static fn(string $v): bool => $v !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function mapStatus(string $type): string
    {
        return match ($type) {
            'completed', 'canceled' => 'done',
            'started' => 'in_progress',
            'blocked' => 'blocked',
            default => 'new',
        };
    }

    private function mapPriority(int $priority): string
    {
        return match ($priority) {
            1 => 'urgent',
            2 => 'high',
            4 => 'low',
            default => 'normal',
        };
    }

    private function normalizeColor(string $color): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $color) ? strtolower($color) : '#64748b';
    }

    private function date(mixed $value): ?string
    {
        $v = trim((string)$value);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v)) {
            $n = (int)$v;
            if ($n > 100000000000) {
                $n = (int)floor($n / 1000);
            }
            return gmdate('Y-m-d H:i:s', $n);
        }
        $t = strtotime($v);
        return $t === false ? null : gmdate('Y-m-d H:i:s', $t);
    }

    private function findCrmUserPublicIdByEmail(string $email): ?string
    {
        if ($email === '') {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT public_id FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['email' => $email]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : null;
    }

    private function findCrmUserIdByPublicId(string $publicId): ?int
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }
}
