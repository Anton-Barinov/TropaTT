<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use PDO;
use RuntimeException;
use Throwable;

/** Idempotent, fenced work journal backed by native task comments. */
final class AgentJournalService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(int $organizationId, string $taskPublicId, string $eventId): ?array
    {
        $this->validateIdentity($organizationId, $taskPublicId, $eventId);
        $statement = $this->pdo->prepare('SELECT event_id, operation_id, event_kind, stage, agent_id, run_id, generation, comment_public_id, payload_hash, created_at FROM agent_journal WHERE organization_id = :organization AND task_public_id = :task AND event_id = :event');
        $statement->execute(['organization' => $organizationId, 'task' => $taskPublicId, 'event' => $eventId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['generation'] = (int)$row['generation'];
        }
        return $row ?: null;
    }

    /** The callback must use the same PDO, return the native comment and never
     * perform network I/O. Its database effects commit with the event record.
     */
    public function append(int $organizationId, int $actorId, string $taskPublicId, array $input, callable $createComment): array
    {
        $eventId = (string)($input['event_id'] ?? '');
        $this->validateIdentity($organizationId, $taskPublicId, $eventId);
        foreach (['agent_id', 'run_id', 'operation_id'] as $key) {
            if (!preg_match('/\A[A-Za-z0-9._-]{1,96}\z/D', (string)($input[$key] ?? ''))) {
                throw new RuntimeException('JOURNAL_INVALID_ARGUMENT');
            }
        }
        $token = (string)($input['token'] ?? '');
        $generation = (int)($input['generation'] ?? 0);
        $body = (string)($input['body'] ?? '');
        $stage = (string)($input['stage'] ?? '');
        $kind = (string)($input['event_kind'] ?? '');
        if ($actorId < 1 || $generation < 1 || !preg_match('/\A[a-f0-9]{64}\z/D', $token)
            || !preg_match('/\A[a-z][a-z0-9_]{0,31}\z/D', $stage)
            || !in_array($kind, ['intent', 'result', 'checkpoint', 'blocker'], true)
            || trim($body) === '' || mb_strlen($body) > 6000
            || str_contains($body, $token) || preg_match('/(?:\bapk_[A-Za-z0-9]+|\bBearer\s+\S+)/i', $body)) {
            throw new RuntimeException('JOURNAL_INVALID_ARGUMENT');
        }
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $this->pdo->inTransaction()) {
            throw new RuntimeException('JOURNAL_DATABASE_CONTEXT_INVALID');
        }
        $hash = hash('sha256', json_encode([$input['operation_id'], $kind, $stage, $body], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS));
        $header = "### Pipeline · {$kind} · {$stage}\n"
            . "event_id={$eventId}; operation_id={$input['operation_id']}; run_id={$input['run_id']}; generation={$generation}\n\n";
        if (str_contains($header, $token) || preg_match('/(?:\bapk_[A-Za-z0-9]+|\bBearer\s+\S+)/i', $header)) {
            throw new RuntimeException('JOURNAL_INVALID_ARGUMENT');
        }
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT owner_user_id, agent_id, run_id, token_hash, generation, expires_at, UNIX_TIMESTAMP() AS server_now FROM agent_leases WHERE organization_id = :organization AND resource_key = :resource FOR UPDATE');
            $statement->execute(['organization' => $organizationId, 'resource' => 'task:' . $taskPublicId]);
            $lease = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$lease || (int)$lease['owner_user_id'] !== $actorId || $lease['agent_id'] !== $input['agent_id']
                || $lease['run_id'] !== $input['run_id'] || (int)$lease['generation'] !== $generation
                || (int)$lease['expires_at'] <= (int)$lease['server_now']
                || !hash_equals((string)$lease['token_hash'], hash('sha256', $token))) {
                throw new RuntimeException('LEASE_OWNERSHIP_LOST');
            }
            // Re-check assignment and workspace inside the same transaction.
            $statement = $this->pdo->prepare('SELECT id FROM tasks WHERE public_id = :task AND organization_id = :organization AND assignee_user_id = :actor AND deleted_at IS NULL FOR UPDATE');
            $statement->execute(['task' => $taskPublicId, 'organization' => $organizationId, 'actor' => $actorId]);
            if ($statement->fetchColumn() === false) {
                throw new RuntimeException('JOURNAL_ACCESS_DENIED');
            }
            $existing = $this->get($organizationId, $taskPublicId, $eventId);
            if ($existing !== null) {
                if (!hash_equals((string)$existing['payload_hash'], $hash)
                    || $existing['agent_id'] !== $input['agent_id'] || $existing['run_id'] !== $input['run_id']
                    || (int)$existing['generation'] !== $generation) {
                    throw new RuntimeException('JOURNAL_EVENT_CONFLICT');
                }
                $this->pdo->commit();
                return ['event' => $existing, 'replayed' => true];
            }
            $comment = $createComment($header . $body);
            $commentId = is_array($comment) ? (string)($comment['public_id'] ?? '') : '';
            if (!preg_match('/\Acmt_[A-Za-z0-9]+\z/D', $commentId)) {
                throw new RuntimeException('JOURNAL_COMMENT_NOT_CREATED');
            }
            $statement = $this->pdo->prepare('INSERT INTO agent_journal (organization_id, task_public_id, event_id, operation_id, event_kind, stage, owner_user_id, agent_id, run_id, generation, comment_public_id, payload_hash, created_at) VALUES (:organization, :task, :event, :operation, :kind, :stage, :actor, :agent, :run, :generation, :comment, :hash, UTC_TIMESTAMP())');
            $statement->execute(['organization' => $organizationId, 'task' => $taskPublicId, 'event' => $eventId,
                'operation' => $input['operation_id'], 'kind' => $kind, 'stage' => $stage, 'actor' => $actorId,
                'agent' => $input['agent_id'], 'run' => $input['run_id'], 'generation' => $generation,
                'comment' => $commentId, 'hash' => $hash]);
            $event = $this->get($organizationId, $taskPublicId, $eventId);
            $this->pdo->commit();
            return ['event' => $event, 'replayed' => false];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function validateIdentity(int $organizationId, string $taskPublicId, string $eventId): void
    {
        if ($organizationId < 1 || !preg_match('/\Atsk_[A-Za-z0-9]{1,60}\z/D', $taskPublicId)
            || !preg_match('/\A[a-f0-9]{32}\z/D', $eventId)) {
            throw new RuntimeException('JOURNAL_INVALID_ARGUMENT');
        }
    }
}
