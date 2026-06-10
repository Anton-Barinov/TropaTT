<?php
declare(strict_types=1);

namespace Api\Controller\Feature_flag;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\FeatureFlagService;

final class FeatureFlagController extends BaseController
{
    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        $cache = $this->cacheApi();
        if ($cache !== null) {
            $input = $this->request()->allInput();
            ksort($input);
            $cacheKey = 'list:' . $this->cacheUserId() . ':' . md5(json_encode($input));
            $result = $cache->remember('feature_flag', $cacheKey, 60, function () use ($input) {
                /** @var FeatureFlagService $service */
                $service = $this->container->get('service.feature_flag');
                return $service->list($input);
            });
        } else {
            /** @var FeatureFlagService $service */
            $service = $this->container->get('service.feature_flag');
            $result = $service->list($this->request()->allInput());
        }

        return $this->success('FEATURE_FLAG_LIST', $this->t('feature_flag/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var FeatureFlagService $service */
        $service = $this->container->get('service.feature_flag');
        $result = $service->update((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'FEATURE_FLAG_UPDATE_FAILED');
            $status = match ($code) {
                'FEATURE_FLAG_NOT_FOUND' => 404,
                'FEATURE_FLAG_NO_CHANGES' => 422,
                default => 400,
            };

            return $this->error($code, $this->t('feature_flag/messages.update_failed'), $status, [
                'feature_flag' => [$code],
            ]);
        }

        $this->invalidateCache('feature_flag');

        return $this->success('FEATURE_FLAG_UPDATED', $this->t('feature_flag/messages.updated'), [
            'feature_flag' => $result['flag'],
        ]);
    }
}
