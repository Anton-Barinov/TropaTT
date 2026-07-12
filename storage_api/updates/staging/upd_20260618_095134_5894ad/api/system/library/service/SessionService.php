<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Security\SessionRepository;
use Api\System\Library\Logger\JsonLogger;

final class SessionService
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly JsonLogger $logger
    ) {
    }

    public function list(array $actor, array $filters): array
    {
        $userId = (int)($actor['id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        [$items, $total, $page, $limit] = $this->sessions->listByUserId($userId, $page, $limit);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function revoke(array $actor, string $sessionPublicId): array
    {
        $session = $this->sessions->findByPublicId($sessionPublicId);
        if (!$session) {
            return ['ok' => false, 'code' => 'SESSION_NOT_FOUND'];
        }

        $actorId = (int)($actor['id'] ?? 0);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        if (!$actorIsRoot && (int)$session['user_id'] !== $actorId) {
            return ['ok' => false, 'code' => 'FORBIDDEN'];
        }

        $ok = $this->sessions->revokeByPublicId($sessionPublicId, gmdate('Y-m-d H:i:s'));
        if ($ok) {
            $this->logger->security([
                'event_type' => 'session_revoked',
                'actor_public_id' => $actor['public_id'] ?? null,
                'session_public_id' => $sessionPublicId,
            ]);
        }

        return ['ok' => $ok, 'code' => $ok ? 'OK' : 'SESSION_NOT_FOUND'];
    }

    public function revokeOthers(array $actor, string $currentSessionPublicId): int
    {
        $userId = (int)($actor['id'] ?? 0);
        $count = $this->sessions->revokeOthers($userId, $currentSessionPublicId, gmdate('Y-m-d H:i:s'));

        $this->logger->security([
            'event_type' => 'session_revoke_others',
            'actor_public_id' => $actor['public_id'] ?? null,
            'kept_session_public_id' => $currentSessionPublicId,
            'revoked_count' => $count,
        ]);

        return $count;
    }

    public function revokeDevice(array $actor, string $deviceFingerprint, string $currentSessionPublicId): int
    {
        $userId = (int)($actor['id'] ?? 0);
        $count = $this->sessions->revokeByDeviceFingerprint(
            $userId,
            $deviceFingerprint,
            gmdate('Y-m-d H:i:s'),
            $currentSessionPublicId
        );

        $this->logger->security([
            'event_type' => 'session_revoke_device',
            'actor_public_id' => $actor['public_id'] ?? null,
            'device_fingerprint' => $deviceFingerprint,
            'revoked_count' => $count,
        ]);

        return $count;
    }
}
