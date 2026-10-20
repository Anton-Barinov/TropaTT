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
        return (int)($auth['user']['organization_id'] ?? 1);
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
            return $this->success('MAILBOX_SYNCED', $syncRes['message'], $syncRes);
        } catch (Throwable $e) {
            return $this->error('SYNC_ERROR', 'Ошибка синхронизации: ' . $e->getMessage(), 500);
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
}
