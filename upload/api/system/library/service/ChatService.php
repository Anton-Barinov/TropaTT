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

    /**
     * Ensure a project_client chat exists for the given project. This chat type
     * is separate from the internal 'project' chat and is used for communication
     * between staff and external (portal) users.
     *
     * Defence-in-depth: every message read/write for external actors must also
     * verify chat.type === 'project_client' at the controller level.
     *
     * @param array $project The project row (must have id, public_id, title)
     * @param int $createdByUserId The staff user who creates the chat
     * @return array The chat row
     */
    public function ensureProjectClientChat(array $project, int $createdByUserId): array
    {
        $projectId = (int)($project['id'] ?? 0);
        if ($projectId <= 0 || $createdByUserId <= 0) {
            return [];
        }

        $title = trim((string)($project['title'] ?? '')) ?: $this->t('chat/messages.project_fallback_title');
        $chat = $this->findSystemChat('project_client', $projectId);
        if (!$chat) {
            $chat = $this->createChat($title, 'project_client', $projectId, null, $createdByUserId);
        } elseif ((string)($chat['title'] ?? '') !== $title) {
            $this->pdo->prepare("UPDATE chats SET title = :title WHERE id = :id")
                ->execute(['title' => $title, 'id' => (int)$chat['id']]);
            $chat['title'] = $title;
        }

        return $chat;
    }

    /**
     * Add a participant to a project_client chat. Only staff with project.manage
     * should call this; the controller must verify the chat type.
     */
    public function addClientChatParticipant(int $chatId, int $userId, string $role = 'member'): bool
    {
        if ($chatId <= 0 || $userId <= 0) {
            return false;
        }
        $this->pdo->prepare("
            INSERT INTO chat_participants (chat_id, user_id, role, joined_at)
            VALUES (:cid, :uid, :role, NOW())
            ON DUPLICATE KEY UPDATE role = :role2
        ")->execute(['cid' => $chatId, 'uid' => $userId, 'role' => $role, 'role2' => $role]);
        return true;
    }

    /**
     * Remove a participant from a project_client chat.
     */
    public function removeClientChatParticipant(int $chatId, int $userId): bool
    {
        if ($chatId <= 0 || $userId <= 0) {
            return false;
        }
        $this->pdo->prepare("DELETE FROM chat_participants WHERE chat_id = :cid AND user_id = :uid")
            ->execute(['cid' => $chatId, 'uid' => $userId]);
        return true;
    }

    /**
     * List project_client chats for an external user. Returns only chats
     * where the user is a participant AND the chat type is 'project_client'.
     *
     * @return list<array>
     */
    public function listExternalChats(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.public_id, c.title, c.type, c.project_id, c.last_message_at, c.created_at,
                   p.title AS project_title, p.public_id AS project_public_id
            FROM chats c
            JOIN chat_participants cp ON cp.chat_id = c.id AND cp.user_id = :uid
            LEFT JOIN projects p ON p.id = c.project_id
            WHERE c.type = 'project_client' AND c.archived_at IS NULL
            ORDER BY c.last_message_at DESC
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get messages for a project_client chat, with defence-in-depth type check.
     * External users can only read chats of type 'project_client'.
     *
     * @return array{messages: list<array>, total: int}
     */
    public function getClientChatMessages(int $chatId, int $userId, int $limit = 50, int $offset = 0): array
    {
        if ($chatId <= 0 || $userId <= 0) {
            return ['messages' => [], 'total' => 0];
        }

        // Defence-in-depth: verify chat type AND participant membership
        $chat = $this->getChatForExternal($chatId, $userId);
        if (!$chat) {
            return ['messages' => [], 'total' => 0];
        }

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        // Count total
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM chat_messages WHERE chat_id = :cid AND deleted_at IS NULL"
        );
        $countStmt->execute(['cid' => $chatId]);
        $total = (int)$countStmt->fetchColumn();

        // Fetch messages
        $stmt = $this->pdo->prepare("
            SELECT cm.public_id, cm.text, cm.message_type, cm.created_at, cm.edited_at,
                   cm.reply_to_message_id, cm.sender_user_id,
                   u.full_name AS sender_name, u.public_id AS sender_public_id
            FROM chat_messages cm
            LEFT JOIN users u ON u.id = cm.sender_user_id
            WHERE cm.chat_id = :cid AND cm.deleted_at IS NULL
            ORDER BY cm.id DESC
            LIMIT :limit OFFSET :offset
        ");
        // L-2: LIMIT/OFFSET must be bound as integers. With native prepared
        // statements (EMULATE_PREPARES=false), string-bound LIMIT/OFFSET is
        // rejected by MySQL, breaking the external chat read path entirely.
        $stmt->bindValue(':cid', $chatId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $messages = array_reverse($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);

        return ['messages' => $messages, 'total' => $total];
    }

    /**
     * Send a message to a project_client chat. External users can only
     * write to chats of type 'project_client'.
     *
     * @return array{ok:bool, message?:array, error?:string}
     */
    public function sendClientChatMessage(int $chatId, int $userId, string $text, ?string $replyToMessageId = null): array
    {
        if ($chatId <= 0 || $userId <= 0) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 10000) {
            return ['ok' => false, 'error' => 'invalid_text'];
        }

        // Defence-in-depth: verify chat type AND participant membership
        $chat = $this->getChatForExternal($chatId, $userId);
        if (!$chat) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        // Resolve reply_to internal id if provided
        $replyToInternalId = null;
        if ($replyToMessageId !== null && $replyToMessageId !== '') {
            $replyStmt = $this->pdo->prepare(
                "SELECT id FROM chat_messages WHERE public_id = :pid AND chat_id = :cid"
            );
            $replyStmt->execute(['pid' => $replyToMessageId, 'cid' => $chatId]);
            $replyToInternalId = (int)$replyStmt->fetchColumn();
            if ($replyToInternalId <= 0) {
                $replyToInternalId = null;
            }
        }

        $publicId = 'msg_' . bin2hex(random_bytes(12));
        $this->pdo->prepare("
            INSERT INTO chat_messages (public_id, chat_id, sender_user_id, reply_to_message_id, text, message_type, created_at)
            VALUES (:pid, :cid, :sid, :rtid, :text, 'text', NOW())
        ")->execute([
            'pid' => $publicId,
            'cid' => $chatId,
            'sid' => $userId,
            'rtid' => $replyToInternalId,
            'text' => $text,
        ]);

        // Update last_message_at
        $this->pdo->prepare("UPDATE chats SET last_message_at = NOW() WHERE id = :id")
            ->execute(['id' => $chatId]);

        $stmt = $this->pdo->prepare("
            SELECT cm.public_id, cm.text, cm.message_type, cm.created_at,
                   cm.reply_to_message_id, cm.sender_user_id,
                   u.full_name AS sender_name, u.public_id AS sender_public_id
            FROM chat_messages cm
            LEFT JOIN users u ON u.id = cm.sender_user_id
            WHERE cm.public_id = :pid
        ");
        $stmt->execute(['pid' => $publicId]);
        $message = $stmt->fetch(\PDO::FETCH_ASSOC);

        return ['ok' => true, 'message' => is_array($message) ? $message : ['public_id' => $publicId]];
    }

    /**
     * Get a chat with defence-in-depth type check for external users.
     * Returns null if the chat is not 'project_client' or the user is not a participant.
     */
    public function getChatForExternal(int $chatId, int $userId): ?array
    {
        if ($chatId <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT c.id, c.public_id, c.title, c.type, c.project_id, c.last_message_at, c.created_at
            FROM chats c
            JOIN chat_participants cp ON cp.chat_id = c.id AND cp.user_id = :uid
            WHERE c.id = :cid AND c.type = 'project_client' AND c.archived_at IS NULL
        ");
        $stmt->execute(['cid' => $chatId, 'uid' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * Find a project_client chat by public id, including its project public id.
     * Used by staff fallback access (staff may read/write the client chat of a
     * project they can access even when they are not an explicit participant).
     */
    public function findProjectClientChatByPublicId(string $publicId): ?array
    {
        if (trim($publicId) === '') {
            return null;
        }
        $stmt = $this->pdo->prepare("
            SELECT c.*, p.public_id AS project_public_id, p.client_public_id AS project_client_public_id
            FROM chats c
            LEFT JOIN projects p ON p.id = c.project_id
            WHERE c.public_id = :pid AND c.type = 'project_client' AND c.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['pid' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * All non-archived project_client chats with their project public id.
     * Staff visibility is filtered in the controller by project access.
     *
     * @return list<array>
     */
    public function listProjectClientChats(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, p.public_id AS project_public_id, p.client_public_id AS project_client_public_id
            FROM chats c
            LEFT JOIN projects p ON p.id = c.project_id
            WHERE c.type = 'project_client' AND c.archived_at IS NULL
            ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    public function findSystemChat(string $type, int $entityId): ?array
    {
        $column = ($type === 'project' || $type === 'project_client') ? 'project_id' : 'team_id';
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
