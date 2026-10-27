<?php
declare(strict_types=1);

namespace Module\Crm\CorporateMail\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\CorporateMail\Service\CorporateMailService;
use Throwable;

final class CorporateMailApiController extends BaseController
{
    private function getService(): CorporateMailService
    {
        return new CorporateMailService($this->container->get('db.pdo'));
    }

    private function getOrganizationId(): int
    {
        $auth = $this->user();
        $organizationId = (int)($auth['user']['organization_id'] ?? 0);
        if ($organizationId <= 0) {
            throw new \RuntimeException('An active workspace is required for corporate mail.');
        }
        return $organizationId;
    }

    /**
     * Get module connectivity and configuration status.
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
            return $this->success('MAIL_STATUS', 'Status retrieved', $status);
        } catch (Throwable $e) {
            return $this->error('STATUS_ERROR', 'Failed to retrieve mail status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List all mailboxes for the current organization.
     */
    public function listMailboxes(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        try {
            $service = $this->getService();
            $items = $service->listMailboxes($this->getOrganizationId());
            return $this->success('MAILBOXES_LIST', 'Mailboxes retrieved', ['items' => $items]);
        } catch (Throwable $e) {
            return $this->error('LIST_ERROR', 'Failed to list mailboxes: ' . $e->getMessage(), 500);
        }
    }

    /** @param array<string, mixed> $params */
    public function listThreads(array $params = []): JsonResponse
    {
        if (!$this->user()) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        try {
            $items = $this->getService()->listThreads(
                $this->getOrganizationId(),
                trim((string)($params['public_id'] ?? '')),
                (int)$this->request()->input('limit', 100)
            );
            return $this->success('MAIL_THREADS_LIST', 'Mail conversations retrieved', ['items' => $items]);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'MAILBOX_NOT_FOUND') return $this->error('NOT_FOUND', 'Mailbox not found', 404);
            return $this->error('THREADS_ERROR', 'Could not retrieve mail conversations', 500);
        }
    }

    /** @param array<string, mixed> $params */
    public function listMessages(array $params = []): JsonResponse
    {
        if (!$this->user()) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        $threadId = $this->request()->input('thread_id', '');
        if (!is_string($threadId) || trim($threadId) === '' || strlen($threadId) > 128) {
            return $this->error('VALIDATION_ERROR', 'A valid conversation id is required', 422);
        }
        try {
            $items = $this->getService()->listMessages(
                $this->getOrganizationId(),
                trim((string)($params['public_id'] ?? '')),
                $threadId,
                (int)$this->request()->input('limit', 100)
            );
            return $this->success('MAIL_MESSAGES_LIST', 'Conversation messages retrieved', ['items' => $items]);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'MAILBOX_NOT_FOUND') return $this->error('NOT_FOUND', 'Mailbox not found', 404);
            return $this->error('MESSAGES_ERROR', 'Could not retrieve conversation messages', 500);
        }
    }

    /**
     * Get single mailbox details.
     *
     * @param array<string, mixed> $params
     */
    public function getMailbox(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $publicId = (string)($params['public_id'] ?? $this->request()->input('public_id', ''));
        if ($publicId === '') {
            return $this->error('INVALID_PARAM', 'Public ID is required', 400);
        }

        try {
            $service = $this->getService();
            $mailbox = $service->getMailbox($this->getOrganizationId(), $publicId);
            if (!$mailbox) {
                return $this->error('NOT_FOUND', 'Mailbox not found', 404);
            }
            return $this->success('MAILBOX_DETAIL', 'Mailbox retrieved', $mailbox);
        } catch (Throwable $e) {
            return $this->error('GET_ERROR', 'Failed to get mailbox: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create or update a mailbox.
     */
    public function saveMailbox(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $input = $this->request()->allInput();
        $email = trim((string)($input['email_address'] ?? ($input['email'] ?? '')));
        $name = trim((string)($input['display_name'] ?? ($input['name'] ?? '')));
        $adapter = trim((string)($input['protocol_adapter'] ?? 'imap_smtp'));
        $publicId = !empty($input['public_id']) ? trim((string)$input['public_id']) : null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('VALIDATION_ERROR', 'Укажите корректный email адрес ящика', 422);
        }
        if ($name === '') {
            $name = $email;
        }

        // Build auth config from nested or flat input
        $authConfig = $input['auth_config'] ?? [];
        if (!is_array($authConfig)) {
            $authConfig = [];
        }

        // Support flat parameters from UI forms
        if (!isset($authConfig['smtp'])) {
            $authConfig['smtp'] = [
                'host' => (string)($input['smtp_host'] ?? ''),
                'port' => (int)($input['smtp_port'] ?? 587),
                'username' => (string)($input['smtp_username'] ?? $email),
                'password' => (string)($input['smtp_password'] ?? ''),
                'encryption' => (string)($input['smtp_encryption'] ?? 'tls'),
                'from_address' => (string)($input['smtp_from'] ?? $email),
            ];
        }
        if (!isset($authConfig['imap'])) {
            $authConfig['imap'] = [
                'host' => (string)($input['imap_host'] ?? ''),
                'port' => (int)($input['imap_port'] ?? 993),
                'username' => (string)($input['imap_username'] ?? $email),
                'password' => (string)($input['imap_password'] ?? ''),
                'encryption' => (string)($input['imap_encryption'] ?? 'ssl'),
            ];
        }

        try {
            $service = $this->getService();
            $savedPubId = $service->saveMailbox($this->getOrganizationId(), $email, $name, $adapter, $authConfig, $publicId);
            return $this->success('MAILBOX_SAVED', 'Почтовый ящик успешно сохранён', ['public_id' => $savedPubId]);
        } catch (Throwable $e) {
            return $this->error('SAVE_ERROR', 'Не удалось сохранить ящик: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete mailbox.
     *
     * @param array<string, mixed> $params
     */
    public function deleteMailbox(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $publicId = (string)($params['public_id'] ?? $this->request()->input('public_id', ''));
        if ($publicId === '') {
            return $this->error('INVALID_PARAM', 'Public ID is required', 400);
        }

        try {
            $service = $this->getService();
            $deleted = $service->deleteMailbox($this->getOrganizationId(), $publicId);
            if (!$deleted) {
                return $this->error('NOT_FOUND', 'Mailbox not found or already deleted', 404);
            }
            return $this->success('MAILBOX_DELETED', 'Почтовый ящик удалён');
        } catch (Throwable $e) {
            return $this->error('DELETE_ERROR', 'Не удалось удалить ящик: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test connection for an existing mailbox.
     *
     * @param array<string, mixed> $params
     */
    public function testMailbox(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $publicId = (string)($params['public_id'] ?? $this->request()->input('public_id', ''));
        if ($publicId === '') {
            return $this->error('INVALID_PARAM', 'Public ID is required', 400);
        }

        try {
            $service = $this->getService();
            $mailbox = $service->getMailbox($this->getOrganizationId(), $publicId);
            if (!$mailbox) {
                return $this->error('NOT_FOUND', 'Mailbox not found', 404);
            }

            // Test connection using mailbox's stored config
            $testRes = $service->testConnection($mailbox['auth_config'] ?? []);
            return $this->success('CONNECTION_TEST', $testRes['message'], $testRes);
        } catch (Throwable $e) {
            return $this->error('TEST_ERROR', 'Ошибка проверки соединения: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test connection with ad-hoc parameters from UI form.
     */
    public function testConnection(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $input = $this->request()->allInput();
        try {
            $service = $this->getService();
            $testRes = $service->testConnection($input);
            return $this->success('CONNECTION_TEST', $testRes['message'], $testRes);
        } catch (Throwable $e) {
            return $this->error('TEST_ERROR', 'Ошибка проверки соединения: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Trigger immediate mailbox sync.
     *
     * @param array<string, mixed> $params
     */
    public function syncMailbox(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $publicId = (string)($params['public_id'] ?? $this->request()->input('public_id', ''));
        if ($publicId === '') {
            return $this->error('INVALID_PARAM', 'Public ID is required', 400);
        }

        try {
            $service = $this->getService();
            $syncRes = $service->syncMailbox($this->getOrganizationId(), $publicId);
            if (empty($syncRes['ok'])) {
                return $this->error('SYNC_FAILED', (string)($syncRes['message'] ?? 'Синхронизация не выполнена.'), 502, $syncRes);
            }
            return $this->success('MAILBOX_SYNCED', $syncRes['message'], $syncRes);
        } catch (Throwable $e) {
            return $this->error('SYNC_ERROR', 'Ошибка синхронизации. Проверьте настройки ящика и повторите попытку.', 502);
        }
    }

    /** List failed IMAP UIDs for a mailbox in the active workspace. */
    public function listSyncFailures(array $params = []): JsonResponse
    {
        if (!$this->user()) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        $publicId = (string)($params['public_id'] ?? '');
        if ($publicId === '') return $this->error('INVALID_PARAM', 'Public ID is required', 400);
        try {
            $items = $this->getService()->listImapFailures($this->getOrganizationId(), $publicId);
            return $this->success('MAIL_SYNC_FAILURES', 'Ошибки синхронизации получены', ['items' => $items]);
        } catch (Throwable $e) {
            return $this->error('SYNC_FAILURES_ERROR', 'Не удалось получить ошибки синхронизации.', 500);
        }
    }

    /** Retry one explicitly recorded UID using a fresh IMAP session. */
    public function retrySyncFailure(array $params = []): JsonResponse
    {
        if (!$this->user()) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        $publicId = (string)($params['public_id'] ?? '');
        $input = $this->request()->allInput();
        $uid = filter_var($input['uid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $validity = filter_var($input['uidvalidity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($publicId === '' || $uid === false || $validity === false) return $this->error('INVALID_PARAM', 'Mailbox, UID and UIDVALIDITY are required', 400);
        try {
            $result = $this->getService()->retryImapFailure($this->getOrganizationId(), $publicId, (int)$validity, (int)$uid);
            if (empty($result['ok'])) return $this->error('UID_RETRY_FAILED', $result['message'], 502, $result);
            return $this->success('UID_RETRIED', $result['message'], $result);
        } catch (Throwable $e) {
            return $this->error('UID_RETRY_FAILED', 'Не удалось повторить обработку UID. Проверьте актуальность синхронизации.', 409);
        }
    }

    /**
     * Import default mailbox from environment variables.
     */
    public function importEnv(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        try {
            $service = $this->getService();
            $pubId = $service->importFromEnv($this->getOrganizationId());
            return $this->success('ENV_IMPORTED', 'Почтовый ящик успешно импортирован из .env', ['public_id' => $pubId]);
        } catch (Throwable $e) {
            return $this->error('IMPORT_ERROR', 'Не удалось импортировать из .env: ' . $e->getMessage(), 400);
        }
    }

    /** @param array<string, mixed> $params */
    public function sendMessage(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        try {
            $input = $this->request()->allInput();
            $recipients = $input['to'] ?? ($input['recipients'] ?? []);
            if (is_string($recipients)) {
                $recipients = preg_split('/[,;\s]+/', $recipients) ?: [];
            }
            if (!is_array($recipients)) {
                $recipients = [];
            }
            $rawIdempotencyKey = $input['idempotency_key'] ?? null;
            $idempotencyKey = is_string($rawIdempotencyKey) ? trim($rawIdempotencyKey) : '';
            if ($idempotencyKey === '') $idempotencyKey = (string)($this->request()->header('X-TropaTT-Idempotency-Key') ?? '');
            $result = $this->getService()->sendMessage(
                $this->getOrganizationId(),
                trim((string)($params['public_id'] ?? $input['public_id'] ?? '')),
                array_values(array_map('strval', $recipients)),
                (string)($input['subject'] ?? ''),
                (string)($input['body'] ?? ''),
                !empty($input['in_reply_to']) ? (string)$input['in_reply_to'] : null,
                $idempotencyKey
            );
            return $this->success('MAIL_SENT', $result['message'], $result);
        } catch (Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'uncertain') || str_contains(strtolower($e->getMessage()), 'retry is blocked')) {
                return $this->error('MAIL_DELIVERY_UNCERTAIN', 'Почтовый сервер мог принять письмо. Не отправляйте его повторно с новым ключом; проверьте статус по исходному ключу.', 409);
            }
            return $this->error('SEND_ERROR', 'Не удалось отправить письмо. Проверьте настройки ящика и получателей.', 422);
        }
    }

    /** @param array<string, mixed> $params */
    public function getSendAttemptStatus(array $params = []): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        try {
            $rawIdempotencyKey = $this->request()->input('idempotency_key', '');
            $idempotencyKey = is_string($rawIdempotencyKey) ? trim($rawIdempotencyKey) : '';
            if ($idempotencyKey === '') $idempotencyKey = (string)($this->request()->header('X-TropaTT-Idempotency-Key') ?? '');
            $status = $this->getService()->getSendAttemptStatus(
                $this->getOrganizationId(),
                trim((string)($params['public_id'] ?? '')),
                $idempotencyKey
            );
            if ($status === null) return $this->error('NOT_FOUND', 'Send attempt not found', 404);
            return $this->success('MAIL_SEND_STATUS', 'Send status retrieved', $status);
        } catch (Throwable) {
            return $this->error('STATUS_ERROR', 'Unable to retrieve mail delivery status.', 422);
        }
    }
}
