<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration\Service;

use Api\System\Library\Container;
use Module\Crm\GitlabIntegration\Repository\GitLabRepository;
use PDO;
use RuntimeException;

/**
 * One-way sync: GitLab merge requests -> TropaTT tasks.
 *
 * Idempotency is guaranteed by module_gitlab_sync_items keyed on
 * (link_id, source_type, source_id). Re-running a sync updates the mapped task.
 */
final class GitLabSyncService
{
    public function __construct(
        private readonly Container $container,
        private readonly GitLabRepository $repo,
        private readonly GitLabClient $client,
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
     * Sync one project link (merge requests + notes) up to $maxItems items.
     *
     * @param array<string, mixed> $link
     * @param string $token Decrypted GitLab token.
     * @param array<string, mixed> $actor
     * @return array<string, int>
     */
    public function syncLink(array $link, string $token, array $actor, int $maxItems = 100, bool $syncComments = true): array
    {
        $linkId = (int)$link['id'];
        $baseUrl = rtrim((string)($link['base_url'] ?? 'https://gitlab.com/api/v4'), '/');

        $mrs = $this->client->listMergeRequests($token, $baseUrl, (string)$link['project_path']);

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'comments' => 0];
        $processed = 0;

        foreach ($mrs as $mr) {
            if ($processed >= $maxItems) {
                break;
            }
            $processed++;
            try {
                $result = $this->syncMergeRequest($link, $mr, $actor);
                $counts[$result['state']] = ($counts[$result['state']] ?? 0) + 1;

                if ($syncComments && !empty($result['target_public_id'])) {
                    $commentCount = $this->syncNotes($link, $token, $baseUrl, (int)($mr['iid'] ?? 0), (string)$result['target_public_id'], $actor);
                    $counts['comments'] += $commentCount;
                }
            } catch (\Throwable $e) {
                error_log('[GitLabSyncService::syncLink] ' . ($mr['iid'] ?? '?') . ': ' . $e->getMessage());
                $this->repo->addLog($linkId, 'error', 'Sync failed for MR ' . ($mr['iid'] ?? '?'));
                $counts['failed']++;
            }
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $link
     * @param array<string, mixed> $mr
     * @param array<string, mixed> $actor
     * @return array{state: string, target_public_id: string}
     */
    private function syncMergeRequest(array $link, array $mr, array $actor): array
    {
        $linkId = (int)$link['id'];
        $iid = (string)($mr['iid'] ?? $mr['id'] ?? '');
        if ($iid === '') {
            return ['state' => 'skipped', 'target_public_id' => ''];
        }

        $existing = $this->repo->findSyncItem($linkId, 'merge_request', $iid);
        $targetPublicId = $existing && !empty($existing['target_public_id']) ? (string)$existing['target_public_id'] : '';

        $title = '!' . $iid . ' ' . (trim((string)($mr['title'] ?? 'Merge Request')) ?: 'Merge Request');

        $input = [
            'project_public_id' => (string)$link['project_public_id'],
            'title' => mb_substr($title, 0, 500),
            'description' => $this->toHtml((string)($mr['description'] ?? '')),
            'status' => $this->mapStatus((string)($mr['state'] ?? 'opened')),
            'priority' => $this->mapPriority($mr['labels'] ?? []),
            'assignee_user_id' => $this->resolveAssignee($mr['assignee'] ?? null),
            'source_type' => 'gitlab',
            'source_id' => $iid,
            'source_url' => (string)($mr['web_url'] ?? ''),
            'source_payload_json' => $mr,
            'created_at' => $this->date($mr['created_at'] ?? null) ?? gmdate('Y-m-d H:i:s'),
            'updated_at' => $this->date($mr['updated_at'] ?? null),
        ];

        if ($targetPublicId !== '') {
            $updated = $this->service('service.task')->update($targetPublicId, $input, (int)($actor['id'] ?? 0), $actor);
            if (!is_array($updated)) {
                throw new RuntimeException('GITLAB_TASK_UPDATE_FAILED');
            }
            $this->syncLabels($linkId, $targetPublicId, $mr['labels'] ?? [], $actor);
            $this->repo->upsertSyncItem($linkId, 'merge_request', $iid, [
                'target_type' => 'task',
                'target_public_id' => $targetPublicId,
                'status' => 'imported',
                'payload_json' => $mr,
            ]);
            return ['state' => 'updated', 'target_public_id' => $targetPublicId];
        }

        $created = $this->service('service.task')->create($input, $actor);
        if (!is_array($created) || empty($created['public_id'])) {
            throw new RuntimeException('GITLAB_TASK_CREATE_FAILED');
        }
        $targetPublicId = (string)$created['public_id'];
        $this->syncLabels($linkId, $targetPublicId, $mr['labels'] ?? [], $actor);
        $this->repo->upsertSyncItem($linkId, 'merge_request', $iid, [
            'target_type' => 'task',
            'target_public_id' => $targetPublicId,
            'status' => 'imported',
            'payload_json' => $mr,
        ]);

        return ['state' => 'created', 'target_public_id' => $targetPublicId];
    }

    /**
     * Sync MR notes as task comments. Returns the number of notes processed.
     */
    private function syncNotes(array $link, string $token, string $baseUrl, int $iid, string $taskPublicId, array $actor): int
    {
        $linkId = (int)$link['id'];
        $notes = $this->client->listMergeRequestNotes($token, $baseUrl, (string)$link['project_path'], $iid);
        $count = 0;

        foreach ($notes as $note) {
            $noteId = (string)($note['id'] ?? '');
            if ($noteId === '') {
                continue;
            }
            // Skip system notes (e.g. "merged", "closed") — only real discussions.
            if ((bool)($note['system'] ?? false)) {
                continue;
            }
            $existing = $this->repo->findSyncItem($linkId, 'comment', $noteId);
            if ($existing && !empty($existing['target_public_id'])) {
                continue;
            }

            $body = trim((string)($note['body'] ?? ''));
            if ($body === '') {
                $this->repo->upsertSyncItem($linkId, 'comment', $noteId, [
                    'target_type' => 'comment',
                    'target_public_id' => null,
                    'status' => 'skipped',
                    'payload_json' => $note,
                ]);
                continue;
            }

            $authorName = (string)($note['author']['username'] ?? 'GitLab user');
            $html = '<p><strong>GitLab:</strong> ' . htmlspecialchars($authorName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
                . $this->toHtml($body);

            $created = $this->service('service.comment')->createByTaskImported($taskPublicId, [
                'body' => $html,
                'created_at' => $this->date($note['created_at'] ?? null),
            ], (int)($actor['id'] ?? 0));

            if (!is_array($created) || empty($created['public_id'])) {
                $this->repo->upsertSyncItem($linkId, 'comment', $noteId, [
                    'target_type' => 'comment',
                    'target_public_id' => null,
                    'status' => 'failed',
                    'payload_json' => $note,
                ]);
                continue;
            }

            $this->repo->upsertSyncItem($linkId, 'comment', $noteId, [
                'target_type' => 'comment',
                'target_public_id' => (string)$created['public_id'],
                'status' => 'imported',
                'payload_json' => $note,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @param array<int, string> $labels
     * @param array<string, mixed> $actor
     */
    private function syncLabels(int $linkId, string $taskPublicId, array $labels, array $actor): void
    {
        foreach ($labels as $label) {
            $name = trim((string)$label);
            if ($name === '') {
                continue;
            }
            try {
                $tagPublicId = $this->ensureTag($linkId, $name);
                if ($tagPublicId !== null) {
                    $this->service('service.tag')->attachToTask($taskPublicId, $tagPublicId, $actor);
                }
            } catch (\Throwable $e) {
                // non-fatal
            }
        }
    }

    private function ensureTag(int $linkId, string $name): ?string
    {
        $code = 'gl_' . substr(hash('sha256', $linkId . ':' . strtolower($name)), 0, 24);
        $created = $this->service('service.tag')->create([
            'code' => $code,
            'title' => $name,
            'color' => '#64748b',
            'description' => 'Imported from GitLab label',
        ]);
        if ($created === 'TAG_CODE_EXISTS') {
            $list = $this->service('service.tag')->list(['search' => $code, 'limit' => 5]);
            $created = $list['items'][0] ?? null;
        }
        if (!is_array($created) || empty($created['public_id'])) {
            return null;
        }
        return (string)$created['public_id'];
    }

    private function mapStatus(string $state): string
    {
        return match (strtolower($state)) {
            'merged', 'closed' => 'done',
            default => 'new',
        };
    }

    /**
     * @param array<int, string> $labels
     */
    private function mapPriority(array $labels): string
    {
        foreach ($labels as $label) {
            $name = strtolower((string)$label);
            if (str_contains($name, 'urgent') || str_contains($name, 'critical') || str_contains($name, 'p0')) {
                return 'urgent';
            }
            if (str_contains($name, 'high') || str_contains($name, 'p1')) {
                return 'high';
            }
            if (str_contains($name, 'low') || str_contains($name, 'p3')) {
                return 'low';
            }
        }
        return 'normal';
    }

    private function resolveAssignee(mixed $assignee): ?int
    {
        if (!is_array($assignee)) {
            return null;
        }
        $username = trim((string)($assignee['username'] ?? ''));
        if ($username === '') {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT id FROM users WHERE login = :login AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['login' => $username]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }

    private function toHtml(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    private function date(mixed $value): ?string
    {
        $v = trim((string)$value);
        if ($v === '') {
            return null;
        }
        $t = strtotime($v);
        return $t === false ? null : gmdate('Y-m-d H:i:s', $t);
    }
}
