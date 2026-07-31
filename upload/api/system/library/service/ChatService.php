<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use PDO;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;

final class ChatService
{
    use TranslatableTrait;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?NotificationService $notifications = null,
        ?LanguageManager $lang = null
    ) {
        $this->lang = $lang ?? new LanguageManager(__DIR__ . '/../../language');
    }

    public function ensureDirectChat(int $actorUserId, int $withUserId): array
    {
        $actorUserId = max(0, $actorUserId);
        $withUserId = max(0, $withUserId);
        if ($actorUserId <= 0 || $withUserId <= 0 || $actorUserId === $withUserId) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT c.*
            FROM chats c
            JOIN chat_participants cp1 ON cp1.chat_id = c.id AND cp1.user_id = :uid1
            JOIN chat_participants cp2 ON cp2.chat_id = c.id AND cp2.user_id = :uid2
            WHERE c.type = 'direct'
            LIMIT 1
        ");
        $stmt->execute(['uid1' => $actorUserId, 'uid2' => $withUserId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing)) {
            return $existing;
        }

        $chat = $this->createChat('', 'direct', null, null, $actorUserId);
        $this->syncParticipants((int)$chat['id'], [$actorUserId, $withUserId], [$actorUserId]);
        return $chat;
    }

    /** @param string[] $participantPublicIds */
    public function ensureGroupChat(string $title, array $participantPublicIds, array $actor = []): array
    {
        $actorUserId = (int)($actor['id'] ?? 0);
        if ($actorUserId <= 0 || count($participantPublicIds) === 0) return [];
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 160 || count($participantPublicIds) > 100) return [];

        $ids = [];
        if ($participantPublicIds !== []) {
            $placeholders = implode(',', array_fill(0, count($participantPublicIds), '?'));
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE public_id IN ({$placeholders}) AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute($participantPublicIds);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        if ($ids === []) return [];

        $allIds = array_values(array_unique(array_filter(array_merge([$actorUserId], $ids), static fn(int $id): bool => $id > 0)));
        if (count($allIds) < 2) return [];

        $chat = $this->createChat($title, 'group', null, null, $actorUserId);
        $this->syncParticipants((int)$chat['id'], $allIds, [$actorUserId]);
        return $chat;
    }

    public function ensureProjectChat(array $project, array $actor = []): array
    {
        $projectId = (int)($project['id'] ?? 0);
        if ($projectId <= 0) {
            return [];
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $title = trim((string)($project['title'] ?? '')) ?: $this->t('chat/messages.project_fallback_title');
        $participantIds = $this->projectParticipantIds($project);
        $adminIds = $this->projectAdminIds($project);
        if ($participantIds === []) {
            return [];
        }
        $createdByUserId = $actorUserId > 0 ? $actorUserId : (int)($adminIds[0] ?? ($participantIds[0] ?? 0));
        $chat = $this->findSystemChat('project', $projectId);
        if (!$chat) {
            $chat = $this->createChat($title, 'project', $projectId, null, $createdByUserId > 0 ? $createdByUserId : null);
        } elseif ((string)($chat['title'] ?? '') !== $title) {
            $this->pdo->prepare("UPDATE chats SET title = :title WHERE id = :id")
                ->execute(['title' => $title, 'id' => (int)$chat['id']]);
            $chat['title'] = $title;
        }

        $this->syncParticipants((int)$chat['id'], $participantIds, $adminIds);
        return $chat;
    }

    public function ensureTeamChat(array $team, array $actor = []): array
    {
        $teamId = (int)($team['id'] ?? 0);
        if ($teamId <= 0) {
            return [];
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $title = trim((string)($team['title'] ?? '')) ?: $this->t('chat/messages.team_fallback_title');
        $participantIds = $this->teamParticipantIds($team);
        $adminIds = $this->teamAdminIds($team);
        if ($participantIds === []) {
            return [];
        }
        $createdByUserId = $actorUserId > 0 ? $actorUserId : (int)($adminIds[0] ?? ($participantIds[0] ?? 0));
        $chat = $this->findSystemChat('team', $teamId);
        if (!$chat) {
            $chat = $this->createChat($title, 'team', null, $teamId, $createdByUserId > 0 ? $createdByUserId : null);
        } elseif ((string)($chat['title'] ?? '') !== $title) {
            $this->pdo->prepare("UPDATE chats SET title = :title WHERE id = :id")
                ->execute(['title' => $title, 'id' => (int)$chat['id']]);
            $chat['title'] = $title;
        }

        $this->syncParticipants((int)$chat['id'], $participantIds, $adminIds);
        return $chat;
    }

    public function repairSystemChats(): array
    {
        $createdBefore = $this->countSystemChats();
        foreach ($this->loadProjectRows() as $project) {
            $this->ensureProjectChat($project);
        }
        foreach ($this->loadTeamRows() as $team) {
            $this->ensureTeamChat($team);
        }

        $createdAfter = $this->countSystemChats();
        return [
            'created' => max(0, $createdAfter - $createdBefore),
            'system_chats' => $createdAfter,
        ];
    }

    public function assertParticipant(int $chatId, int $userId): bool
    {
        if ($chatId <= 0 || $userId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM chat_participants WHERE chat_id = :cid AND user_id = :uid LIMIT 1");
        $stmt->execute(['cid' => $chatId, 'uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function updateParticipantSettings(int $chatId, int $userId, ?bool $isFavorite = null, mixed $mutedUntil = '__keep__'): array
    {
        if (!$this->assertParticipant($chatId, $userId)) {
            return [];
        }

        $sets = [];
        $params = ['cid' => $chatId, 'uid' => $userId];
        if ($isFavorite !== null) {
            $sets[] = 'is_favorite = :favorite';
            $params['favorite'] = $isFavorite ? 1 : 0;
        }
        if ($mutedUntil !== '__keep__') {
            $sets[] = 'muted_until = :muted_until';
            $params['muted_until'] = $mutedUntil;
        }
        if ($sets !== []) {
            $this->pdo->prepare('UPDATE chat_participants SET ' . implode(', ', $sets) . ' WHERE chat_id = :cid AND user_id = :uid')
                ->execute($params);
        }

        $stmt = $this->pdo->prepare("SELECT is_favorite, muted_until FROM chat_participants WHERE chat_id = :cid AND user_id = :uid");
        $stmt->execute(['cid' => $chatId, 'uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    public function markRead(int $chatId, int $userId): void
    {
        if ($chatId <= 0 || $userId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT MAX(id) FROM chat_messages WHERE chat_id = :cid");
        $stmt->execute(['cid' => $chatId]);
        $lastMessageId = (int)$stmt->fetchColumn();
        if ($lastMessageId <= 0) {
            return;
        }

        $this->pdo->prepare("
            INSERT INTO chat_read_markers (chat_id, user_id, last_read_message_id, updated_at)
            VALUES (:cid, :uid, :mid, NOW())
            ON DUPLICATE KEY UPDATE last_read_message_id = :mid2, updated_at = NOW()
        ")->execute([
            'cid' => $chatId,
            'uid' => $userId,
            'mid' => $lastMessageId,
            'mid2' => $lastMessageId,
        ]);
    }

    /**
     * @param int $chatId Chat numeric id
     * @param int $actorUserId User archiving the chat
     * @return array Empty array on failure, chat row on success
     */
    public function archiveChat(int $chatId, int $actorUserId): array
    {
        if ($chatId <= 0 || $actorUserId <= 0) {
            return [];
        }

        $chat = $this->pdo->prepare("SELECT id, public_id, title, type, project_id, team_id, created_by_user_id, created_at, updated_at, last_message_at, archived_at, archived_by_user_id FROM chats WHERE id = :id AND archived_at IS NULL AND created_by_user_id = :uid");
        $chat->execute(['id' => $chatId, 'uid' => $actorUserId]);
        $chat = $chat->fetch(PDO::FETCH_ASSOC);
        if (!is_array($chat)) {
            return [];
        }

        $participants = $this->pdo->prepare("SELECT user_id, role FROM chat_participants WHERE chat_id = :cid");
        $participants->execute(['cid' => $chatId]);
        $participantRows = $participants->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $participantIds = array_map(static fn(array $row): int => (int)($row['user_id'] ?? 0), $participantRows);
        $archivedParticipants = array_map(static function (array $row): array {
            return [
                'user_id' => (int)($row['user_id'] ?? 0),
                'role' => (string)($row['role'] ?? 'member') === 'admin' ? 'admin' : 'member',
            ];
        }, $participantRows);

        $archivedIds = json_encode($archivedParticipants !== [] ? $archivedParticipants : $participantIds, JSON_UNESCAPED_UNICODE);

        $this->pdo->prepare("UPDATE chats SET archived_at = NOW(), archived_by_user_id = :uid, archived_participant_ids = :pids WHERE id = :id")
            ->execute(['uid' => $actorUserId, 'pids' => $archivedIds, 'id' => $chatId]);

        $this->pdo->prepare("DELETE FROM chat_participants WHERE chat_id = :cid")->execute(['cid' => $chatId]);

        $refetch = $this->pdo->prepare("SELECT id, public_id, title, type, project_id, team_id, created_by_user_id, created_at, updated_at, last_message_at, archived_at, archived_by_user_id FROM chats WHERE id = :id");
        $refetch->execute(['id' => $chatId]);
        $row = $refetch->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /**
     * @param int $chatId Chat numeric id
     * @param int $actorUserId User restoring the chat
     * @return array Empty array on failure, chat row on success
     */
    public function restoreChat(int $chatId, int $actorUserId): array
    {
        if ($chatId <= 0 || $actorUserId <= 0) {
            return [];
        }

        $chat = $this->pdo->prepare("SELECT id, public_id, title, type, project_id, team_id, created_by_user_id, created_at, updated_at, last_message_at, archived_at, archived_by_user_id FROM chats WHERE id = :id AND archived_by_user_id = :uid AND archived_at IS NOT NULL");
        $chat->execute(['id' => $chatId, 'uid' => $actorUserId]);
        $chat = $chat->fetch(PDO::FETCH_ASSOC);
        if (!is_array($chat)) {
            return [];
        }

        $archivedParticipants = $this->decodeArchivedParticipants($chat['archived_participant_ids'] ?? '[]');

        $now = gmdate('Y-m-d H:i:s');

        if ($archivedParticipants !== []) {
            foreach ($archivedParticipants as $participant) {
                $userId = (int)$participant['user_id'];
                $role = (string)$participant['role'] === 'admin' ? 'admin' : 'member';
                $this->pdo->prepare("
                    INSERT INTO chat_participants (chat_id, user_id, role, joined_at)
                    VALUES (:cid, :uid, :role, :now)
                    ON DUPLICATE KEY UPDATE role = :role2
                ")->execute(['cid' => $chatId, 'uid' => $userId, 'role' => $role, 'role2' => $role, 'now' => $now]);
            }
        }

        $this->pdo->prepare("UPDATE chats SET archived_at = NULL, archived_by_user_id = NULL, archived_participant_ids = NULL, updated_at = :now WHERE id = :id")
            ->execute(['now' => $now, 'id' => $chatId]);

        $chat['archived_at'] = null;
        $chat['archived_by_user_id'] = null;
        $chat['archived_participant_ids'] = null;
        return $chat;
    }

    public function notifyMessage(array $chat, array $message, array $actor, array $options = []): int
    {
        if (!$this->notifications) {
            return 0;
        }

        $chatId = (int)($chat['id'] ?? 0);
        $chatPublicId = trim((string)($chat['public_id'] ?? ''));
        $messagePublicId = trim((string)($message['public_id'] ?? ''));
        $actorUserId = (int)($actor['id'] ?? 0);
        if ($chatId <= 0 || $chatPublicId === '' || $messagePublicId === '') {
            return 0;
        }

        $participantIds = $this->participantIdsForNotification($chatId);
        $priorityIds = array_values(array_unique(array_filter(array_map('intval', $options['priority_user_ids'] ?? []), static fn(int $id): bool => $id > 0)));
        $participantIds = array_values(array_unique(array_merge($participantIds, $priorityIds)));
        $chatTitle = $this->chatTitle($chat);
        $actorName = trim((string)($actor['full_name'] ?? '')) ?: trim((string)($actor['login'] ?? $this->t('chat/messages.default_user_name')));
        $text = trim((string)($message['text'] ?? ''));
        $snippet = mb_substr($text, 0, 160);
        $action = (string)($options['action_code'] ?? 'chat_message_created');
        $title = (string)($options['title'] ?? $this->t('chat/messages.new_chat_message'));

        return $this->notifications->notifyUsers($participantIds, [
            'category' => 'chat',
            'title' => $title,
            'body' => $actorName . ' ' . $this->t('chat/messages.wrote_in_chat') . ' "' . $chatTitle . '": ' . $snippet,
            'entity_type' => 'chat_message',
            'entity_public_id' => $messagePublicId,
            'action_code' => $action,
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => 'index.php?route=chat&id=' . rawurlencode($chatPublicId),
            'payload' => [
                'chat_public_id' => $chatPublicId,
                'chat_title' => $chatTitle,
                'chat_type' => $chat['type'] ?? 'direct',
                'message_public_id' => $messagePublicId,
                'reply_to_message_public_id' => $options['reply_to_message_public_id'] ?? null,
                'mentioned_user_ids' => $priorityIds,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    public function mentionedParticipantIds(int $chatId, string $text): array
    {
        if ($chatId <= 0 || trim($text) === '' || !preg_match_all('/@([\\p{L}\\p{N}._-]{2,80})/u', $text, $matches)) {
            return [];
        }
        $tokens = array_values(array_unique(array_map(static fn(string $v): string => mb_strtolower($v), $matches[1] ?? [])));
        if ($tokens === []) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT u.id, u.login, u.full_name
            FROM chat_participants cp
            JOIN users u ON u.id = cp.user_id
            WHERE cp.chat_id = :cid
        ");
        $stmt->execute(['cid' => $chatId]);
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $login = mb_strtolower(trim((string)($row['login'] ?? '')));
            $name = mb_strtolower(trim(preg_replace('/\\s+/u', '', (string)($row['full_name'] ?? '')) ?? ''));
            foreach ($tokens as $token) {
                if ($token !== '' && ($token === $login || ($name !== '' && str_contains($name, $token)))) {
                    $ids[] = (int)$row['id'];
                }
            }
        }

        return array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
    }

    private function findSystemChat(string $type, int $entityId): ?array
    {
        $column = $type === 'project' ? 'project_id' : 'team_id';
        $stmt = $this->pdo->prepare("SELECT id, public_id, title, type, project_id, team_id, created_by_user_id, created_at, updated_at, last_message_at, archived_at, archived_by_user_id FROM chats WHERE type = :type AND {$column} = :id ORDER BY id ASC LIMIT 1");
        $stmt->execute(['type' => $type, 'id' => $entityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function createChat(string $title, string $type, ?int $projectId, ?int $teamId, ?int $createdByUserId): array
    {
        $publicId = 'chat_' . bin2hex(random_bytes(12));
        $this->pdo->prepare("
            INSERT INTO chats (public_id, title, type, project_id, team_id, created_by_user_id, created_at, updated_at, last_message_at)
            VALUES (:pid, :title, :type, :project_id, :team_id, :created_by, NOW(), NOW(), NOW())
        ")->execute([
            'pid' => $publicId,
            'title' => $title,
            'type' => $type,
            'project_id' => $projectId,
            'team_id' => $teamId,
            'created_by' => $createdByUserId,
        ]);

        $stmt = $this->pdo->prepare("SELECT id, public_id, title, type, project_id, team_id, created_by_user_id, created_at, updated_at, last_message_at, archived_at, archived_by_user_id FROM chats WHERE id = :id");
        $stmt->execute(['id' => (int)$this->pdo->lastInsertId()]);
        $chat = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($chat) ? $chat : ['public_id' => $publicId];
    }

    /** @param int[] $participantIds @param int[] $adminIds */
    private function syncParticipants(int $chatId, array $participantIds, array $adminIds = []): void
    {
        $participantIds = array_values(array_unique(array_filter(array_map('intval', $participantIds), static fn(int $id): bool => $id > 0)));
        $adminIds = array_values(array_unique(array_filter(array_map('intval', $adminIds), static fn(int $id): bool => $id > 0)));
        if ($chatId <= 0 || $participantIds === []) {
            return;
        }

        foreach ($participantIds as $userId) {
            $role = in_array($userId, $adminIds, true) ? 'admin' : 'member';
            $this->pdo->prepare("
                INSERT INTO chat_participants (chat_id, user_id, role, joined_at)
                VALUES (:cid, :uid, :role, NOW())
                ON DUPLICATE KEY UPDATE role = :role2
            ")->execute(['cid' => $chatId, 'uid' => $userId, 'role' => $role, 'role2' => $role]);
        }

        $placeholders = implode(',', array_fill(0, count($participantIds), '?'));
        $params = array_merge([$chatId], $participantIds);
        $this->pdo->prepare("DELETE FROM chat_participants WHERE chat_id = ? AND user_id NOT IN ({$placeholders})")
            ->execute($params);
    }

    /** @return int[] */
    private function participantIds(int $chatId): array
    {
        $stmt = $this->pdo->prepare("SELECT user_id FROM chat_participants WHERE chat_id = :cid");
        $stmt->execute(['cid' => $chatId]);
        return array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    /** @return int[] */
    private function participantIdsForNotification(int $chatId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT user_id
            FROM chat_participants
            WHERE chat_id = :cid
              AND (muted_until IS NULL OR muted_until < NOW())
        ");
        $stmt->execute(['cid' => $chatId]);
        return array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    /**
     * @return list<array{user_id:int,role:string}>
     */
    private function decodeArchivedParticipants(mixed $raw): array
    {
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) return [];
        $items = [];
        foreach ($decoded as $entry) {
            if (is_array($entry)) {
                $userId = (int)($entry['user_id'] ?? $entry['id'] ?? 0);
                $role = (string)($entry['role'] ?? 'member') === 'admin' ? 'admin' : 'member';
            } else {
                $userId = (int)$entry;
                $role = 'member';
            }
            if ($userId > 0) $items[$userId] = ['user_id' => $userId, 'role' => $role];
        }
        return array_values($items);
    }

    /** @return int[] */
    private function projectParticipantIds(array $project): array
    {
        return array_values(array_unique(array_filter(array_merge([
            (int)($project['created_by_user_id'] ?? 0),
            (int)($project['manager_user_id'] ?? 0),
            (int)($project['team_manager_user_id'] ?? 0),
        ], $this->decodeIds($project['team_member_user_ids'] ?? null)), static fn(int $id): bool => $id > 0)));
    }

    /** @return int[] */
    private function projectAdminIds(array $project): array
    {
        return array_values(array_unique(array_filter([
            (int)($project['created_by_user_id'] ?? 0),
            (int)($project['manager_user_id'] ?? 0),
            (int)($project['team_manager_user_id'] ?? 0),
        ], static fn(int $id): bool => $id > 0)));
    }

    /** @return int[] */
    private function teamParticipantIds(array $team): array
    {
        return array_values(array_unique(array_filter(array_merge([
            (int)($team['created_by_user_id'] ?? 0),
            (int)($team['manager_user_id'] ?? 0),
        ], $this->decodeIds($team['member_user_ids'] ?? null)), static fn(int $id): bool => $id > 0)));
    }

    /** @return int[] */
    private function teamAdminIds(array $team): array
    {
        return array_values(array_unique(array_filter([
            (int)($team['created_by_user_id'] ?? 0),
            (int)($team['manager_user_id'] ?? 0),
        ], static fn(int $id): bool => $id > 0)));
    }

    /** @return int[] */
    private function decodeIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $id): bool => $id > 0)));
    }

    /** @return array<int,array<string,mixed>> */
    private function loadProjectRows(): array
    {
        $rows = $this->pdo->query("
            SELECT p.id, p.public_id, p.title, p.created_by_user_id, p.manager_user_id, p.team_public_id,
                   t.manager_user_id AS team_manager_user_id, t.member_user_ids AS team_member_user_ids
            FROM projects p
            LEFT JOIN teams t ON t.public_id = p.team_public_id
            WHERE p.archived_at IS NULL
        ");
        return $rows ? ($rows->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return array<int,array<string,mixed>> */
    private function loadTeamRows(): array
    {
        $rows = $this->pdo->query("SELECT id, title, created_by_user_id, manager_user_id, member_user_ids FROM teams");
        return $rows ? ($rows->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    private function countSystemChats(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM chats WHERE type IN ('project', 'team')");
        return $stmt ? (int)$stmt->fetchColumn() : 0;
    }

    private function chatTitle(array $chat): string
    {
        $title = trim((string)($chat['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }
        if (($chat['type'] ?? '') === 'direct') {
            return $this->t('chat/messages.direct_chat_title');
        }
        return $this->t('chat/messages.default_chat_title');
    }
}
