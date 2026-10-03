<?php
declare(strict_types=1);

namespace Module\Crm\FixtureConnector\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Connector\ConnectorSignatureVerifier;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Module\ModuleExecutionContext;

final class FixtureConnectorController extends BaseController
{
    /**
     * Protected REST endpoint showing workspace-isolated diagnostic status.
     */
    public function status(): JsonResponse
    {
        $context = $this->container->has('module.execution_context')
            ? $this->container->get('module.execution_context')
            : null;

        $orgId = $context instanceof ModuleExecutionContext ? $context->organizationId : 0;
        $orgPublicId = $context instanceof ModuleExecutionContext ? $context->organizationPublicId : '';

        return $this->success('FIXTURE_STATUS', 'OK', [
            'status' => 'active',
            'module' => 'crm.fixture-connector',
            'organization_id' => $orgId,
            'organization_public_id' => $orgPublicId,
        ]);
    }

    /**
     * Public signed webhook callback.
     */
    public function webhook(): JsonResponse
    {
        $request = $this->request();
        $rawBody = (string)$request->rawBody();
        $signatureHeader = (string)($request->headers['X-Hub-Signature-256']
            ?? $request->headers['x-hub-signature-256']
            ?? $request->headers['X-Signature']
            ?? '');

        if ($signatureHeader === '') {
            return $this->error('SIGNATURE_MISSING', 'Signature header is missing', 401);
        }

        $secret = 'fixture_webhook_secret_key_12345';
        $verifier = new ConnectorSignatureVerifier();
        $valid = $verifier->verifyHmacSha256($rawBody, $secret, $signatureHeader);

        if (!$valid) {
            return $this->error('SIGNATURE_INVALID', 'Invalid request signature', 401);
        }

        $payload = json_decode($rawBody, true);

        return $this->success('WEBHOOK_RECEIVED', 'Webhook accepted', [
            'received' => true,
            'event' => is_array($payload) ? ($payload['event'] ?? 'unknown') : 'unknown',
            'timestamp' => time(),
        ]);
    }

    /**
     * Mutating protected sync action requiring Idempotency-Key.
     */
    public function sync(): JsonResponse
    {
        $input = $this->request()->allInput();

        return $this->success('SYNC_EXECUTED', 'Sync executed successfully', [
            'synced_at' => date('Y-m-d H:i:s'),
            'records' => (int)($input['records_count'] ?? 10),
        ]);
    }
}
