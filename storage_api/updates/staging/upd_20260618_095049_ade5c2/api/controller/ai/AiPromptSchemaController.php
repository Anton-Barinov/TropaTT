<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiPromptSchemaService;

final class AiPromptSchemaController extends BaseController
{
    public function listPrompts(): \Api\System\Library\Http\JsonResponse
    {
        /** @var AiPromptSchemaService $service */
        $service = $this->container->get('service.ai_prompt_schema');
        $result = $service->listPrompts($this->request()->allInput());

        return $this->success('AI_PROMPT_LIST', $this->t('ai/messages.prompt_list'), ['items' => $result['items']]);
    }

    public function createPrompt(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        return $this->withIdempotency(function () use ($auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiPromptSchemaService $service */
            $service = $this->container->get('service.ai_prompt_schema');
            $result = $service->createPrompt($this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = (string)($result['code'] ?? 'AI_PROMPT_CREATE_FAILED');
                return $this->error($code, $this->t('ai/messages.prompt_create_failed'), 422, ['prompt' => [$code]]);
            }

            return $this->success('AI_PROMPT_CREATED', $this->t('ai/messages.prompt_created'), ['prompt' => $result['prompt']], 201);
        });
    }

    public function updatePrompt(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var AiPromptSchemaService $service */
        $service = $this->container->get('service.ai_prompt_schema');
        $result = $service->updatePrompt((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'AI_PROMPT_UPDATE_FAILED');
            $status = match ($code) {
                'AI_PROMPT_NOT_FOUND' => 404,
                'AI_PROMPT_NO_CHANGES' => 422,
                default => 422,
            };
            return $this->error($code, $this->t('ai/messages.prompt_update_failed'), $status, ['prompt' => [$code]]);
        }

        return $this->success('AI_PROMPT_UPDATED', $this->t('ai/messages.prompt_updated'), ['prompt' => $result['prompt']]);
    }

    public function listSchemas(): \Api\System\Library\Http\JsonResponse
    {
        /** @var AiPromptSchemaService $service */
        $service = $this->container->get('service.ai_prompt_schema');
        $result = $service->listSchemas($this->request()->allInput());

        return $this->success('AI_SCHEMA_LIST', $this->t('ai/messages.schema_list'), ['items' => $result['items']]);
    }

    public function createSchema(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        return $this->withIdempotency(function () use ($auth): \Api\System\Library\Http\JsonResponse {
            /** @var AiPromptSchemaService $service */
            $service = $this->container->get('service.ai_prompt_schema');
            $result = $service->createSchema($this->request()->allInput(), $auth['user']);
            if (!(bool)($result['ok'] ?? false)) {
                $code = (string)($result['code'] ?? 'AI_SCHEMA_CREATE_FAILED');
                return $this->error($code, $this->t('ai/messages.schema_create_failed'), 422, ['schema' => [$code]]);
            }

            return $this->success('AI_SCHEMA_CREATED', $this->t('ai/messages.schema_created'), ['schema' => $result['schema']], 201);
        });
    }

    public function updateSchema(array $params): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var AiPromptSchemaService $service */
        $service = $this->container->get('service.ai_prompt_schema');
        $result = $service->updateSchema((string)$params['public_id'], $this->request()->allInput(), $auth['user']);
        if (!(bool)($result['ok'] ?? false)) {
            $code = (string)($result['code'] ?? 'AI_SCHEMA_UPDATE_FAILED');
            $status = match ($code) {
                'AI_SCHEMA_NOT_FOUND' => 404,
                'AI_SCHEMA_NO_CHANGES' => 422,
                default => 422,
            };
            return $this->error($code, $this->t('ai/messages.schema_update_failed'), $status, ['schema' => [$code]]);
        }

        return $this->success('AI_SCHEMA_UPDATED', $this->t('ai/messages.schema_updated'), ['schema' => $result['schema']]);
    }
}

