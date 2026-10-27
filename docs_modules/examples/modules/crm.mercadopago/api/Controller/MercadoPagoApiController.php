<?php

declare(strict_types=1);

namespace Module\Crm\MercadoPago\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\MercadoPago\Service\MercadoPagoPaymentService;
use Throwable;

final class MercadoPagoApiController extends BaseController
{
    /** @return array{0:int}|JsonResponse */
    private function authorizeWorkspace(string $permission): array|JsonResponse
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
            return $this->error('WORKSPACE_REQUIRED', 'Select an active workspace before configuring Mercado Pago.', 409);
        }
        if (empty($user['is_root']) && (!$this->container->has('service.authz')
            || !$this->container->get('service.authz')->hasPermissions($user, [$permission]))) {
            return $this->error('FORBIDDEN', 'Insufficient permissions to manage Mercado Pago.', 403);
        }

        return [$workspaceId];
    }

    public function getStatus(): JsonResponse
    {
        $access = $this->authorizeWorkspace('settings.view');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;
        $stmt = $this->container->get('db.pdo')->prepare('SELECT public_key, sandbox_mode FROM module_mercadopago_settings WHERE workspace_id = :workspace AND is_active = 1 LIMIT 1');
        $stmt->execute([':workspace' => $workspaceId]);
        $settings = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $this->success('MERCADOPAGO_STATUS', 'Состояние подключения загружено.', [
            'connected' => is_array($settings),
            'gateway' => 'Mercado Pago Brazil',
            'sandbox_mode' => is_array($settings) ? (bool)$settings['sandbox_mode'] : null,
            'public_key' => is_array($settings) ? (string)($settings['public_key'] ?? '') : null,
        ]);
    }

    public function saveConfig(): JsonResponse
    {
        $access = $this->authorizeWorkspace('settings.edit');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;
        $input = $this->bodyInput();
        if ($input instanceof JsonResponse) {
            return $input;
        }
        if (!is_string($input['access_token'] ?? null)
            || (isset($input['public_key']) && !is_string($input['public_key']))) {
            return $this->error('CONFIGURATION_INVALID', 'Access token and public key must be text values.', 422);
        }
        $token = trim($input['access_token']);
        if ($token === '') {
            return $this->error('ACCESS_TOKEN_REQUIRED', 'Укажите access token Mercado Pago.', 422);
        }
        if (!is_bool($input['sandbox_mode'] ?? null)) {
            return $this->error('CONFIGURATION_INVALID', 'Select whether to use the sandbox environment.', 422);
        }
        try {
            $service = new MercadoPagoPaymentService($this->container->get('db.pdo'), $workspaceId);
            $service->saveSettings($token, $input['public_key'] ?? null, $input['sandbox_mode']);
        } catch (Throwable) {
            return $this->error('CONFIGURATION_REJECTED', 'Unable to save the configuration securely. Check the token, APP_KEY, and OpenSSL support.', 422);
        }
        return $this->success('MERCADOPAGO_CONFIG_SAVED', 'Settings were saved securely.', ['configured' => true]);
    }

    public function createPayment(): JsonResponse
    {
        $access = $this->authorizeWorkspace('finance.edit');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;
        $input = $this->bodyInput();
        if ($input instanceof JsonResponse) {
            return $input;
        }
        if (!is_string($input['title'] ?? null)
            || !(is_string($input['amount'] ?? null) || is_int($input['amount'] ?? null) || is_float($input['amount'] ?? null))
            || (isset($input['currency_id']) && !is_string($input['currency_id']))
            || (isset($input['external_reference']) && !is_string($input['external_reference']))
            || !is_string($input['idempotency_key'] ?? null)) {
            return $this->error('MERCADOPAGO_PAYMENT_INVALID', 'Check the payment title, amount, currency, and reference.', 422);
        }
        try {
            $result = (new MercadoPagoPaymentService($this->container->get('db.pdo'), $workspaceId))
                ->createPaymentPreference($input);
            return $this->success('MERCADOPAGO_PAYMENT_CREATED', 'Checkout link was created by Mercado Pago.', $result, 201);
        } catch (\InvalidArgumentException) {
            return $this->error('MERCADOPAGO_PAYMENT_INVALID', 'Check the payment title, amount, and BRL currency.', 422);
        } catch (Throwable) {
            return $this->error('MERCADOPAGO_PAYMENT_FAILED', 'The checkout result could not be confirmed. Retry only with the same idempotency key; verify the provider before starting a new checkout.', 502);
        }
    }

    public function listPayments(): JsonResponse
    {
        $access = $this->authorizeWorkspace('finance.view');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;
        $limitInput = $this->request()->query['limit'] ?? '50';
        $limit = is_scalar($limitInput) && preg_match('/^\d{1,3}$/D', (string)$limitInput)
            ? max(1, min(100, (int)$limitInput))
            : 50;
        try {
            return $this->success('MERCADOPAGO_PAYMENTS_LIST', 'Payments loaded.', [
                'items' => (new MercadoPagoPaymentService($this->container->get('db.pdo'), $workspaceId))->listPayments($limit),
            ]);
        } catch (Throwable) {
            return $this->error('MERCADOPAGO_PAYMENTS_UNAVAILABLE', 'Unable to load payments for this workspace.', 503);
        }
    }

    public function checkPaymentStatus(): JsonResponse
    {
        $access = $this->authorizeWorkspace('finance.view');
        if ($access instanceof JsonResponse) {
            return $access;
        }
        [$workspaceId] = $access;
        $input = $this->bodyInput();
        if ($input instanceof JsonResponse) {
            return $input;
        }
        if (!is_string($input['payment_public_id'] ?? null)
            || (isset($input['provider_payment_id']) && !is_string($input['provider_payment_id']))) {
            return $this->error('MERCADOPAGO_PAYMENT_INVALID', 'Payment references must be text values.', 422);
        }
        try {
            $result = (new MercadoPagoPaymentService($this->container->get('db.pdo'), $workspaceId))
                ->checkPaymentStatus($input['payment_public_id'], $input['provider_payment_id'] ?? '');
            return $this->success('MERCADOPAGO_PAYMENT_STATUS', 'Payment status was verified with Mercado Pago.', $result);
        } catch (\InvalidArgumentException) {
            return $this->error('MERCADOPAGO_PAYMENT_INVALID', 'Check the CRM payment reference and provider payment ID.', 422);
        } catch (Throwable $e) {
            $notFound = $e->getMessage() === 'Payment not found.';
            return $this->error(
                $notFound ? 'MERCADOPAGO_PAYMENT_NOT_FOUND' : 'MERCADOPAGO_VERIFICATION_FAILED',
                $notFound ? 'This payment was not found in the active workspace.' : 'Mercado Pago could not verify this payment.',
                $notFound ? 404 : 502
            );
        }
    }

    /** @return array<string,mixed>|JsonResponse */
    private function bodyInput(): array|JsonResponse
    {
        $request = $this->request();
        if ($request->bodyTooLarge || strlen($request->rawBody) > 16384) {
            return $this->error('MERCADOPAGO_PAYLOAD_TOO_LARGE', 'Request exceeds the 16 KB limit.', 413);
        }
        foreach (['access_token', 'token', 'secret_key', 'authorization'] as $sensitiveKey) {
            foreach (array_keys($request->query) as $queryKey) {
                if (is_string($queryKey) && strtolower($queryKey) === $sensitiveKey) {
                    return $this->error('MERCADOPAGO_CREDENTIALS_IN_QUERY', 'Sensitive credentials must be sent in the request body.', 400);
                }
            }
        }
        $contentType = strtolower(trim(explode(';', (string)($request->header('Content-Type') ?? ''))[0]));
        if ($contentType !== 'application/json') {
            return $this->error('UNSUPPORTED_MEDIA_TYPE', 'Requests must use application/json.', 415);
        }
        $data = $request->jsonBody;
        if (!is_array($data) || array_is_list($data)) {
            return $this->error('INVALID_REQUEST_BODY', 'Request body must be a JSON object.', 400);
        }

        return $data;
    }
}
