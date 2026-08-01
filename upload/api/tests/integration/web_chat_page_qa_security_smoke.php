<?php
declare(strict_types=1);

function failChatPageSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] web_chat_page_qa_security_smoke: {$message}\n");
    exit(1);
}

function assertChatContains(string $haystack, string $needle, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        failChatPageSmoke($message . ' (needle: ' . $needle . ')');
    }
}

function assertChatNotContains(string $haystack, string $needle, string $message): void
{
    if (str_contains($haystack, $needle)) {
        failChatPageSmoke($message . ' (needle: ' . $needle . ')');
    }
}

$root = dirname(__DIR__, 3);
$chatPage = file_get_contents($root . '/web/view/template/page/chat.php');
$controller = file_get_contents($root . '/api/controller/chat/ChatController.php');

if ($chatPage === false || $controller === false) {
    failChatPageSmoke('unable to read chat page or controller');
}

assertChatNotContains($chatPage, 'window.confirm', 'chat page must use CRM confirm modal, not native browser confirm');
assertChatContains($chatPage, 'function showConfirmModal', 'chat page must provide styled confirmation modal');
assertChatContains($chatPage, 'syncMessagesAfterLocalChange', 'chat page must update message list without full conversation reload after local actions');
assertChatContains($chatPage, 'aria-label="Создать чат"', 'new chat button must be accessible');
assertChatContains($chatPage, 'aria-label="Открыть чат: ', 'chat list items must expose accessible labels');
assertChatContains($chatPage, 'aria-label="Открыть изображение: ', 'image attachments must expose accessible preview labels');
assertChatContains($chatPage, 'aria-expanded="false" aria-controls="emojiPicker"', 'emoji picker must expose expanded state');
assertChatContains($controller, '/api/index.php?route=api/v1/chats/', 'chat attachment URLs must use authenticated API route');
assertChatContains($controller, 'JOIN chat_messages cm ON cm.public_id = f.entity_public_id', 'attachment download must be bound to a chat message');
assertChatContains($controller, 'cm.chat_id = :cid', 'attachment download must be scoped to the current chat');
assertChatContains($controller, "created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)", 'edit/delete window must be enforced server-side');
assertChatContains($controller, 'validateChatUpload', 'chat uploads must validate file type and MIME');

fwrite(STDOUT, "[OK] web_chat_page_qa_security_smoke\n");

