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

    public function handleWebhook(array $params): JsonResponse
    {
        $orgId = (int)($params['org_id'] ?? 1);
        $secretToken = (string)($this->request()->header('X-Telegram-Bot-Api-Secret-Token') ?? '');
        $update = $this->request()->allInput();

        $service = $this->getService();
        try {
            $result = $service->handleUpdate($orgId, $secretToken, $update);
            return $this->success('TELEGRAM_UPDATE_HANDLED', 'Update processed', $result);
        } catch (Throwable $e) {
            return $this->error('TELEGRAM_ERROR', $e->getMessage(), 400);
        }
    }

    public function requestBinding(): JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', 'Unauthorized', 401);
        }

        $orgId = (int)($auth['user']['organization_id'] ?? 1);
        $userPubId = (string)($auth['user']['public_id'] ?? '');

        $service = $this->getService();
        $code = $service->generateBindingCode($orgId, $userPubId);

        return $this->success('TELEGRAM_BINDING_CODE', 'Code generated', ['verification_code' => $code]);
    }
}
