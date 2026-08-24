<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Auth\AuthRepository;
use Api\Model\Security\ImpersonationRepository;
use Api\Model\Security\SessionRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Security\TokenManager;
use Api\System\Library\Support\Ulid;

final class ImpersonationService
{
    public function __construct(
        private readonly ImpersonationRepository $impersonations,
        private readonly UserManagementRepository $users,
        private readonly SessionRepository $sessions,
        private readonly AuthRepository $auth,
        private readonly HierarchyPolicy $hierarchy,
        private readonly TokenManager $tokens,
        private readonly JsonLogger $logger,
        private readonly int $tokenTtlSeconds
    ) {
    }

    public function start(array $actor, array $input, string $ip, string $userAgent): array
    {
        $targetPublicId = trim((string)($input['target_user_public_id'] ?? ''));
        $reason = trim((string)($input['reason'] ?? ''));
        if ($targetPublicId === '') {
            return ['ok' => false, 'code' => 'TARGET_USER_REQUIRED'];
        }

        $actorFull = $this->users->findById((int)($actor['id'] ?? 0));
        if (!$actorFull) {
            return ['ok' => false, 'code' => 'ACTOR_NOT_FOUND'];
        }

        $target = $this->users->findByPublicId($targetPublicId);
        if (!$target || (int)($target['is_active'] ?? 0) !== 1 || (string)($target['deleted_at'] ?? '') !== '') {
            return ['ok' => false, 'code' => 'TARGET_USER_NOT_FOUND'];
        }

        $actorId = (int)($actorFull['id'] ?? 0);
        $targetId = (int)($target['id'] ?? 0);
        if ($actorId <= 0 || $targetId <= 0) {
            return ['ok' => false, 'code' => 'TARGET_USER_NOT_FOUND'];
        }

        if ($actorId === $targetId) {
            return ['ok' => false, 'code' => 'IMPERSONATION_SELF_FORBIDDEN'];
        }

        $actorIsRoot = (int)($actorFull['is_root'] ?? 0) === 1;
        $targetIsRoot = (int)($target['is_root'] ?? 0) === 1;

        if ($targetIsRoot && !$actorIsRoot) {
            return ['ok' => false, 'code' => 'FORBIDDEN_ROOT_PROTECTED'];
        }

        if (!$actorIsRoot && !$this->hierarchy->canManageUser($actorFull, $target)) {
            return ['ok' => false, 'code' => 'FORBIDDEN_HIERARCHY'];
        }

        $existing = $this->impersonations->findActiveByAdminAndTarget($actorId, $targetId);
        if ($existing) {
            return ['ok' => false, 'code' => 'IMPERSONATION_ALREADY_ACTIVE', 'audit' => $this->normalizeAudit($existing)];
        }

        $now = gmdate('Y-m-d H:i:s');
        $auditPublicId = Ulid::generate('imp');
        $this->impersonations->create([
            'public_id' => $auditPublicId,
            'admin_user_id' => $actorId,
            'target_user_id' => $targetId,
            'reason' => $reason,
            'started_at' => $now,
            'ended_at' => null,
        ]);

        $plainAccess = $this->tokens->generate();
        $sessionPublicId = Ulid::generate('ses');
        // M-9: Impersonation sessions have a short fixed TTL (15 minutes),
        // no sliding window extension — admin must re-authenticate to continue.
        $impersonationTtl = 900; // 15 minutes
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $impersonationTtl);
        $sessionUserAgent = $this->buildImpersonationUserAgent($auditPublicId, (string)($actorFull['public_id'] ?? ''), $userAgent);

        $this->auth->createSession([
            'public_id' => $sessionPublicId,
            'user_id' => $targetId,
            'token_hash' => $this->tokens->hash($plainAccess),
            'ip' => $ip,
            'user_agent' => $sessionUserAgent,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        $audit = $this->impersonations->findByPublicId($auditPublicId);
        $this->logger->audit([
            'action' => 'impersonation_start',
            'actor_public_id' => (string)($actorFull['public_id'] ?? ''),
            'entity_type' => 'impersonation',
            'entity_public_id' => $auditPublicId,
            'target_user_public_id' => (string)($target['public_id'] ?? ''),
            'reason' => $reason,
        ]);
        $this->logger->security([
            'event_type' => 'impersonation_started',
            'actor_public_id' => (string)($actorFull['public_id'] ?? ''),
            'target_user_public_id' => (string)($target['public_id'] ?? ''),
            'impersonation_public_id' => $auditPublicId,
            'session_public_id' => $sessionPublicId,
            'ip' => $this->maskIp($ip, $actor),
            'user_agent' => '***',
        ]);

        return [
            'ok' => true,
            'audit' => $audit ? $this->normalizeAudit($audit) : ['public_id' => $auditPublicId],
            'impersonation_access_token' => $plainAccess,
            'token_type' => 'Bearer',
            'expires_in' => $impersonationTtl,  // M-1 fix: return actual impersonation TTL, not global token TTL
            'session_public_id' => $sessionPublicId,
            'target_user' => [
                'public_id' => (string)($target['public_id'] ?? ''),
                'login' => (string)($target['login'] ?? ''),
                'full_name' => (string)($target['full_name'] ?? ''),
                'email' => (string)($target['email'] ?? ''),
                'locale' => (string)($target['locale'] ?? 'en-gb'),
                'is_root' => (int)($target['is_root'] ?? 0) === 1,
                'is_active' => (int)($target['is_active'] ?? 0) === 1,
            ],
        ];
    }

    public function status(array $actor, string $sessionPublicId): array
    {
        $current = [
            'active' => false,
            'audit' => null,
        ];

        $session = $this->sessions->findByPublicId($sessionPublicId);
        if ($session && (string)($session['revoked_at'] ?? '') === '') {
            $auditPublicId = $this->extractAuditPublicIdFromUserAgent((string)($session['user_agent'] ?? ''));
            if ($auditPublicId !== null) {
                $audit = $this->impersonations->findActiveByPublicId($auditPublicId);
                if ($audit) {
                    $current = [
                        'active' => true,
                        'audit' => $this->normalizeAudit($audit),
                    ];
                }
            }
        }

        $activeStarted = $this->impersonations->listActiveByAdminUserId((int)($actor['id'] ?? 0), 20);
        $items = [];
        foreach ($activeStarted as $row) {
            $items[] = $this->normalizeAudit($row);
        }

        return [
            'current' => $current,
            'active_started_by_me' => $items,
        ];
    }

    public function stop(array $actor, string $sessionPublicId, ?string $auditPublicId, string $ip, string $userAgent): array
    {
        $auditId = $auditPublicId !== null ? trim($auditPublicId) : '';
        if ($auditId === '') {
            $session = $this->sessions->findByPublicId($sessionPublicId);
            $auditId = $session ? (string)($this->extractAuditPublicIdFromUserAgent((string)($session['user_agent'] ?? '')) ?? '') : '';
        }
        if ($auditId === '') {
            return ['ok' => false, 'code' => 'IMPERSONATION_NOT_ACTIVE'];
        }

        $audit = $this->impersonations->findByPublicId($auditId);
        if (!$audit) {
            return ['ok' => false, 'code' => 'IMPERSONATION_NOT_FOUND'];
        }

        $actorId = (int)($actor['id'] ?? 0);
        $actorIsRoot = (int)($actor['is_root'] ?? 0) === 1;
        $adminId = (int)($audit['admin_user_id'] ?? 0);
        $targetId = (int)($audit['target_user_id'] ?? 0);

        if (!$actorIsRoot && $actorId !== $adminId && $actorId !== $targetId) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        if ((string)($audit['ended_at'] ?? '') !== '') {
            return ['ok' => false, 'code' => 'IMPERSONATION_ALREADY_STOPPED', 'audit' => $this->normalizeAudit($audit)];
        }

        $endedAt = gmdate('Y-m-d H:i:s');
        $ok = $this->impersonations->endByPublicId($auditId, $endedAt);
        if (!$ok) {
            return ['ok' => false, 'code' => 'IMPERSONATION_NOT_FOUND'];
        }

        $revokedCount = $this->sessions->revokeByUserAgentNeedle('impersonation:audit=' . $auditId . ';', $endedAt);
        $ended = $this->impersonations->findByPublicId($auditId);

        $this->logger->audit([
            'action' => 'impersonation_stop',
            'actor_public_id' => (string)($actor['public_id'] ?? ''),
            'entity_type' => 'impersonation',
            'entity_public_id' => $auditId,
            'revoked_sessions' => $revokedCount,
        ]);
        $this->logger->security([
            'event_type' => 'impersonation_stopped',
            'actor_public_id' => (string)($actor['public_id'] ?? ''),
            'impersonation_public_id' => $auditId,
            'revoked_sessions' => $revokedCount,
            'ip' => $this->maskIp($ip, $actor),
            'user_agent' => '***',
        ]);

        return [
            'ok' => true,
            'audit' => $ended ? $this->normalizeAudit($ended) : ['public_id' => $auditId],
            'revoked_sessions' => $revokedCount,
        ];
    }

    private function buildImpersonationUserAgent(string $auditPublicId, string $adminPublicId, string $originUserAgent): string
    {
        $origin = trim($originUserAgent);
        if ($origin === '') {
            $origin = 'unknown';
        }

        return sprintf(
            'impersonation:audit=%s;admin=%s;origin=%s',
            $auditPublicId,
            $adminPublicId,
            substr($origin, 0, 180)
        );
    }

    private function extractAuditPublicIdFromUserAgent(string $userAgent): ?string
    {
        if (preg_match('/impersonation:audit=([A-Za-z0-9_]+);/u', $userAgent, $m) === 1) {
            return (string)$m[1];
        }

        return null;
    }

    private function maskIp(string $ip, array $actor): string
    {
        $isRoot = (bool)(($actor['is_root'] ?? false));
        if ($isRoot) {
            return $ip;
        }
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $parts[3] = 'xxx';
            return implode('.', $parts);
        }
        $binary = @inet_pton($ip);
        if ($binary !== false && strlen($binary) === 16) {
            for ($i = 12; $i < 16; $i++) {
                $binary[$i] = "\x00";
            }
            $masked = inet_ntop($binary);
            if ($masked !== false) {
                return $masked;
            }
        }
        return $ip;
    }

    private function normalizeAudit(array $row): array
    {
        return [
            'public_id' => (string)($row['public_id'] ?? ''),
            'admin_user_public_id' => (string)($row['admin_public_id'] ?? ''),
            'admin_login' => (string)($row['admin_login'] ?? ''),
            'target_user_public_id' => (string)($row['target_public_id'] ?? ''),
            'target_login' => (string)($row['target_login'] ?? ''),
            'reason' => (string)($row['reason'] ?? ''),
            'started_at' => (string)($row['started_at'] ?? ''),
            'ended_at' => $row['ended_at'] ?? null,
            'active' => (string)($row['ended_at'] ?? '') === '',
        ];
    }
}
