<?php
declare(strict_types=1);

namespace Module\Crm\WhatsAppBusiness\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Api\System\Library\Http\RawTextResponse;
use Api\System\Library\Support\AppLog;
use Module\Crm\WhatsAppBusiness\Service\WhatsAppBusinessService;
use Throwable;

final class WhatsAppBusinessApiController extends BaseController
{
    private function getService(): WhatsAppBusinessService
    {
        return new WhatsAppBusinessService($this->container->get('db.pdo'));
    }

    /** Workspace is derived from the authenticated session, never from caller-supplied input. */
    private function activeOrganizationId(): ?int
    {
        $auth = $this->user();
        $user = is_array($auth['user'] ?? null) ? $auth['user'] : [];
        $organizationId = (int)($user['organization_id'] ?? 0);
        return $organizationId > 0 ? $organizationId : null;
    }

    public function getConfigStatus(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        $organizationId = $this->activeOrganizationId();
        if ($organizationId === null) {
            return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Active workspace is required.', 409);
        }
        $webhookUrl = '/api/index.php?route=_module/crm.whatsapp-business/webhook/' . $organizationId;
        try {
            return $this->success('WHATSAPP_CONFIG_STATUS', 'OK', $this->getService()->getStatus($organizationId, $webhookUrl));
        } catch (Throwable) {
            return $this->error('WHATSAPP_STATUS_FAILED', 'WhatsApp setup status is unavailable.', 500);
        }
    }

    public function saveConfig(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) {
            return $contextError;
        }
        $organizationId = $this->activeOrganizationId();
        if ($organizationId === null) {
            return $this->error('ORGANIZATION_CONTEXT_REQUIRED', 'Active workspace is required.', 409);
        }
        $input = $this->request()->allInput();
        try {
            $publicId = $this->getService()->saveConfig(
                $organizationId,
                trim((string)($input['phone_number_id'] ?? '')),
                trim((string)($input['waba_id'] ?? '')),
                trim((string)($input['api_token'] ?? '')),
                trim((string)($input['verify_token'] ?? '')),
                trim((string)($input['app_secret'] ?? '')),
                (int)($auth['user']['id'] ?? 0)
            );
            return $this->success('WHATSAPP_CONFIG_SAVED', 'Credentials encrypted and saved.', [
                'public_id' => $publicId,
                'status' => $this->getService()->getStatus($organizationId),
            ]);
        } catch (Throwable) {
            return $this->error('WHATSAPP_CONFIG_SAVE_FAILED', 'Configuration was not saved. Check the required IDs, credentials, APP_KEY, and OpenSSL support.', 422);
        }
    }

    public function verifyWebhook(array $params): RawTextResponse
    {
        $orgId = (int)($params['org_id'] ?? 0);
        $query = $this->request()->query;
        // PHP normalizes dots in external query parameter names to underscores.
        $mode = (string)($query['hub_mode'] ?? $query['hub.mode'] ?? '');
        $token = (string)($query['hub_verify_token'] ?? $query['hub.verify_token'] ?? '');
        $challenge = (string)($query['hub_challenge'] ?? $query['hub.challenge'] ?? '');
        if ($orgId > 0 && $mode === 'subscribe' && $challenge !== '' && strlen($challenge) <= 512
            && preg_match('/^[A-Za-z0-9._~-]+$/D', $challenge)) {
            try {
                if ($this->getService()->verifyWebhookChallenge($orgId, $token) === true) {
                    return new RawTextResponse($challenge, 200);
                }
            } catch (Throwable) {
                // Return the same generic denial as invalid tokens; never reveal credential state.
            }
        }
        return new RawTextResponse('Forbidden', 403);
    }

    public function handleWebhook(array $params): JsonResponse
    {
        $orgId = (int)($params['org_id'] ?? 0);
        if ($orgId <= 0) {
            return $this->error('VALIDATION_ERROR', 'Invalid workspace identifier', 400);
        }
        $request = $this->request();
        $rawBody = $request->rawBody;
        $contentLength = (int)($request->server['CONTENT_LENGTH'] ?? $request->server['HTTP_CONTENT_LENGTH'] ?? 0);
        if ($request->bodyTooLarge || $contentLength > 262144 || strlen($rawBody) > 262144) {
            return $this->error('PAYLOAD_TOO_LARGE', 'Webhook payload exceeds the 256 KB limit.', 413);
        }
        $contentType = strtolower(trim(explode(';', (string)($request->header('Content-Type') ?? ''))[0]));
        if ($contentType !== 'application/json') {
            return $this->error('UNSUPPORTED_MEDIA_TYPE', 'Webhook payload must use application/json.', 415);
        }
        $signature = trim((string)($request->header('X-Hub-Signature-256') ?? ''));
        $payload = $this->request()->allInput();

        $service = $this->getService();
        try {
            $res = $service->handleWebhook($orgId, $payload, $rawBody, $signature !== '' ? $signature : null);
            $actor = $this->webhookIntakeActor($orgId);
            if ($res['conversation_ids'] !== [] && $actor === null) {
                return $this->error('INTAKE_LINK_FAILED', 'Webhook could not be fully processed.', 503);
            }
            if ($res['conversation_ids'] !== []) {
                foreach ($res['conversation_ids'] as $conversationId) {
                    try {
                        $intakeService = $this->container->get('service.intake_item');
                        $linked = $service->linkIntake($orgId, (string)$conversationId, static fn(array $input, array $intakeActor): array|string => $intakeService->create($input, $intakeActor), $actor);
                    } catch (Throwable $linkError) {
                        AppLog::error('[WhatsAppBusinessApiController::handleWebhook] Intake linking failed (' . get_class($linkError) . ').');
                        $linked = null;
                    }
                    if ($linked === null) {
                        // Ask Meta to retry. Ingress is deduplicated, and retry will repair Intake linking.
                        return $this->error('INTAKE_LINK_FAILED', 'Webhook could not be fully processed.', 503);
                    }
                }
            }
            return $this->success('WHATSAPP_WEBHOOK_PROCESSED', 'Webhook handled', $res);
        } catch (\DomainException $e) {
            AppLog::error('[WhatsAppBusinessApiController::handleWebhook] Webhook rejected (' . get_class($e) . ').');
            return $this->error('WHATSAPP_ERROR', 'Webhook request was rejected.', 400);
        } catch (Throwable $e) {
            AppLog::error('[WhatsAppBusinessApiController::handleWebhook] Webhook processing failed (' . get_class($e) . ').');
            return $this->error('WHATSAPP_ERROR', 'Webhook processing is temporarily unavailable.', 503);
        }
    }

    public function listInbox(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $contextError = $this->rejectInvalidOrganizationContext();
        if ($contextError !== null) {
            return $contextError;
        }
        $user = (array)($auth['user'] ?? []);
        $orgId = (int)($user['organization_id'] ?? 0);
        if ($orgId <= 0) {
            return $this->error('FORBIDDEN', 'An active workspace is required', 403);
        }
        if (empty($user['is_root'])) {
            if (!$this->container->has('service.authz')
                || !$this->container->get('service.authz')->hasPermissions($user, ['settings.view'])) {
                return $this->error('FORBIDDEN', 'Insufficient permissions to view WhatsApp conversations', 403);
            }
        }

        $service = $this->getService();
        $items = $service->listConversations($orgId);

        return $this->success('WHATSAPP_INBOX_LIST', 'Inbox listed', ['items' => $items]);
    }

    public function getConversation(array $params): JsonResponse
    {
        $context = $this->authorizedWorkspaceUser('settings.view');
        if ($context instanceof JsonResponse) return $context;
        $publicId = trim((string)($params['conversation_id'] ?? ''));
        if (!preg_match('/^wcn_[a-f0-9]{20}$/D', $publicId)) return $this->error('NOT_FOUND', 'Conversation not found.', 404);
        $service = $this->getService();
        $messages = $service->listMessages($context['organization_id'], $publicId);
        return $this->success('WHATSAPP_CONVERSATION', 'Conversation loaded.', ['messages' => $messages]);
    }

    public function reply(array $params): JsonResponse
    {
        $context = $this->authorizedWorkspaceUser('settings.edit');
        if ($context instanceof JsonResponse) return $context;
        $publicId = trim((string)($params['conversation_id'] ?? ''));
        $input = $this->request()->allInput();
        $key = (string)($this->request()->header('Idempotency-Key') ?? $this->request()->header('X-Idempotency-Key') ?? '');
        try {
            $result = $this->getService()->sendReply($context['organization_id'], $publicId, (int)$context['user']['id'], (string)($input['text'] ?? ''), $key);
            return $this->success('WHATSAPP_REPLY_SENT', 'Reply sent.', $result);
        } catch (Throwable $e) {
            AppLog::error('[WhatsAppBusinessApiController::reply] Reply failed (' . get_class($e) . ').');
            return $this->error('WHATSAPP_REPLY_FAILED', 'Reply was not confirmed. Check the conversation before retrying.', 422);
        }
    }

    /** @return array{user:array,organization_id:int}|JsonResponse */
    private function authorizedWorkspaceUser(string $permission): array|JsonResponse
    {
        $auth = $this->user();
        if (!$auth) return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        if (($contextError = $this->rejectInvalidOrganizationContext()) !== null) return $contextError;
        $user = (array)($auth['user'] ?? []);
        $orgId = (int)($user['organization_id'] ?? 0);
        if ($orgId <= 0) return $this->error('FORBIDDEN', 'An active workspace is required.', 403);
        if (empty($user['is_root']) && (!$this->container->has('service.authz') || !$this->container->get('service.authz')->hasPermissions($user, [$permission]))) {
            return $this->error('FORBIDDEN', 'Insufficient permissions.', 403);
        }
        return ['user' => $user, 'organization_id' => $orgId];
    }

    /** Select only safe user columns and require active membership before creating Intake records. */
    private function webhookIntakeActor(int $organizationId): ?array
    {
        $db = $this->container->get('db.pdo');
        $stmt = $db->prepare('SELECT u.id, u.public_id, u.login, u.full_name FROM crm_whatsapp_configs c JOIN users u ON u.id = c.configured_by_user_id JOIN organization_memberships om ON om.user_id = u.id AND om.organization_id = c.organization_id WHERE c.organization_id = :org AND c.is_active = 1 AND u.is_active = 1 LIMIT 1');
        $stmt->execute([':org' => $organizationId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($user) ? $user + ['organization_id' => $organizationId] : null;
    }
}
