<?php
declare(strict_types=1);

namespace Module\Crm\PaystackAfrica\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\PaystackAfrica\Service\PaystackSettingsService;
use Throwable;

final class PaystackAfricaApiController extends BaseController
{
    public function getStatus(): JsonResponse
    {
        $access = $this->authorizeWorkspacePermission('settings.view');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;

        try {
            return $this->success(
                'PAYSTACK_SETTINGS_STATUS',
                'Paystack settings status loaded.',
                $this->settingsService()->getSettings($workspaceId)
            );
        } catch (Throwable) {
            return $this->error('PAYSTACK_SETTINGS_UNAVAILABLE', 'Unable to read Paystack settings for this workspace.', 500);
        }
    }

    public function saveSettings(): JsonResponse
    {
        $access = $this->authorizeWorkspacePermission('settings.edit');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;

        $input = $this->bodyInput();
        if ($input instanceof JsonResponse) {
            return $input;
        }
        foreach (['public_key', 'secret_key', 'mode'] as $field) {
            if (!isset($input[$field]) || !is_string($input[$field])) {
                return $this->error('PAYSTACK_SETTINGS_INVALID', 'Provide the public key, secret key, and selected mode.', 422);
            }
        }

        try {
            $settings = $this->settingsService()->saveSettings(
                $workspaceId,
                $input['public_key'],
                $input['secret_key'],
                $input['mode']
            );
        } catch (\InvalidArgumentException) {
            return $this->error('PAYSTACK_SETTINGS_INVALID', 'The keys do not match the selected test or live mode.', 422);
        } catch (Throwable) {
            // Never include provider keys, encryption material, or exception text in API output/logs.
            return $this->error('PAYSTACK_SETTINGS_SAVE_FAILED', 'Unable to securely save Paystack settings. Check APP_KEY and OpenSSL configuration.', 503);
        }

        return $this->success('PAYSTACK_SETTINGS_SAVED', 'Paystack settings saved securely.', $settings);
    }

    public function testConnection(): JsonResponse
    {
        $access = $this->authorizeWorkspacePermission('settings.edit');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;
        try {
            return $this->success('PAYSTACK_CONNECTION_OK', 'Paystack credentials were accepted.', $this->paymentService($workspaceId)->testConnection());
        } catch (Throwable) {
            return $this->error('PAYSTACK_CONNECTION_FAILED', 'Paystack could not validate these credentials. Check the selected mode, account access, and outbound HTTPS.', 502);
        }
    }

    public function initializeTransaction(): JsonResponse
    {
        $access = $this->authorizeWorkspacePermission('finance.edit');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;
        $input = $this->bodyInput();
        if ($input instanceof JsonResponse) {
            return $input;
        }
        try {
            $result = $this->paymentService($workspaceId)->initializeTransaction($input);
            return $this->success('PAYSTACK_TRANSACTION_INITIALIZED', 'Payment checkout was created by Paystack.', $result);
        } catch (\InvalidArgumentException) {
            return $this->error('PAYSTACK_TRANSACTION_INVALID', 'Check the customer email, amount, currency, and reference.', 422);
        } catch (Throwable) {
            return $this->error('PAYSTACK_TRANSACTION_FAILED', 'Payment initialization could not be confirmed. Keep the same reference and verify the transaction before retrying.', 502);
        }
    }

    public function verifyTransaction(): JsonResponse
    {
        $access = $this->authorizeWorkspacePermission('finance.view');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;
        $input = $this->bodyInput();
        if ($input instanceof JsonResponse) {
            return $input;
        }
        try {
            $reference = trim((string)($input['reference'] ?? ''));
            return $this->success('PAYSTACK_TRANSACTION_VERIFIED', 'Payment status was checked with Paystack.', $this->paymentService($workspaceId)->verifyTransaction($reference));
        } catch (\InvalidArgumentException) {
            return $this->error('PAYSTACK_REFERENCE_INVALID', 'Provide a valid transaction reference.', 422);
        } catch (Throwable $e) {
            $notFound = $e->getMessage() === 'Transaction reference was not found in this workspace.';
            return $this->error($notFound ? 'PAYSTACK_TRANSACTION_NOT_FOUND' : 'PAYSTACK_VERIFICATION_FAILED', $notFound ? 'This transaction was not found in the active workspace.' : 'Paystack could not verify this payment. Try again later.', $notFound ? 404 : 502);
        }
    }

    public function listTransactions(): JsonResponse
    {
        $access = $this->authorizeWorkspacePermission('finance.view');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;
        $limit = max(1, min(100, (int)($this->request()->query['limit'] ?? 30)));
        try {
            return $this->success('PAYSTACK_TRANSACTIONS_LIST', 'Paystack transactions loaded.', ['items' => $this->paymentService($workspaceId)->listTransactions($limit)]);
        } catch (Throwable) {
            return $this->error('PAYSTACK_TRANSACTIONS_UNAVAILABLE', 'Unable to load transactions for this workspace.', 503);
        }
    }

    public function webhook(array $params = []): JsonResponse
    {
        $request = $this->request();
        if ($request->bodyTooLarge || strlen($request->rawBody) > 262144) {
            return $this->error('PAYSTACK_WEBHOOK_TOO_LARGE', 'Webhook payload exceeds the allowed size.', 413);
        }
        $webhookId = (string)($params['webhook_id'] ?? '');
        try {
            $credentials = $this->settingsService()->getWebhookCredentials($webhookId);
            if (!is_array($credentials)) {
                return $this->error('PAYSTACK_WEBHOOK_NOT_FOUND', 'Webhook route is not configured.', 404);
            }
            $service = new \Module\Crm\PaystackAfrica\Service\PaystackPaymentService(
                $this->container->get('db.pdo'),
                (int)$credentials['workspace_id'],
                (string)$credentials['secret_key']
            );
            $result = $service->processWebhook((string)$request->header('X-Paystack-Signature', ''), $request->rawBody);
            return $this->success('PAYSTACK_WEBHOOK_ACCEPTED', 'Webhook accepted.', $result);
        } catch (\InvalidArgumentException) {
            return $this->error('PAYSTACK_WEBHOOK_INVALID', 'Webhook payload is invalid.', 400);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'INVALID_WEBHOOK_SIGNATURE') {
                return $this->error('PAYSTACK_WEBHOOK_SIGNATURE_INVALID', 'Webhook signature is invalid.', 401);
            }
            if ($e->getMessage() === 'PAYSTACK_TRANSACTION_NOT_FOUND') {
                return $this->error('PAYSTACK_TRANSACTION_NOT_FOUND', 'Webhook transaction was not found.', 404);
            }
            return $this->error('PAYSTACK_WEBHOOK_UNAVAILABLE', 'Webhook could not be processed.', 503);
        }
    }

    /** @return array{0:int}|JsonResponse */
    private function authorizeWorkspacePermission(string $requiredPermission): array|JsonResponse
    {
        $auth = $this->user();
        if (!$auth || !is_array($auth['user'] ?? null)) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }

        $user = (array)$auth['user'];
        $workspaceId = (int)($user['organization_id'] ?? 0);
        if ($workspaceId <= 0) {
            return $this->error('WORKSPACE_REQUIRED', 'Select an active workspace before configuring Paystack.', 409);
        }
        if (empty($user['is_root'])) {
            if (!$this->container->has('service.authz')
                || !$this->container->get('service.authz')->hasPermissions($user, [$requiredPermission])) {
                return $this->error('FORBIDDEN', 'Insufficient permissions to manage Paystack settings.', 403);
            }
        }

        return [$workspaceId];
    }

    private function settingsService(): PaystackSettingsService
    {
        return new PaystackSettingsService($this->container->get('db.pdo'));
    }

    private function paymentService(int $workspaceId): \Module\Crm\PaystackAfrica\Service\PaystackPaymentService
    {
        return new \Module\Crm\PaystackAfrica\Service\PaystackPaymentService($this->container->get('db.pdo'), $workspaceId);
    }

    /** @return array<string, mixed>|JsonResponse */
    private function bodyInput(): array|JsonResponse
    {
        $request = $this->request();
        foreach (['secret_key', 'token', 'api_key', 'authorization'] as $sensitiveKey) {
            foreach (array_keys($request->query) as $queryKey) {
                if (is_string($queryKey) && strtolower($queryKey) === $sensitiveKey) {
                    return $this->error('PAYSTACK_CREDENTIALS_IN_QUERY', 'Sensitive credentials must be sent in the request body.', 400);
                }
            }
        }
        $json = [];
        if ($request->rawBody !== '') {
            $decoded = json_decode($request->rawBody, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }
        return array_replace($request->post, $json);
    }
}
