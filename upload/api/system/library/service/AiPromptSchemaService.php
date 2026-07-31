<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class AiPromptSchemaService
{
    public function __construct(
        private readonly AiPromptTemplateService $prompts,
        private readonly AiJsonSchemaService $schemas
    ) {
    }

    public function listPrompts(array $filters): array
    {
        return $this->prompts->list($filters);
    }

    public function createPrompt(array $input, array $actor): array
    {
        return $this->prompts->create($input, $actor);
    }

    public function updatePrompt(string $publicId, array $input, array $actor): array
    {
        return $this->prompts->update($publicId, $input, $actor);
    }

    public function listSchemas(array $filters): array
    {
        return $this->schemas->list($filters);
    }

    public function createSchema(array $input, array $actor): array
    {
        return $this->schemas->create($input, $actor);
    }

    public function updateSchema(string $publicId, array $input, array $actor): array
    {
        return $this->schemas->update($publicId, $input, $actor);
    }

    public function resolveActivePrompt(string $intentCode, string $locale): ?array
    {
        return $this->prompts->resolveActive($intentCode, $locale);
    }

    public function resolveActiveSchema(string $intentCode): ?array
    {
        return $this->schemas->resolveActive($intentCode);
    }

    public function validatePayloadBySchema(string $intentCode, array $payload): array
    {
        return $this->schemas->validatePayloadBySchema($intentCode, $payload);
    }
}
