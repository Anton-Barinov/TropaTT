<?php
declare(strict_types=1);

namespace Module\Crm\TelegramBot\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\TelegramBot\Service\TelegramBotService;
use Throwable;

final class TelegramBotApiController extends BaseController
{
    private function getService(): TelegramBotService
    {
        return new TelegramBotService($this->container->get('db.pdo'));
    }

    private function getOrganizationId(): int
    {
        $explicitOrg = (int)($this->request()->input('organization_id') ?? $this->request()->query('organization_id') ?? 0);
        if ($explicitOrg > 0) {
            return $explicitOrg;
        }

        $auth = $this->user();
        $orgId = (int)($auth['user']['organization_id'] ?? 0);
        if ($orgId > 0) {
            return $orgId;
        }

        $hdr = (string)($this->request()->header('X-Organization-Id') ?? $this->request()->header('X-Organization-Public-Id') ?? '');
        if ($hdr !== '' && is_numeric($hdr)) {
            return (int)$hdr;
        }

        $userId = (int)($auth['user']['id'] ?? 0);
        if ($userId > 0) {
            try {
                $stmt = $this->container->get('db.pdo')->prepare(
                    "SELECT organization_id FROM organization_memberships WHERE user_id = :uid ORDER BY id ASC LIMIT 1"
                );
                $stmt->execute([':uid' => $userId]);
                $userOrg = (int)$stmt->fetchColumn();
                if ($userOrg > 0) {
                    return $userOrg;
                }
            } catch (Throwable) {}
        }

        try {
            $anyStmt = $this->container->get('db.pdo')->query(
                "SELECT organization_id FROM crm_telegram_configs WHERE is_active = 1 LIMIT 1"
            );
            $anyOrg = $anyStmt ? (int)$anyStmt->fetchColumn() : 0;
            if ($anyOrg > 0) {
                return $anyOrg;
            }
        } catch (Throwable) {}

        try {
            $primaryOrgStmt = $this->container->get('db.pdo')->query(
                "SELECT id FROM organizations ORDER BY id ASC LIMIT 1"
            );
            $primaryOrg = $primaryOrgStmt ? (int)$primaryOrgStmt->fetchColumn() : 0;
            if ($primaryOrg > 0) {
                return $primaryOrg;
            }
        } catch (Throwable) {}

        return 1;
    }

    /**
     * Get operational status and configuration overview.
     */
    public function getStatus(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        try {
            $service = $this->getService();
            $status = $service->getStatus($this->getOrganizationId());
            return $this->success('TELEGRAM_STATUS', 'Status retrieved', $status);
        } catch (Throwable $e) {
            return $this->error('STATUS_ERROR', 'Failed to retrieve telegram status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Save/Update bot configuration.
     */
    public function saveConfig(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $botToken = trim((string)$this->request()->input('bot_token', ''));
        $webhookSecretToken = trim((string)$this->request()->input('webhook_secret_token', ''));
        $botUsername = trim((string)$this->request()->input('bot_username', ''));
        $autoCreateIntake = (bool)$this->request()->input('auto_create_intake', true);
        $welcomeMessage = (string)$this->request()->input('welcome_message', '');
        $webhookUrl = trim((string)$this->request()->input('webhook_url', ''));

        try {
            $service = $this->getService();
            $pubId = $service->saveConfig(
                $this->getOrganizationId(),
                $botToken,
                $webhookSecretToken,
                $botUsername ?: null,
                $autoCreateIntake,
                $welcomeMessage ?: null,
                null,
                null,
                $webhookUrl ?: null
            );

            $status = $service->getStatus($this->getOrganizationId());
            return $this->success('TELEGRAM_CONFIG_SAVED', 'Конфигурация бота сохранена', [
                'public_id' => $pubId,
                'status' => $status,
            ]);
        } catch (Throwable $e) {
            return $this->error('SAVE_CONFIG_ERROR', $e->getMessage(), 400);
        }
    }

    /**
     * Test connection to Telegram API via getMe.
     */
    public function testConnection(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $token = trim((string)$this->request()->input('bot_token', ''));
        if (str_contains($token, '••••') || str_starts_with($token, 'gcm:') || str_starts_with($token, 'b64:')) {
            $token = '';
        }

        try {
            $service = $this->getService();
            $result = $service->testConnection($token ?: null, $this->getOrganizationId());
            if (!empty($result['ok'])) {
                return $this->success('TELEGRAM_CONNECTED', 'Связь с Telegram API успешно подтверждена', $result);
            }
            return $this->error('TELEGRAM_CONNECT_FAILED', $result['error'] ?? 'Не удалось подключиться к Telegram', 400);
        } catch (Throwable $e) {
            return $this->error('TEST_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Set Webhook URL in Telegram.
     */
    public function setWebhook(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $webhookUrl = trim((string)$this->request()->input('webhook_url', ''));
        $secret = trim((string)$this->request()->input('webhook_secret_token', ''));

        if ($webhookUrl === '') {
            return $this->error('INVALID_PARAM', 'Webhook URL обязателен для заполнения', 400);
        }

        try {
            $service = $this->getService();
            $res = $service->setWebhook($this->getOrganizationId(), $webhookUrl, $secret ?: null);
            if (!empty($res['ok'])) {
                return $this->success('WEBHOOK_SET', 'Вебхук Telegram успешно зарегистрирован', $res);
            }
            return $this->error('WEBHOOK_ERROR', (string)($res['description'] ?? 'Ошибка установки вебхука'), 400);
        } catch (Throwable $e) {
            return $this->error('WEBHOOK_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Delete Webhook in Telegram.
     */
    public function deleteWebhook(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        try {
            $service = $this->getService();
            $res = $service->deleteWebhook($this->getOrganizationId());
            if (!empty($res['ok'])) {
                return $this->success('WEBHOOK_DELETED', 'Вебхук Telegram успешно удалён (режим polling)', $res);
            }
            return $this->error('WEBHOOK_ERROR', (string)($res['description'] ?? 'Ошибка удаления вебхука'), 400);
        } catch (Throwable $e) {
            return $this->error('WEBHOOK_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Poll updates manually (for shared hosting or local testing).
     */
    public function pollUpdates(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $limit = (int)$this->request()->input('limit', 10);

        try {
            $service = $this->getService();
            $res = $service->pollUpdates($this->getOrganizationId(), $limit);
            if (!empty($res['ok'])) {
                return $this->success('UPDATES_POLLED', "Обработано {$res['processed_count']} новых обновлений", $res);
            }
            return $this->error('POLL_ERROR', $res['error'] ?? 'Ошибка опроса Telegram', 400);
        } catch (Throwable $e) {
            return $this->error('POLL_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Send test message to a chat or bound user.
     */
    public function sendTestMessage(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $chatId = trim((string)$this->request()->input('chat_id', ''));
        $text = trim((string)($this->request()->input('text') ?: $this->request()->input('message', '')));

        if ($chatId === '' || $text === '') {
            return $this->error('INVALID_PARAM', 'Chat ID и текст сообщения обязательны', 400);
        }

        try {
            $service = $this->getService();
            $res = $service->sendMessage($this->getOrganizationId(), $chatId, $text);
            if (!empty($res['ok'])) {
                return $this->success('MESSAGE_SENT', 'Сообщение успешно отправлено в Telegram', $res);
            }
            return $this->error('SEND_FAILED', (string)($res['description'] ?? 'Не удалось отправить сообщение'), 400);
        } catch (Throwable $e) {
            return $this->error('SEND_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Import Telegram credentials from .env.
     */
    public function importEnv(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        try {
            $service = $this->getService();
            $status = $service->importFromEnv($this->getOrganizationId());
            return $this->success('ENV_IMPORTED', 'Параметры Telegram успешно импортированы из .env', $status);
        } catch (Throwable $e) {
            return $this->error('IMPORT_ERROR', $e->getMessage(), 400);
        }
    }

    /**
     * List bound staff users.
     */
    public function listBindings(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        try {
            $service = $this->getService();
            $items = $service->listBindings($this->getOrganizationId());
            return $this->success('BINDINGS_LIST', 'Список привязанных сотрудников получен', ['items' => $items]);
        } catch (Throwable $e) {
            return $this->error('LIST_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Delete a staff user binding.
     *
     * @param array<string, mixed> $params
     */
    public function deleteBinding(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $pubId = (string)($params['id'] ?? $this->request()->input('public_id', ''));
        if ($pubId === '') {
            return $this->error('INVALID_PARAM', 'Public ID привязки обязателен', 400);
        }

        try {
            $service = $this->getService();
            $ok = $service->deleteBinding($this->getOrganizationId(), $pubId);
            if ($ok) {
                return $this->success('BINDING_DELETED', 'Привязка сотрудника удалена');
            }
            return $this->error('NOT_FOUND', 'Привязка не найдена', 404);
        } catch (Throwable $e) {
            return $this->error('DELETE_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Get recent incoming updates log.
     */
    public function getRecentUpdates(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $limit = (int)$this->request()->input('limit', 15);

        try {
            $service = $this->getService();
            $items = $service->getRecentUpdates($this->getOrganizationId(), $limit);
            return $this->success('UPDATES_LOG', 'Журнал обновлений получен', ['items' => $items]);
        } catch (Throwable $e) {
            return $this->error('LOG_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Generate binding code for current staff member.
     */
    public function requestBinding(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $orgId = $this->getOrganizationId();
        $userPubId = (string)($auth['user']['public_id'] ?? 'usr_current');

        try {
            $service = $this->getService();
            $code = $service->generateBindingCode($orgId, $userPubId);
            return $this->success('TELEGRAM_BINDING_CODE', 'Код привязки сгенерирован', ['verification_code' => $code]);
        } catch (Throwable $e) {
            return $this->error('BIND_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Webhook receiver endpoint from Telegram servers.
     *
     * @param array<string, mixed> $params
     */
    public function handleWebhook(array $params = []): JsonResponse
    {
        $orgId = (int)($params['org_id'] ?? 0);
        if ($orgId <= 0) {
            $orgId = $this->getOrganizationId();
        }
        $secretToken = (string)($this->request()->header('X-Telegram-Bot-Api-Secret-Token') ?? '');
        $update = $this->request()->allInput();

        if (empty($update)) {
            // Check raw input
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $update = $decoded;
                }
            }
        }

        $service = $this->getService();
        try {
            $result = $service->handleUpdate($orgId, $secretToken, $update);
            return $this->success('TELEGRAM_UPDATE_HANDLED', 'Update processed', $result);
        } catch (Throwable $e) {
            return $this->error('TELEGRAM_ERROR', $e->getMessage(), 400);
        }
    }

    /**
     * List accessible organizations for current user.
     */
    public function listOrganizations(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        try {
            $service = $this->getService();
            $userId = (int)($auth['user']['id'] ?? 0);
            $items = $service->listOrganizationsForUser($userId);
            return $this->success('ORGANIZATIONS_LIST', 'Список организаций получен', [
                'items' => $items,
                'active_organization_id' => $this->getOrganizationId(),
            ]);
        } catch (Throwable $e) {
            return $this->error('ORG_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * List projects for active organization.
     */
    public function listProjects(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        try {
            $service = $this->getService();
            $items = $service->listProjectsForOrg($this->getOrganizationId());
            return $this->success('PROJECTS_LIST', 'Список проектов получен', ['items' => $items]);
        } catch (Throwable $e) {
            return $this->error('PROJECTS_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * List dialogs for active organization.
     */
    public function listDialogs(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        $status = (string)($this->request()->input('status') ?? $this->request()->query('status') ?? 'all');
        $search = (string)($this->request()->input('search') ?? $this->request()->query('search') ?? '');
        $limit = (int)($this->request()->input('limit') ?? $this->request()->query('limit') ?? 50);

        try {
            $service = $this->getService();
            $res = $service->listDialogs($this->getOrganizationId(), $status, $search, $limit);
            return $this->success('TELEGRAM_DIALOGS', 'Список диалогов получен', [
                'items' => $res['dialogs'] ?? [],
                'total_unread' => $res['total_unread'] ?? 0,
            ]);
        } catch (Throwable $e) {
            return $this->error('DIALOGS_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Get single dialog and its messages.
     *
     * @param array<string, mixed> $params
     */
    public function getDialog(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        $dialogId = (string)($params['id'] ?? $this->request()->input('dialog_id') ?? $this->request()->query('dialog_id') ?? '');
        if ($dialogId === '') {
            return $this->error('INVALID_PARAM', 'ID диалога обязателен', 400);
        }
        try {
            $service = $this->getService();
            $dialog = $service->getDialog($this->getOrganizationId(), $dialogId);
            if (!$dialog) {
                return $this->error('NOT_FOUND', 'Диалог не найден', 404);
            }
            return $this->success('TELEGRAM_DIALOG', 'Диалог получен', [
                'dialog' => $dialog,
                'messages' => $dialog['messages'] ?? [],
            ]);
        } catch (Throwable $e) {
            return $this->error('DIALOG_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Reply to client in dialog.
     *
     * @param array<string, mixed> $params
     */
    public function replyDialog(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        $dialogId = (string)($params['id'] ?? $this->request()->input('dialog_id', ''));
        $text = trim((string)$this->request()->input('text', ''));
        if ($dialogId === '' || $text === '') {
            return $this->error('INVALID_PARAM', 'ID диалога и текст ответа обязательны', 400);
        }
        $user = $auth['user'] ?? [];
        $staffUserId = (int)($user['id'] ?? 0);
        $staffName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if ($staffName === '') {
            $staffName = (string)($user['username'] ?? 'Менеджер CRM');
        }

        try {
            $service = $this->getService();
            $res = $service->replyDialog($this->getOrganizationId(), $dialogId, $text, $staffUserId, $staffName);
            return $this->success('REPLY_SENT', 'Ответ отправлен в Telegram', $res);
        } catch (Throwable $e) {
            return $this->error('REPLY_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Update dialog status.
     *
     * @param array<string, mixed> $params
     */
    public function updateDialogStatus(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        $dialogId = (string)($params['id'] ?? $this->request()->input('dialog_id', ''));
        $status = (string)$this->request()->input('status', 'open');
        try {
            $service = $this->getService();
            $ok = $service->updateDialogStatus($this->getOrganizationId(), $dialogId, $status);
            if (!$ok) {
                return $this->error('NOT_FOUND', 'Диалог не найден', 404);
            }
            return $this->success('STATUS_UPDATED', 'Статус диалога обновлен');
        } catch (Throwable $e) {
            return $this->error('STATUS_ERROR', $e->getMessage(), 500);
        }
    }

    /**
     * Create CRM task from dialog.
     *
     * @param array<string, mixed> $params
     */
    public function createTaskFromDialog(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        $dialogId = (string)($params['id'] ?? $this->request()->input('dialog_id', ''));
        $title = trim((string)$this->request()->input('title', ''));
        $description = trim((string)$this->request()->input('description', ''));
        $projectId = (int)$this->request()->input('project_id', 0);
        $priority = (string)$this->request()->input('priority', 'medium');

        if ($dialogId === '') {
            return $this->error('INVALID_PARAM', 'ID диалога обязателен', 400);
        }

        $user = $auth['user'] ?? [];
        $staffUserId = (int)($user['id'] ?? 0);

        try {
            $service = $this->getService();
            $res = $service->createTaskFromDialog($this->getOrganizationId(), $dialogId, $title, $description, $projectId ?: null, $priority, null, $staffUserId);
            return $this->success('TASK_CREATED', 'Задача создана в CRM', $res);
        } catch (Throwable $e) {
            return $this->error('TASK_ERROR', $e->getMessage(), 500);
        }
    }
}
