<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use PDO;
use RuntimeException;
use Throwable;

/** Atomic coordination primitives. Callers must authorize the resource first.
 * Tokens are provided by the client before claim so a lost response can be
 * reconciled without revealing another run's capability. Only hashes persist.
 */
final class AgentLeaseService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function execute(string $action, int $organizationId, int $actorId, string $resource, string $agentId, string $runId, string $token, int $generation = 0, int $ttl = 300): array
    {
        if (!in_array($action, ['claim', 'renew', 'release', 'status'], true)
            || $organizationId < 1 || $actorId < 1 || strlen($resource) > 96
            || !preg_match('/\A(?:task:tsk_[A-Za-z0-9]+|release:[a-z0-9][a-z0-9._-]{0,63})\z/D', $resource)) {
            throw new RuntimeException('LEASE_INVALID_ARGUMENT');
        }
        if ($action !== 'status' && (!preg_match('/\A[A-Za-z0-9._-]{1,96}\z/D', $agentId)
            || !preg_match('/\A[A-Za-z0-9._-]{1,96}\z/D', $runId)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $token)
            || $ttl < 30 || $ttl > 900
            || (in_array($action, ['renew', 'release'], true) && $generation < 1))) {
            throw new RuntimeException('LEASE_INVALID_ARGUMENT');
        }
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $this->pdo->inTransaction()) {
            throw new RuntimeException('LEASE_DATABASE_CONTEXT_INVALID');
        }
        if ($action === 'status') {
            $statement = $this->pdo->prepare('SELECT resource_key, owner_user_id, agent_id, run_id, generation, expires_at, heartbeat_at, UNIX_TIMESTAMP() AS server_now FROM agent_leases WHERE organization_id = :organization AND resource_key = :resource');
            $statement->execute(['organization' => $organizationId, 'resource' => $resource]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return ['lease' => $row ? $this->publicLease($row) : null];
        }
        $hash = hash('sha256', $token);
        $this->pdo->beginTransaction();
        try {
            // The unique resource index serializes first claim too, including
            // concurrent inserts of a previously unseen resource.
            $statement = $this->pdo->prepare('INSERT INTO agent_leases (organization_id, resource_key, owner_user_id, agent_id, run_id, token_hash, generation, expires_at, heartbeat_at) VALUES (:organization, :resource, 0, \'\', \'\', \'\', 0, 0, 0) ON DUPLICATE KEY UPDATE id = id');
            $statement->execute(['organization' => $organizationId, 'resource' => $resource]);
            $statement = $this->pdo->prepare('SELECT resource_key, owner_user_id, agent_id, run_id, token_hash, generation, expires_at, heartbeat_at, UNIX_TIMESTAMP() AS server_now FROM agent_leases WHERE organization_id = :organization AND resource_key = :resource FOR UPDATE');
            $statement->execute(['organization' => $organizationId, 'resource' => $resource]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('LEASE_ROW_MISSING');
            }
            $now = (int)$row['server_now'];
            $active = (int)$row['expires_at'] > $now;
            $sameOwner = (int)$row['owner_user_id'] === $actorId && $row['agent_id'] === $agentId
                && $row['run_id'] === $runId && hash_equals((string)$row['token_hash'], $hash);
            if ($action === 'claim') {
                if ($active) {
                    if (!$sameOwner) {
                        throw new RuntimeException('LEASE_OCCUPIED');
                    }
                    // Idempotent recovery of a confirmed active claim; never
                    // renew here or resurrect a stale claim accidentally.
                    $this->pdo->commit();
                    return ['lease' => $this->publicLease($row), 'replayed' => true];
                }
                $seen = $this->pdo->prepare('SELECT generation FROM agent_lease_runs WHERE organization_id = :organization AND resource_key = :resource AND owner_user_id = :actor AND agent_id = :agent AND run_id = :run');
                $seen->execute(['organization' => $organizationId, 'resource' => $resource,
                    'actor' => $actorId, 'agent' => $agentId, 'run' => $runId]);
                if ($seen->fetchColumn() !== false) {
                    throw new RuntimeException('LEASE_RUN_EXPIRED');
                }
                $nextGeneration = (int)$row['generation'] + 1;
                $expiry = $now + $ttl;
            } else {
                if (!$active || !$sameOwner || (int)$row['generation'] !== $generation) {
                    throw new RuntimeException('LEASE_OWNERSHIP_LOST');
                }
                $nextGeneration = $generation;
                $expiry = $action === 'release' ? $now : $now + $ttl;
            }
            $statement = $this->pdo->prepare('UPDATE agent_leases SET owner_user_id = :actor, agent_id = :agent, run_id = :run, token_hash = :token, generation = :generation, expires_at = :expires, heartbeat_at = :heartbeat WHERE organization_id = :organization AND resource_key = :resource');
            $statement->execute(['actor' => $actorId, 'agent' => $agentId, 'run' => $runId, 'token' => $hash,
                'generation' => $nextGeneration, 'expires' => $expiry, 'heartbeat' => $now,
                'organization' => $organizationId, 'resource' => $resource]);
            if ($action === 'claim') {
                $statement = $this->pdo->prepare('INSERT INTO agent_lease_runs (organization_id, resource_key, owner_user_id, agent_id, run_id, generation, claimed_at) VALUES (:organization, :resource, :actor, :agent, :run, :generation, :claimed)');
                $statement->execute(['organization' => $organizationId, 'resource' => $resource,
                    'actor' => $actorId, 'agent' => $agentId, 'run' => $runId,
                    'generation' => $nextGeneration, 'claimed' => $now]);
            }
            $row = array_merge($row, ['owner_user_id' => $actorId, 'agent_id' => $agentId, 'run_id' => $runId,
                'generation' => $nextGeneration, 'expires_at' => $expiry, 'heartbeat_at' => $now]);
            $this->pdo->commit();
            return ['lease' => $this->publicLease($row), 'replayed' => false];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function publicLease(array $row): array
    {
        return ['resource' => (string)$row['resource_key'], 'owner_user_id' => (int)$row['owner_user_id'],
            'agent_id' => (string)$row['agent_id'], 'run_id' => (string)$row['run_id'],
            'generation' => (int)$row['generation'], 'expires_at' => (int)$row['expires_at'],
            'heartbeat_at' => (int)$row['heartbeat_at'], 'server_now' => (int)$row['server_now'],
            'active' => (int)$row['expires_at'] > (int)$row['server_now']];
    }
}
