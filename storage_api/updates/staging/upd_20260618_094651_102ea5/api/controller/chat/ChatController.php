<?php
declare(strict_types=1);

namespace Api\Controller\Chat;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Service\ChatService;
use PDO;

final class ChatController extends BaseController
{
    public function list(): JsonResponse
    {
        $pdo = $this->container->get('db.pdo');
        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        $archived = (string)($this->request()->input('archived', '')) === '1';

        try {
            /** @var ChatService $service */
            $service = $this->container->get('service.chat');
            $service->repairSystemChats();

            $hasArchivedColumn = $this->tableHasColumn($pdo, 'chats', 'archived_at');

            if ($archived) {
                if (!$hasArchivedColumn) {
                    return $this->success('CHATS_ARCHIVED', $this->t('common/messages.ok'), ['items' => []]);
                }
                $stmt = $pdo->prepare("
                    SELECT c.*, 0 as is_favorite, null as muted_until, 0 as last_read_id, 0 as unread,
                        (SELECT text FROM chat_messages WHERE chat_id = c.id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1) as last_message,
                        (SELECT message_type FROM chat_messages WHERE chat_id = c.id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1) as last_message_type,
                        (SELECT u.full_name FROM chat_messages cm2 JOIN users u ON u.id = cm2.sender_user_id WHERE cm2.chat_id = c.id AND cm2.deleted_at IS NULL ORDER BY cm2.id DESC LIMIT 1) as last_sender,
                        c.archived_participant_ids as participant_names_raw
                    FROM chats c
                    WHERE c.archived_at IS NOT NULL AND c.archived_by_user_id = :archived_by
                    ORDER BY c.archived_at DESC
                ");
                $stmt->execute(['archived_by' => $userId]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($items as &$item) {
                    $ids = json_decode($item['participant_names_raw'] ?? '[]', true) ?: [];
                    unset($item['participant_names_raw']);
                    $names = [];
                    if ($ids !== []) {
                        $in = implode(',', array_fill(0, count($ids), '?'));
                        $ns = $pdo->prepare("SELECT COALESCE(NULLIF(full_name, ''), login) as name FROM users WHERE id IN ({$in})");
                        $ns->execute(array_map('intval', $ids));
                        $names = $ns->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    }
                    $item['participant_names'] = implode(', ', $names);
                    $item['is_archived'] = true;
                }
                unset($item);
                if ($items !== []) {
                    foreach ($items as &$item) {
                        $item['participants'] = $this->participantsForChatArchived($item);
                    }
                    unset($item);
                }
                return $this->success('CHATS_ARCHIVED', $this->t('common/messages.ok'), ['items' => $items]);
            }

            $archivedFilter = $hasArchivedColumn ? 'WHERE c.archived_at IS NULL' : '';
            $stmt = $pdo->prepare("
                SELECT c.*,
                    cp.is_favorite,
                    cp.muted_until,
                    COALESCE(rm.last_read_message_id, 0) as last_read_id,
                    (SELECT COUNT(*) FROM chat_messages cm WHERE cm.chat_id = c.id AND cm.id > COALESCE(rm.last_read_message_id, 0) AND cm.deleted_at IS NULL) as unread,
                    (SELECT text FROM chat_messages WHERE chat_id = c.id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1) as last_message,
                    (SELECT message_type FROM chat_messages WHERE chat_id = c.id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1) as last_message_type,
                    (SELECT u.full_name FROM chat_messages cm2 JOIN users u ON u.id = cm2.sender_user_id WHERE cm2.chat_id = c.id AND cm2.deleted_at IS NULL ORDER BY cm2.id DESC LIMIT 1) as last_sender,
                    (SELECT GROUP_CONCAT(COALESCE(NULLIF(u.full_name, ''), u.login)) FROM chat_participants cp2 JOIN users u ON u.id = cp2.user_id WHERE cp2.chat_id = c.id AND cp2.user_id <> :uid3) as participant_names
                FROM chats c
                JOIN chat_participants cp ON cp.chat_id = c.id AND cp.user_id = :uid
                LEFT JOIN chat_read_markers rm ON rm.chat_id = c.id AND rm.user_id = :uid2
                {$archivedFilter}
                ORDER BY cp.is_favorite DESC, COALESCE(c.last_message_at, c.created_at) DESC
            ");
            $stmt->execute(['uid' => $userId, 'uid2' => $userId, 'uid3' => $userId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[ChatController::list] ' . $e->getMessage());
            $items = [];
        }

        return $this->success('CHATS_LIST', $this->t('common/messages.ok'), ['items' => $items]);
    }

    private function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column} IS NULL LIMIT 0");
            $stmt->execute();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ensureChatArchiveColumns(PDO $pdo): void
    {
        $columns = [
            'archived_at' => 'DATETIME',
            'archived_by_user_id' => 'INTEGER',
            'archived_participant_ids' => 'TEXT',
        ];
        foreach ($columns as $col => $type) {
            try {
                $pdo->exec("ALTER TABLE chats ADD COLUMN {$col} {$type} NULL");
            } catch (\Throwable $e) {}
        }
    }

    public function get(array $params = []): JsonResponse
    {
        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        if (!$chat) {
            $chat = $this->archivedChatForCurrentUser((string)($params['public_id'] ?? ''));
        }
        if (!$chat) return $this->error('NOT_FOUND', $this->t('chat/messages.chat_not_found'), 404);
        $chat['participants'] = !empty($chat['archived_at'])
            ? $this->participantsForChatArchived($chat)
            : $this->participantsForChat((int)$chat['id']);

        return $this->success('CHAT_DETAIL', $this->t('common/messages.ok'), ['chat' => $chat]);
    }

    public function participants(array $params = []): JsonResponse
    {
        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        if (!$chat) return $this->error('NOT_FOUND', $this->t('chat/messages.chat_not_found'), 404);

        return $this->success('CHAT_PARTICIPANTS', $this->t('common/messages.ok'), ['items' => $this->participantsForChat((int)$chat['id'])]);
    }

    public function settings(array $params = []): JsonResponse
    {
        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        if (!$chat) return $this->error('NOT_FOUND', $this->t('chat/messages.chat_not_found'), 404);
        $input = $this->request()->allInput();
        $favorite = array_key_exists('is_favorite', $input) ? (bool)$input['is_favorite'] : null;
        $mutedUntil = '__keep__';
        if (array_key_exists('muted_until', $input)) {
            $raw = trim((string)$input['muted_until']);
            $mutedUntil = $raw !== '' ? $raw : null;
        } elseif (array_key_exists('is_muted', $input)) {
            $mutedUntil = (bool)$input['is_muted'] ? '9999-12-31 23:59:59' : null;
        }

        /** @var ChatService $service */
        $service = $this->container->get('service.chat');
        $state = $service->updateParticipantSettings((int)$chat['id'], $this->currentUserId(), $favorite, $mutedUntil);

        return $this->success('CHAT_SETTINGS_UPDATED', $this->t('chat/messages.settings_updated'), ['settings' => $state]);
    }

    public function create(): JsonResponse
    {
        $input = $this->request()->allInput();
        $type = (string)($input['type'] ?? 'direct');
        $withUserId = (int)($input['user_id'] ?? 0);
        $projectId = (int)($input['project_id'] ?? 0);
        $teamId = (int)($input['team_id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        $participantPublicIds = $input['participant_public_ids'] ?? [];

        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);

        $pdo = $this->container->get('db.pdo');
        /** @var ChatService $service */
        $service = $this->container->get('service.chat');

        if (!in_array($type, ['direct', 'project', 'team', 'group'], true)) {
            return $this->error('INVALID_TYPE', $this->t('chat/messages.unsupported_chat_type'), 422);
        }

        if ($type === 'direct') {
            if ($withUserId <= 0 || $withUserId === $userId) {
                return $this->error('INVALID_USER', $this->t('chat/messages.select_active_user'), 422);
            }

            $userStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND is_active = 1 AND deleted_at IS NULL");
            $userStmt->execute(['id' => $withUserId]);
            if (!$userStmt->fetchColumn()) {
                return $this->error('USER_NOT_FOUND', $this->t('chat/messages.user_not_found'), 404);
            }

            $chat = $service->ensureDirectChat($userId, $withUserId);
            return $this->success('CHAT_CREATED', $this->t('chat/messages.chat_ready'), ['public_id' => $chat['public_id'] ?? ''], status: 201);
        }

        if ($type === 'project' && $projectId > 0) {
            $stmt = $pdo->prepare("
                SELECT p.*, t.manager_user_id AS team_manager_user_id, t.member_user_ids AS team_member_user_ids
                FROM projects p
                LEFT JOIN teams t ON t.public_id = p.team_public_id
                WHERE p.id = :id AND p.archived_at IS NULL
            ");
            $stmt->execute(['id' => $projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($project)) return $this->error('PROJECT_NOT_FOUND', $this->t('chat/messages.project_not_found'), 404);
            $chat = $service->ensureProjectChat($project, $user);
            if (!$service->assertParticipant((int)($chat['id'] ?? 0), $userId)) return $this->error('FORBIDDEN', $this->t('chat/messages.not_participant'), 403);
            return $this->success('CHAT_CREATED', $this->t('chat/messages.chat_ready'), ['public_id' => $chat['public_id'] ?? ''], status: 201);
        }

        if ($type === 'team' && $teamId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = :id");
            $stmt->execute(['id' => $teamId]);
            $team = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($team)) return $this->error('TEAM_NOT_FOUND', $this->t('chat/messages.team_not_found'), 404);
            $chat = $service->ensureTeamChat($team, $user);
            if (!$service->assertParticipant((int)($chat['id'] ?? 0), $userId)) return $this->error('FORBIDDEN', $this->t('chat/messages.not_participant'), 403);
            return $this->success('CHAT_CREATED', $this->t('chat/messages.chat_ready'), ['public_id' => $chat['public_id'] ?? ''], status: 201);
        }

        if ($type === 'group') {
            if (!is_array($participantPublicIds)) {
                $participantPublicIds = [];
            }
            $participantPublicIds = array_values(array_unique(array_filter(array_map(
                static fn(mixed $id): string => trim((string)$id),
                $participantPublicIds
            ), static fn(string $id): bool => $id !== '')));
            if ($title === '' || count($participantPublicIds) === 0) {
                return $this->error('INVALID_PARAM', $this->t('chat/messages.title_participants_required'), 422);
            }
            if (mb_strlen($title) > 160) {
                return $this->error('TITLE_TOO_LONG', $this->t('chat/messages.title_too_long'), 422);
            }
            if (count($participantPublicIds) > 100) {
                return $this->error('TOO_MANY_PARTICIPANTS', $this->t('chat/messages.too_many_participants'), 422);
            }
            $chat = $service->ensureGroupChat($title, $participantPublicIds, $user);
            if ($chat === []) return $this->error('PARTICIPANTS_NOT_FOUND', $this->t('chat/messages.select_at_least_one'), 422);
            return $this->success('CHAT_CREATED', $this->t('chat/messages.chat_ready'), ['public_id' => $chat['public_id'] ?? ''], status: 201);
        }

        return $this->error('INVALID_PARAM', $this->t('chat/messages.entity_id_required'), 422);
    }

    public function messages(array $params = []): JsonResponse
    {
        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        if (!$chat) return $this->error('NOT_FOUND', $this->t('chat/messages.chat_not_found'), 404);

        $limit = min(100, max(1, (int)($this->request()->allInput()['limit'] ?? 50)));
        $beforeId = (int)($this->request()->allInput()['before_id'] ?? 0);
        $afterId = (int)($this->request()->allInput()['after_id'] ?? 0);
        $pdo = $this->container->get('db.pdo');
        $where = "cm.chat_id = :cid";
        if ($afterId > 0) {
            $where .= " AND cm.id > :aid";
            $order = 'ASC';
        } else {
            if ($beforeId > 0) $where .= " AND cm.id < :bid";
            $order = 'DESC';
        }

        $msgStmt = $pdo->prepare("
            SELECT cm.*, cm.id AS message_seq, u.full_name as sender_name, u.login as sender_login,
                   CASE WHEN cm.sender_user_id = :uid THEN 1 ELSE 0 END as is_own,
                   rm.public_id AS reply_public_id, rm.text AS reply_text,
                   ru.full_name AS reply_sender_name, ru.login AS reply_sender_login
            FROM chat_messages cm
            JOIN users u ON u.id = cm.sender_user_id
            LEFT JOIN chat_messages rm ON rm.id = cm.reply_to_message_id
            LEFT JOIN users ru ON ru.id = rm.sender_user_id
            WHERE {$where}
            ORDER BY cm.id {$order}
            LIMIT :lim
        ");
        $msgStmt->bindValue('cid', (int)$chat['id'], PDO::PARAM_INT);
        $msgStmt->bindValue('uid', $this->currentUserId(), PDO::PARAM_INT);
        if ($afterId > 0) {
            $msgStmt->bindValue('aid', $afterId, PDO::PARAM_INT);
        } elseif ($beforeId > 0) {
            $msgStmt->bindValue('bid', $beforeId, PDO::PARAM_INT);
        }
        $msgStmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $msgStmt->execute();
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($afterId <= 0) $messages = array_reverse($messages);
        $this->attachFiles($messages, (string)$chat['public_id']);

        return $this->success('MESSAGES_LIST', $this->t('common/messages.ok'), ['items' => $messages]);
    }

    public function sendMessage(array $params = []): JsonResponse
    {
        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        $input = $this->request()->allInput();
        $text = trim((string)($input['text'] ?? ''));
        if (!$chat || $text === '') return $this->error('INVALID_PARAM', $this->t('chat/messages.text_required'), 400);
        if (mb_strlen($text) > 4000) return $this->error('TEXT_TOO_LONG', $this->t('chat/messages.message_too_long'), 422);
        $messageType = (string)($input['message_type'] ?? 'text');
        if (!in_array($messageType, ['text'], true)) $messageType = 'text';

        $pdo = $this->container->get('db.pdo');
        $reply = $this->resolveReplyMessage((int)$chat['id'], (string)($input['reply_to_message_public_id'] ?? ''));
        $msgPublicId = 'msg_' . bin2hex(random_bytes(8));
        $pdo->prepare("
            INSERT INTO chat_messages (public_id, chat_id, sender_user_id, reply_to_message_id, message_type, text, created_at)
            VALUES (:pid, :cid, :uid, :reply_id, :type, :text, NOW())
        ")->execute([
            'pid' => $msgPublicId,
            'cid' => (int)$chat['id'],
            'uid' => $this->currentUserId(),
            'reply_id' => $reply ? (int)$reply['id'] : null,
            'type' => $messageType,
            'text' => $text,
        ]);

        $msgId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE chats SET last_message_at = NOW() WHERE id = :cid")->execute(['cid' => (int)$chat['id']]);

        /** @var ChatService $service */
        $service = $this->container->get('service.chat');
        $service->markRead((int)$chat['id'], $this->currentUserId());
        $priorityIds = $service->mentionedParticipantIds((int)$chat['id'], $text);
        if ($reply && (int)$reply['sender_user_id'] !== $this->currentUserId()) $priorityIds[] = (int)$reply['sender_user_id'];
        $service->notifyMessage($chat, ['public_id' => $msgPublicId, 'id' => $msgId, 'text' => $text], $this->user()['user'] ?? [], [
            'priority_user_ids' => $priorityIds,
            'reply_to_message_public_id' => $reply['public_id'] ?? null,
            'action_code' => $reply ? 'chat_message_replied' : 'chat_message_created',
            'title' => $reply ? $this->t('chat/messages.reply_to_message') : $this->t('chat/messages.new_chat_message'),
        ]);

        return $this->success('MESSAGE_SENT', $this->t('chat/messages.message_sent'), ['public_id' => $msgPublicId], status: 201);
    }

    public function editMessage(array $params = []): JsonResponse
    {
        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        $text = trim((string)($this->request()->allInput()['text'] ?? ''));
        if (!$chat || $text === '') return $this->error('INVALID_PARAM', $this->t('chat/messages.text_required'), 400);
        if (mb_strlen($text) > 4000) return $this->error('TEXT_TOO_LONG', $this->t('chat/messages.message_too_long'), 422);

        $message = $this->editableMessage((int)$chat['id'], (string)($params['message_public_id'] ?? ''));
        if (!$message) return $this->error('EDIT_FORBIDDEN', $this->t('chat/messages.cannot_edit'), 403);

        $pdo = $this->container->get('db.pdo');
        $pdo->prepare("UPDATE chat_messages SET text = :text, edited_at = NOW() WHERE id = :id")->execute(['text' => $text, 'id' => (int)$message['id']]);
        $this->auditMessage((int)$message['id'], (int)$chat['id'], 'edit', (string)$message['text'], $text);

        return $this->success('MESSAGE_EDITED', $this->t('chat/messages.message_edited'));
    }

    public function deleteMessage(array $params = []): JsonResponse
    {
        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        if (!$chat) return $this->error('NOT_FOUND', $this->t('chat/messages.chat_not_found'), 404);
        $message = $this->editableMessage((int)$chat['id'], (string)($params['message_public_id'] ?? ''));
        if (!$message) return $this->error('DELETE_FORBIDDEN', $this->t('chat/messages.cannot_delete'), 403);

        $pdo = $this->container->get('db.pdo');
        $pdo->prepare("UPDATE chat_messages SET deleted_at = NOW(), deleted_by_user_id = :uid WHERE id = :id")
            ->execute(['uid' => $this->currentUserId(), 'id' => (int)$message['id']]);
        $this->auditMessage((int)$message['id'], (int)$chat['id'], 'delete', (string)$message['text'], null);

        return $this->success('MESSAGE_DELETED', $this->t('chat/messages.message_deleted'));
    }

    public function uploadAttachment(array $params = []): JsonResponse
    {
        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        if (!$chat) return $this->error('NOT_FOUND', $this->t('chat/messages.chat_not_found'), 404);
        $file = $this->request()->files['file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return $this->error('FILE_REQUIRED', $this->t('chat/messages.file_required'), 400);
        if ((int)($file['size'] ?? 0) > 20 * 1024 * 1024) return $this->error('FILE_TOO_LARGE', $this->t('chat/messages.file_too_large'), 422);
        $fileValidation = $this->validateChatUpload($file);
        if ($fileValidation !== null) return $this->error($fileValidation['code'], $fileValidation['message'], 422);

        $pdo = $this->container->get('db.pdo');
        $msgPublicId = 'msg_' . bin2hex(random_bytes(8));
        $text = trim((string)($this->request()->allInput()['text'] ?? ''));
        if (mb_strlen($text) > 4000) return $this->error('TEXT_TOO_LONG', $this->t('chat/messages.message_too_long'), 422);
        $pdo->prepare("
            INSERT INTO chat_messages (public_id, chat_id, sender_user_id, message_type, text, created_at)
            VALUES (:pid, :cid, :uid, 'attachment', :text, NOW())
        ")->execute(['pid' => $msgPublicId, 'cid' => (int)$chat['id'], 'uid' => $this->currentUserId(), 'text' => $text]);
        $msgId = (int)$pdo->lastInsertId();

        $fileRow = $this->storeAttachment($msgPublicId, $file);
        $pdo->prepare("UPDATE chats SET last_message_at = NOW() WHERE id = :cid")->execute(['cid' => (int)$chat['id']]);

        /** @var ChatService $service */
        $service = $this->container->get('service.chat');
        $service->markRead((int)$chat['id'], $this->currentUserId());
        $service->notifyMessage($chat, ['public_id' => $msgPublicId, 'id' => $msgId, 'text' => $text !== '' ? $text : $this->t('chat/messages.attached_file') . ': ' . $fileRow['original_name']], $this->user()['user'] ?? []);

        return $this->success('ATTACHMENT_UPLOADED', $this->t('chat/messages.attachment_uploaded'), ['message_public_id' => $msgPublicId, 'file' => $fileRow], status: 201);
    }

    public function downloadAttachment(array $params = []): array
    {
        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        if (!$chat) return ['error' => 'FILE_NOT_FOUND'];
        $pdo = $this->container->get('db.pdo');
        $stmt = $pdo->prepare("
            SELECT f.*
            FROM files f
            JOIN chat_messages cm ON cm.public_id = f.entity_public_id
            WHERE f.public_id = :fid
              AND f.entity_type = 'chat_message'
              AND cm.chat_id = :cid
              AND f.is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute(['fid' => (string)($params['file_public_id'] ?? ''), 'cid' => (int)$chat['id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($file) || !is_file((string)($file['storage_path'] ?? ''))) return ['error' => 'FILE_NOT_FOUND'];

        return [
            'path' => (string)$file['storage_path'],
            'name' => (string)$file['original_name'],
            'mime' => (string)$file['mime_type'],
            'size' => (int)$file['size_bytes'],
        ];
    }

    public function markRead(array $params = []): JsonResponse
    {
        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        if (!$chat) return $this->error('NOT_FOUND', $this->t('chat/messages.chat_not_found'), 404);

        /** @var ChatService $service */
        $service = $this->container->get('service.chat');
        $service->markRead((int)$chat['id'], $this->currentUserId());

        return $this->success('CHAT_READ', $this->t('chat/messages.marked_read'));
    }

    public function unreadCount(): JsonResponse
    {
        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);

        try {
            $pdo = $this->container->get('db.pdo');
            $hasArchived = $this->tableHasColumn($pdo, 'chats', 'archived_at');
            $archivedFilter = $hasArchived ? 'AND c.archived_at IS NULL' : '';
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM chats c
                JOIN chat_participants cp ON cp.chat_id = c.id AND cp.user_id = :uid
                WHERE (SELECT COUNT(*) FROM chat_messages cm WHERE cm.chat_id = c.id AND cm.id > COALESCE((SELECT last_read_message_id FROM chat_read_markers WHERE chat_id = c.id AND user_id = :uid2), 0) AND cm.deleted_at IS NULL) > 0
                {$archivedFilter}
            ");
            $stmt->execute(['uid' => $userId, 'uid2' => $userId]);
            $count = (int)$stmt->fetchColumn();
            return $this->success('UNREAD_COUNT', $this->t('common/messages.ok'), ['count' => $count]);
        } catch (\Throwable $e) {
            return $this->error('ERROR', $this->t('common/messages.internal_error'), 500);
        }
    }

    public function archive(array $params): JsonResponse
    {
        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);

        $chat = $this->chatForCurrentUser((string)($params['public_id'] ?? ''));
        if (!is_array($chat)) return $this->error('CHAT_NOT_FOUND', $this->t('chat/messages.chat_not_found'), 404);
        if ((int)($chat['created_by_user_id'] ?? 0) !== $userId) return $this->error('NOT_ALLOWED', $this->t('chat/messages.only_creator_archive'), 422);

        $pdo = $this->container->get('db.pdo');
        if (!$this->tableHasColumn($pdo, 'chats', 'archived_at')) $this->ensureChatArchiveColumns($pdo);

        if (!$this->tableHasColumn($pdo, 'chats', 'archived_at')) return $this->error('NOT_AVAILABLE', $this->t('chat/messages.archive_unavailable'), 503);

        try {
            $stmt = $pdo->prepare("SELECT id FROM chats WHERE public_id = :pid");
            $stmt->execute(['pid' => $chat['public_id']]);
            $chatId = (int)$stmt->fetchColumn();
            if ($chatId <= 0) return $this->error('CHAT_NOT_FOUND', $this->t('chat/messages.chat_not_found'), 404);

            /** @var ChatService $service */
            $service = $this->container->get('service.chat');
            $result = $service->archiveChat($chatId, $userId);
            if ($result === []) return $this->error('ARCHIVE_FAILED', $this->t('chat/messages.archive_failed'), 500);
            return $this->success('CHAT_ARCHIVED', $this->t('common/messages.ok'), ['chat' => $result]);
        } catch (\Throwable $e) {
            return $this->error('ERROR', $this->t('common/messages.internal_error'), 500);
        }
    }

    public function restore(array $params): JsonResponse
    {
        $user = $this->user()['user'] ?? [];
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);

        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('MISSING_ID', $this->t('chat/messages.chat_id_required'), 422);

        $pdo = $this->container->get('db.pdo');
        if (!$this->tableHasColumn($pdo, 'chats', 'archived_at')) $this->ensureChatArchiveColumns($pdo);

        if (!$this->tableHasColumn($pdo, 'chats', 'archived_at')) return $this->error('NOT_AVAILABLE', $this->t('chat/messages.archive_unavailable'), 503);

        try {
            $stmt = $pdo->prepare("SELECT id FROM chats WHERE public_id = :pid AND archived_by_user_id = :uid AND archived_at IS NOT NULL");
            $stmt->execute(['pid' => $publicId, 'uid' => $userId]);
            $chatId = (int)$stmt->fetchColumn();
            if ($chatId <= 0) return $this->error('CHAT_NOT_FOUND', $this->t('chat/messages.chat_not_found'), 404);

            /** @var ChatService $service */
            $service = $this->container->get('service.chat');
            $result = $service->restoreChat($chatId, $userId);
            if ($result === []) return $this->error('RESTORE_FAILED', $this->t('chat/messages.restore_failed'), 500);
            return $this->success('CHAT_RESTORED', $this->t('common/messages.ok'), ['chat' => $result]);
        } catch (\Throwable $e) {
            return $this->error('ERROR', $this->t('common/messages.internal_error'), 500);
        }
    }

    private function participantsForChatArchived(array $chat): array
    {
        $archivedParticipants = $this->decodeArchivedParticipants($chat['archived_participant_ids'] ?? '[]');
        $rolesById = [];
        foreach ($archivedParticipants as $participant) {
            $rolesById[(int)$participant['user_id']] = (string)$participant['role'];
        }
        $ids = array_keys($rolesById);
        if ($ids === []) return [];
        $pdo = $this->container->get('db.pdo');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT u.id, u.public_id, u.login, u.full_name FROM users u WHERE u.id IN ({$in})");
        $stmt->execute($ids);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $item['role'] = $rolesById[(int)($item['id'] ?? 0)] ?? 'member';
        }
        unset($item);
        return $items;
    }

    private function chatForCurrentUser(string $publicId): ?array
    {
        $publicId = trim($publicId);
        $userId = $this->currentUserId();
        if ($publicId === '' || $userId <= 0) return null;

        $stmt = $this->container->get('db.pdo')->prepare("
            SELECT c.*, cp.is_favorite, cp.muted_until
            FROM chats c
            JOIN chat_participants cp ON cp.chat_id = c.id AND cp.user_id = :uid
            WHERE c.public_id = :pid
            LIMIT 1
        ");
        $stmt->execute(['pid' => $publicId, 'uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function archivedChatForCurrentUser(string $publicId): ?array
    {
        $publicId = trim($publicId);
        $userId = $this->currentUserId();
        if ($publicId === '' || $userId <= 0) return null;

        $stmt = $this->container->get('db.pdo')->prepare("
            SELECT c.*, 0 AS is_favorite, NULL AS muted_until
            FROM chats c
            WHERE c.public_id = :pid
              AND c.archived_by_user_id = :uid
              AND c.archived_at IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute(['pid' => $publicId, 'uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
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

    private function currentUserId(): int
    {
        return (int)(($this->user()['user'] ?? [])['id'] ?? 0);
    }

    private function participantsForChat(int $chatId): array
    {
        $stmt = $this->container->get('db.pdo')->prepare("
            SELECT u.id, u.public_id, u.full_name, u.login, u.email, cp.role, cp.joined_at
            FROM chat_participants cp
            JOIN users u ON u.id = cp.user_id
            WHERE cp.chat_id = :cid
            ORDER BY CASE cp.role WHEN 'admin' THEN 0 ELSE 1 END, COALESCE(NULLIF(u.full_name, ''), u.login)
        ");
        $stmt->execute(['cid' => $chatId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function resolveReplyMessage(int $chatId, string $publicId): ?array
    {
        if ($chatId <= 0 || trim($publicId) === '') return null;
        $stmt = $this->container->get('db.pdo')->prepare("SELECT * FROM chat_messages WHERE chat_id = :cid AND public_id = :pid AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['cid' => $chatId, 'pid' => trim($publicId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function editableMessage(int $chatId, string $messagePublicId): ?array
    {
        $stmt = $this->container->get('db.pdo')->prepare("
            SELECT *
            FROM chat_messages
            WHERE chat_id = :cid
              AND public_id = :pid
              AND sender_user_id = :uid
              AND deleted_at IS NULL
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            LIMIT 1
        ");
        $stmt->execute(['cid' => $chatId, 'pid' => $messagePublicId, 'uid' => $this->currentUserId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function auditMessage(int $messageId, int $chatId, string $action, ?string $before, ?string $after): void
    {
        $this->container->get('db.pdo')->prepare("
            INSERT INTO chat_message_audit_logs (public_id, message_id, chat_id, actor_user_id, action, before_text, after_text, created_at)
            VALUES (:pid, :mid, :cid, :uid, :action, :before_text, :after_text, NOW())
        ")->execute([
            'pid' => 'cma_' . bin2hex(random_bytes(8)),
            'mid' => $messageId,
            'cid' => $chatId,
            'uid' => $this->currentUserId(),
            'action' => $action,
            'before_text' => $before,
            'after_text' => $after,
        ]);
    }

    private function attachFiles(array &$messages, string $chatPublicId): void
    {
        $ids = array_values(array_filter(array_map(static fn(array $message): string => (string)($message['public_id'] ?? ''), $messages)));
        if ($ids === []) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->container->get('db.pdo')->prepare("SELECT public_id, entity_public_id, original_name, mime_type, size_bytes, created_at FROM files WHERE entity_type = 'chat_message' AND is_deleted = 0 AND entity_public_id IN ({$placeholders}) ORDER BY id ASC");
        $stmt->execute($ids);
        $byMessage = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $file) {
            $file['download_url'] = '/api/index.php?route=api/v1/chats/' . rawurlencode($chatPublicId) . '/attachments/' . rawurlencode((string)$file['public_id']) . '/download';
            $byMessage[(string)$file['entity_public_id']][] = $file;
        }
        foreach ($messages as &$message) {
            $message['attachments'] = $byMessage[(string)($message['public_id'] ?? '')] ?? [];
        }
    }

    private function storeAttachment(string $messagePublicId, array $raw): array
    {
        $publicId = 'fil_' . bin2hex(random_bytes(8));
        $name = $this->sanitizeFileName((string)($raw['name'] ?? 'file.bin'));
        $tmp = (string)($raw['tmp_name'] ?? '');
        $mime = $this->normalizeUploadMime($this->detectMime($tmp) ?: (string)($raw['type'] ?? 'application/octet-stream'), $name);
        $size = (int)($raw['size'] ?? 0);
        $dir = dirname(__DIR__, 3) . '/storage_api/uploads/chat';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = $dir . '/' . $publicId . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        if (!move_uploaded_file($tmp, $path)) throw new \RuntimeException('UPLOAD_MOVE_FAILED');

        $this->container->get('db.pdo')->prepare("
            INSERT INTO files (public_id, entity_type, entity_public_id, uploader_user_id, original_name, storage_path, mime_type, size_bytes, is_deleted, created_at)
            VALUES (:pid, 'chat_message', :entity_pid, :uid, :name, :path, :mime, :size, 0, NOW())
        ")->execute([
            'pid' => $publicId,
            'entity_pid' => $messagePublicId,
            'uid' => $this->currentUserId(),
            'name' => $name,
            'path' => $path,
            'mime' => $mime,
            'size' => $size,
        ]);

        return [
            'public_id' => $publicId,
            'original_name' => $name,
            'mime_type' => $mime,
            'size_bytes' => $size,
        ];
    }

    /**
     * @return array{code:string,message:string}|null
     */
    private function validateChatUpload(array $raw): ?array
    {
        $name = $this->sanitizeFileName((string)($raw['name'] ?? 'file.bin'));
        $tmp = (string)($raw['tmp_name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $this->normalizeUploadMime($this->detectMime($tmp) ?: (string)($raw['type'] ?? 'application/octet-stream'), $name);

        $blockedExt = ['svg', 'html', 'htm', 'js', 'mjs', 'php', 'phtml', 'phar', 'exe', 'dll', 'bat', 'cmd', 'sh', 'ps1', 'jar', 'com', 'scr'];
        if ($ext === '' || in_array($ext, $blockedExt, true)) {
            return ['code' => 'FILE_TYPE_NOT_ALLOWED', 'message' => $this->t('chat/messages.file_type_not_allowed')];
        }

        $allowed = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'csv' => ['text/plain', 'text/csv', 'application/csv'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
        ];

        if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) {
            return ['code' => 'FILE_TYPE_NOT_ALLOWED', 'message' => $this->t('chat/messages.file_type_not_allowed')];
        }

        return null;
    }

    private function normalizeUploadMime(string $mime, string $fileName): string
    {
        $mime = strtolower(trim(explode(';', $mime)[0]));
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($mime === 'image/pjpeg') return 'image/jpeg';
        if ($mime === 'text/comma-separated-values') return 'text/csv';
        if ($mime === 'application/x-zip') return 'application/zip';
        if ($mime === 'application/octet-stream' && in_array($ext, ['doc', 'xls', 'ppt'], true)) return $mime;
        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    private function sanitizeFileName(string $name): string
    {
        $name = trim(basename(str_replace('\\', '/', $name)));
        $name = preg_replace('/[\x00-\x1F\x7F]+/', '', $name) ?? '';
        return $name !== '' ? mb_substr($name, 0, 180) : 'file.bin';
    }

    private function detectMime(string $path): string
    {
        if ($path === '' || !is_file($path) || !function_exists('finfo_open')) return '';
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) return '';
        $mime = finfo_file($finfo, $path);
        return is_string($mime) ? $mime : '';
    }
}
