<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiAvailabilityService;

final class AiAvailabilityController extends BaseController
{
    public function get(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $requestedIntents = $this->parseIntentCodes((string)$this->request()->input('intents', ''));

        /** @var AiAvailabilityService $service */
        $service = $this->container->get('service.ai_availability');
        $data = $service->getAvailability((array)$auth['user'], $requestedIntents);

        return $this->success('AI_AVAILABILITY_GET', $this->t('ai/messages.availability_get'), $data);
    }

    /**
     * @return list<string>
     */
    private function parseIntentCodes(string $input): array
    {
        if (trim($input) === '') {
            return [];
        }

        $items = array_map(static fn(string $value): string => trim($value), explode(',', $input));
        $items = array_values(array_filter($items, static fn(string $value): bool => $value !== ''));

        return array_values(array_unique($items));
    }
}
