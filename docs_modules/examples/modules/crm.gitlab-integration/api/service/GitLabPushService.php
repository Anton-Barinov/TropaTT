<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration\Service;

use Api\System\Library\Container;
use Module\Crm\GitlabIntegration\Repository\GitLabRepository;
use PDO;

/**
 * Event-driven push-back: mirror CRM changes back to the linked GitLab merge
 * request when the changed task was imported by this module.
 *
 * Loop safety is structural: the poll sync writes through core services (no
 * events), while this service only reacts to events dispatched from core
 * controllers on real user actions. Imported notes use a dedicated path and are
 * therefore never re-pushed, and every pushed note is persisted in the
 * sync-items table so the next pull skips it.
 */
final class GitLabPushService
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly Container $container,
        private readonly GitLabRepository $repo,
        private readonly GitLabClient $client,
        private readonly array $config = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onCommentAdded(array $context): void
    {
        if (!$this->enabled('push_back_comments', true)) {
            return;
        }

        $taskPublicId = (string)($context['task_public_id'] ?? '');
        $commentPublicId = (string)($context['comment_public_id'] ?? '');
        if ($taskPublicId === '' || $commentPublicId === '') {
            return;
        }

        $resolved = $this->resolveTask($taskPublicId);
        if ($resolved === null) {
            return;
        }

        $body = $this->commentBody($commentPublicId);
        if ($body === '') {
            return;
        }

        $mapping = $resolved['mapping'];
        $remote = $this->client->createMergeRequestNote(
            $resolved['token'],
            (string)($resolved['connection']['base_url'] ?? 'https://gitlab.com/api/v4'),
            (string)($mapping['project_path'] ?? ''),
            (int)($mapping['source_id'] ?? 0),
            $body
        );
        $remoteId = (string)($remote['id'] ?? '');
        if ($remoteId === '') {
            return;
        }

        // Persist the remote note mapping so the next pull sync skips it.
        $this->repo->upsertSyncItem((int)($mapping['id'] ?? 0), 'comment', $remoteId, [
            'target_type' => 'comment',
            'target_public_id' => $commentPublicId,
            'status' => 'pushed',
            'payload_json' => $remote,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onStatusChanged(array $context): void
    {
        if (!$this->enabled('push_back_status', true)) {
            return;
        }

        $taskPublicId = (string)($context['task_public_id'] ?? '');
        $newStatus = (string)($context['new_status'] ?? '');
        if ($taskPublicId === '' || $newStatus === '') {
            return;
        }

        $resolved = $this->resolveTask($taskPublicId);
        if ($resolved === null) {
            return;
        }

        $mapping = $resolved['mapping'];
        $stateEvent = $newStatus === 'done' ? 'close' : 'reopen';
        $this->client->updateMergeRequestState(
            $resolved['token'],
            (string)($resolved['connection']['base_url'] ?? 'https://gitlab.com/api/v4'),
            (string)($mapping['project_path'] ?? ''),
            (int)($mapping['source_id'] ?? 0),
            $stateEvent
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function enabled(string $flag, bool $default): bool
    {
        $value = $this->config[$flag] ?? null;
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveTask(string $taskPublicId): ?array
    {
        $mapping = $this->repo->findTaskMapping($taskPublicId);
        if ($mapping === null) {
            return null;
        }
        $connection = $this->repo->getConnectionById((int)($mapping['connection_id'] ?? 0));
        if ($connection === null) {
            return null;
        }
        $token = EncryptionService::decrypt((string)($connection['token_encrypted'] ?? ''));
        if ($token === null || $token === '') {
            return null;
        }

        return [
            'mapping' => $mapping,
            'connection' => $connection,
            'token' => $token,
        ];
    }

    private function commentBody(string $commentPublicId): string
    {
        try {
            $stmt = $this->pdo()->prepare('SELECT body FROM comments WHERE public_id = :public_id LIMIT 1');
            $stmt->execute(['public_id' => $commentPublicId]);
            $body = $stmt->fetchColumn();
            return is_string($body) ? trim($body) : '';
        } catch (\Throwable $e) {
            error_log('[GitLabPushService::commentBody] ' . $e->getMessage());
            return '';
        }
    }

    private function pdo(): PDO
    {
        return $this->container->get('db.pdo');
    }
}
