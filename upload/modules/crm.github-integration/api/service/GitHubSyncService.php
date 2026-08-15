<?php
declare(strict_types=1);

namespace Module\Crm\GithubIntegration\Service;

use Api\System\Library\Container;
use Module\Crm\GithubIntegration\Repository\GitHubRepository;
use PDO;
use RuntimeException;

/**
 * One-way sync: GitHub issues / pull requests -> TropaTT tasks.
 *
 * Idempotency is guaranteed by module_github_sync_items keyed on
 * (link_id, source_type, source_id). Re-running a sync updates the mapped task
 * instead of duplicating it.
 */
final class GitHubSyncService
{
    public function __construct(
        private readonly Container $container,
        private readonly GitHubRepository $repo,
        private readonly GitHubClient $client,
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
     * Sync one repo link (issues + PRs + their comments) up to $maxItems items.
     *
     * @param array<string, mixed> $link
     * @param string $token Decrypted GitHub token.
     * @param array<string, mixed> $actor
     * @return array<string, int> Counts of created/updated/skipped/failed items.
     */
    public function syncLink(array $link, string $token, array $actor, int $maxItems = 100, bool $syncComments = true): array
    {
        $linkId = (int)$link['id'];
        $baseUrl = $this->baseUrlForLink($link, $token);

        $issues = $this->client->listIssues($token, $baseUrl, (string)$link['owner'], (string)$link['repo']);

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'comments' => 0];
        $processed = 0;

        foreach ($issues as $issue) {
            if ($processed >= $maxItems) {
                break;
            }
            $processed++;
            try {
                $result = $this->syncIssue($link, $issue, $actor);
                $counts[$result['state']] = ($counts[$result['state']] ?? 0) + 1;

                if ($syncComments && !empty($result['target_public_id'])) {
                    $commentCount = $this->syncComments($link, $token, $baseUrl, (int)$issue['number'], (string)$result['target_public_id'], $actor);
                    $counts['comments'] += $commentCount;
                }
            } catch (\Throwable $e) {
                error_log('[GitHubSyncService::syncLink] ' . ($issue['number'] ?? '?') . ': ' . $e->getMessage());
                $this->repo->addLog($linkId, 'error', 'Sync failed for ' . ($issue['number'] ?? '?'));
                $counts['failed']++;
            }
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $link
     * @param array<string, mixed> $issue
     * @param array<string, mixed> $actor
     * @return array{state: string, target_public_id: string}
     */
    private function syncIssue(array $link, array $issue, array $actor): array
    {
        $linkId = (int)$link['id'];
        $isPr = is_array($issue['pull_request'] ?? null);
        $sourceType = $isPr ? 'pull_request' : 'issue';
        $number = (string)($issue['number'] ?? $issue['id'] ?? '');
        if ($number === '') {
            return ['state' => 'skipped', 'target_public_id' => ''];
        }
        $sourceId = $number;

        $existing = $this->repo->findSyncItem($linkId, $sourceType, $sourceId);
        $targetPublicId = $existing && !empty($existing['target_public_id']) ? (string)$existing['target_public_id'] : '';

        $title = trim((string)($issue['title'] ?? 'GitHub item')) ?: 'GitHub item';
        if ($isPr) {
            $title = '[PR] #' . $number . ' ' . $title;
        } else {
            $title = '#' . $number . ' ' . $title;
        }

        $input = [
            'project_public_id' => (string)$link['project_public_id'],
            'title' => mb_substr($title, 0, 500),
            'description' => $this->toHtml((string)($issue['body'] ?? '')),
            'status' => $this->mapStatus((string)($issue['state'] ?? 'open')),
            'priority' => $this->mapPriority($issue['labels'] ?? []),
            'assignee_user_id' => $this->resolveAssignee($issue['assignee'] ?? null),
            'source_type' => 'github',
            'source_id' => $sourceId,
            'source_url' => (string)($issue['html_url'] ?? ''),
            'source_payload_json' => $issue,
            'created_at' => $this->date($issue['created_at'] ?? null) ?? gmdate('Y-m-d H:i:s'),
            'updated_at' => $this->date($issue['updated_at'] ?? null),
        ];

        if ($targetPublicId !== '') {
            $updated = $this->service('service.task')->update($targetPublicId, $input, (int)($actor['id'] ?? 0), $actor);
            if (!is_array($updated)) {
                throw new RuntimeException('GITHUB_TASK_UPDATE_FAILED');
            }
            $this->syncLabels($linkId, $targetPublicId, $issue['labels'] ?? [], $actor);
            return ['state' => 'updated', 'target_public_id' => $targetPublicId];
        }

        $created = $this->service('service.task')->create($input, $actor);
        if (!is_array($created) || empty($created['public_id'])) {
            throw new RuntimeException('GITHUB_TASK_CREATE_FAILED');
        }
        $targetPublicId = (string)$created['public_id'];
        $this->syncLabels($linkId, $targetPublicId, $issue['labels'] ?? [], $actor);

        return ['state' => 'created', 'target_public_id' => $targetPublicId];
    }

    /**
     * Sync issue comments for a task. Returns the number of comments processed.
     */
    private function syncComments(array $link, string $token, string $baseUrl, int $number, string $taskPublicId, array $actor): int
    {
        $linkId = (int)$link['id'];
        $comments = $this->client->listIssueComments($token, $baseUrl, (string)$link['owner'], (string)$link['repo'], $number);
        $count = 0;

        foreach ($comments as $comment) {
            $commentId = (string)($comment['id'] ?? '');
            if ($commentId === '') {
                continue;
            }
            $existing = $this->repo->findSyncItem($linkId, 'comment', $commentId);
            if ($existing && !empty($existing['target_public_id'])) {
                continue;
            }

            $body = trim((string)($comment['body'] ?? ''));
            if ($body === '') {
                $this->repo->upsertSyncItem($linkId, 'comment', $commentId, [
                    'target_type' => 'comment',
                    'target_public_id' => null,
                    'status' => 'skipped',
                    'payload_json' => $comment,
                ]);
                continue;
            }

            $authorLogin = (string)($comment['user']['login'] ?? 'GitHub user');
            $html = '<p><strong>GitHub:</strong> ' . htmlspecialchars($authorLogin, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
                . $this->toHtml($body);

            $created = $this->service('service.comment')->createByTaskImported($taskPublicId, [
                'body' => $html,
                'created_at' => $this->date($comment['created_at'] ?? null),
            ], (int)($actor['id'] ?? 0));

            if (!is_array($created) || empty($created['public_id'])) {
                $this->repo->upsertSyncItem($linkId, 'comment', $commentId, [
                    'target_type' => 'comment',
                    'target_public_id' => null,
                    'status' => 'failed',
                    'payload_json' => $comment,
                ]);
                continue;
            }

            $this->repo->upsertSyncItem($linkId, 'comment', $commentId, [
                'target_type' => 'comment',
                'target_public_id' => (string)$created['public_id'],
                'status' => 'imported',
                'payload_json' => $comment,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Attach GitHub labels as tags on a task.
     *
     * @param array<int, array<string, mixed>> $labels
     * @param array<string, mixed> $actor
     */
    private function syncLabels(int $linkId, string $taskPublicId, array $labels, array $actor): void
    {
        foreach ($labels as $label) {
            $name = trim((string)($label['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            try {
                $tagPublicId = $this->ensureTag($linkId, $name, (string)($label['color'] ?? ''));
                if ($tagPublicId !== null) {
                    $this->service('service.tag')->attachToTask($taskPublicId, $tagPublicId, $actor);
                }
            } catch (\Throwable $e) {
                // non-fatal: label attachment should never fail the whole sync
            }
        }
    }

    private function ensureTag(int $linkId, string $name, string $color): ?string
    {
        $code = 'gh_' . substr(hash('sha256', $linkId . ':' . strtolower($name)), 0, 24);
        $created = $this->service('service.tag')->create([
            'code' => $code,
            'title' => $name,
            'color' => $this->normalizeColor($color),
            'description' => 'Imported from GitHub label',
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

    private function baseUrlForLink(array $link, string $token): string
    {
        return rtrim((string)($link['base_url'] ?? 'https://api.github.com'), '/');
    }

    private function mapStatus(string $state): string
    {
        return strtolower($state) === 'closed' ? 'done' : 'new';
    }

    /**
     * @param array<int, array<string, mixed>> $labels
     */
    private function mapPriority(array $labels): string
    {
        foreach ($labels as $label) {
            $name = strtolower((string)($label['name'] ?? ''));
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
        $login = trim((string)($assignee['login'] ?? ''));
        if ($login === '') {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT id FROM users WHERE login = :login AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['login' => $login]);
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

    private function normalizeColor(string $color): string
    {
        $color = ltrim(trim($color), '#');
        return preg_match('/^[0-9a-f]{6}$/i', $color) ? '#' . strtolower($color) : '#64748b';
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
