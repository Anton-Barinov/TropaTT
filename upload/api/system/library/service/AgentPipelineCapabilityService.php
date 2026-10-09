<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use JsonException;
use RuntimeException;

/**
 * Read-only, fail-closed acceptance status for the local agent pipeline.
 * Evidence is trusted only from protected owner-controlled runtime storage and
 * only when each accepted source SHA exactly matches the installed core SHA;
 * an evidence digest is integrity metadata, not an authentication mechanism.
 */
final class AgentPipelineCapabilityService
{
    private const MAX_RECORD_BYTES = 16384;
    private const RECORD_KEYS = ['schema_version', 'task_write_fencing', 'parallel_task_writes', 'release_coordinator', 'journal_fencing', 'swarm_coordination'];

    /**
     * Read the owner-managed runtime evidence file without following links or
     * accepting a file that changes while it is being read.
     *
     * @return array<string,mixed>
     */
    public function readRuntimeRecord(string $path): array
    {
        clearstatcache(true, $path);
        $directory = dirname($path);
        $directoryStat = @lstat($directory);
        if (!function_exists('posix_geteuid')) {
            throw new RuntimeException('CAPABILITY_RECORD_UNAVAILABLE');
        }
        $effectiveUid = posix_geteuid();
        if (!is_int($effectiveUid) || is_link($directory) || !is_array($directoryStat)
            || (($directoryStat['mode'] & 0170000) !== 0040000)
            || (int)($directoryStat['uid'] ?? -1) !== $effectiveUid
            || (($directoryStat['mode'] & 0500) !== 0500) || (($directoryStat['mode'] & 0022) !== 0)) {
            throw new RuntimeException('CAPABILITY_RECORD_UNAVAILABLE');
        }
        $before = @lstat($path);
        if (!is_array($before) || (($before['mode'] & 0170000) !== 0100000)
            || (int)($before['uid'] ?? -1) !== $effectiveUid
            || (($before['mode'] & 0400) !== 0400)
            || (int)($before['nlink'] ?? 0) !== 1 || (int)($before['size'] ?? 0) < 2
            || (int)$before['size'] > self::MAX_RECORD_BYTES || (($before['mode'] & 0077) !== 0)) {
            throw new RuntimeException('CAPABILITY_RECORD_UNAVAILABLE');
        }

        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) {
            throw new RuntimeException('CAPABILITY_RECORD_UNAVAILABLE');
        }
        try {
            $opened = fstat($handle);
            if (!is_array($opened) || (($opened['mode'] & 0170000) !== 0100000)
                || (int)$opened['nlink'] !== 1
                || (int)($opened['uid'] ?? -1) !== $effectiveUid
                || (int)$opened['dev'] !== (int)$before['dev']
                || (int)$opened['ino'] !== (int)$before['ino']
                || (int)$opened['size'] !== (int)$before['size']
                || (($opened['mode'] & 0077) !== 0)) {
                throw new RuntimeException('CAPABILITY_RECORD_UNAVAILABLE');
            }
            $raw = stream_get_contents($handle, self::MAX_RECORD_BYTES + 1);
            $after = fstat($handle);
            if (!is_string($raw) || strlen($raw) !== (int)$opened['size']
                || strlen($raw) > self::MAX_RECORD_BYTES || !is_array($after)
                || (($after['mode'] & 0170000) !== 0100000)
                || (int)($after['uid'] ?? -1) !== $effectiveUid
                || (($after['mode'] & 0077) !== 0)
                || (int)$after['dev'] !== (int)$opened['dev']
                || (int)$after['ino'] !== (int)$opened['ino']
                || (int)$after['size'] !== (int)$opened['size']) {
                throw new RuntimeException('CAPABILITY_RECORD_UNAVAILABLE');
            }
        } finally {
            fclose($handle);
        }

        try {
            $record = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('CAPABILITY_RECORD_INVALID');
        }
        if (!is_array($record) || array_is_list($record)) {
            throw new RuntimeException('CAPABILITY_RECORD_INVALID');
        }
        return $record;
    }

    /** @param array<string,mixed> $record @return array<string,mixed> */
    public function status(array $record, ?string $runtimeSha): array
    {
        $runtimeSha = is_string($runtimeSha) && preg_match('/\A[a-f0-9]{40}\z/D', $runtimeSha) === 1
            ? $runtimeSha : null;
        $validRecord = array_diff(array_keys($record), self::RECORD_KEYS) === []
            && array_key_exists('schema_version', $record)
            && is_int($record['schema_version']) && $record['schema_version'] === 1;
        $reason = !$validRecord ? 'CAPABILITY_RECORD_INVALID'
            : ($runtimeSha === null ? 'RUNTIME_SOURCE_UNAVAILABLE' : null);

        $task = $validRecord && $runtimeSha !== null
            ? $this->verify($record['task_write_fencing'] ?? null, $runtimeSha) : null;
        $parallel = $validRecord && $runtimeSha !== null
            ? $this->verify($record['parallel_task_writes'] ?? null, $runtimeSha) : null;
        $release = $validRecord && $runtimeSha !== null
            ? $this->verify($record['release_coordinator'] ?? null, $runtimeSha, true) : null;
        $journal = $validRecord && $runtimeSha !== null
            ? $this->verify($record['journal_fencing'] ?? null, $runtimeSha) : null;
        $swarm = $validRecord && $runtimeSha !== null
            ? $this->verify($record['swarm_coordination'] ?? null, $runtimeSha) : null;

        $taskVerified = $task !== null;
        $parallelVerified = $parallel !== null;
        $releaseVerified = $release !== null;
        $journalVerified = $journal !== null;
        $swarmVerified = $swarm !== null;
        $rolloutAccepted = $taskVerified && $parallelVerified && $releaseVerified;
        if ($reason === null && !$rolloutAccepted) {
            $reason = 'CAPABILITY_NOT_ACCEPTED';
        }
        return [
            'schema_version' => 1,
            'task_write_fencing' => $taskVerified ? 'verified' : 'not_verified',
            'task_fencing_source_matches_runtime' => $taskVerified,
            'task_fencing_evidence_sha256' => $task['evidence_sha256'] ?? null,
            'parallel_task_writes_allowed' => $taskVerified && $parallelVerified,
            'parallel_write_evidence_sha256' => ($taskVerified && $parallelVerified) ? $parallel['evidence_sha256'] : null,
            'release_source_matches_runtime' => $releaseVerified,
            'release_coordinator_accepted' => $releaseVerified,
            'release_coordinator_protocol' => $release['protocol'] ?? null,
            'release_evidence_sha256' => $release['evidence_sha256'] ?? null,
            'swarm_coordination_accepted' => $swarmVerified,
            'swarm_coordination_source_matches_runtime' => $swarmVerified,
            'swarm_coordination_evidence_sha256' => $swarm['evidence_sha256'] ?? null,
            'journal_fencing' => [
                'status' => $journalVerified ? 'verified' : 'not_verified',
                'source_matches_runtime' => $journalVerified,
                'evidence_sha256' => $journal['evidence_sha256'] ?? null,
            ],
            'rollout_gate' => $rolloutAccepted ? 'accepted' : 'blocked',
            'status_reason' => $reason,
        ];
    }

    /**
     * Swarm reads and writes share the exact-build acceptance record. This
     * capability governs coordination metadata only; it does not enable
     * ordinary task writes or parallel implementation work by itself.
     *
     * @param array<string,mixed> $capabilities
     */
    public function swarmActionGateError(string $action, array $capabilities): ?string
    {
        $mutatingActions = ['create_run', 'claim_paths', 'release_paths', 'append_event'];
        $readActions = ['get_run', 'list_events'];
        if (in_array($action, $mutatingActions, true)) {
            return ($capabilities['swarm_coordination_accepted'] ?? false) === true
                ? null : 'SWARM_CAPABILITY_NOT_ACCEPTED';
        }
        if (in_array($action, $readActions, true)) {
            return ($capabilities['swarm_coordination_accepted'] ?? false) === true
                ? null : 'SWARM_CAPABILITY_NOT_ACCEPTED';
        }
        return 'SWARM_INVALID_ACTION';
    }

    /** @return array{evidence_sha256:string,protocol?:int}|null */
    private function verify(mixed $entry, string $runtimeSha, bool $needsProtocol = false): ?array
    {
        if (!is_array($entry) || ($entry['accepted'] ?? null) !== true
            || !is_string($entry['source_sha'] ?? null)
            || preg_match('/\A[a-f0-9]{40}\z/D', $entry['source_sha']) !== 1
            || !hash_equals($runtimeSha, $entry['source_sha'])
            || !is_string($entry['evidence_sha256'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/D', $entry['evidence_sha256']) !== 1) {
            return null;
        }
        if ($needsProtocol && (!is_int($entry['protocol'] ?? null) || $entry['protocol'] < 1)) {
            return null;
        }
        $verified = ['evidence_sha256' => $entry['evidence_sha256']];
        if ($needsProtocol) {
            $verified['protocol'] = $entry['protocol'];
        }
        return $verified;
    }
}
