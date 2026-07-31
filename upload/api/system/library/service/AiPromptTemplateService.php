<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Ai\AiPromptTemplateRepository;
use Api\System\Library\Logger\JsonLogger;

final class AiPromptTemplateService
{
    public function __construct(
        private readonly AiPromptTemplateRepository $prompts,
        private readonly JsonLogger $logger
    ) {
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,array<string,mixed>>}
     */
    public function list(array $filters): array
    {
        $items = $this->prompts->list($filters);
        return ['items' => array_map(fn(array $item): array => $this->normalizePrompt($item), $items)];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string,prompt?:array<string,mixed>}
     */
    public function create(array $input, array $actor): array
    {
        $intentCode = trim((string)($input['intent_code'] ?? ''));
        $locale = trim((string)($input['locale'] ?? ''));
        $templateText = trim((string)($input['template_text'] ?? ''));
        $version = max(1, (int)($input['version'] ?? 1));
        $isActive = $this->toBool($input['is_active'] ?? true);

        if ($intentCode === '' || $locale === '' || $templateText === '') {
            return ['ok' => false, 'code' => 'AI_PROMPT_VALIDATION_FAILED'];
        }
        if (mb_strlen($intentCode) > 128 || mb_strlen($locale) > 16 || mb_strlen($templateText) > 64000) {
            return ['ok' => false, 'code' => 'AI_PROMPT_VALIDATION_FAILED'];
        }

        if ($isActive) {
            $this->prompts->deactivateForIntentLocale($intentCode, $locale);
        }

        $now = gmdate('Y-m-d H:i:s');
        $actorId = (int)($actor['id'] ?? 0);
        $publicId = $this->prompts->create([
            'intent_code' => $intentCode,
            'locale' => $locale,
            'version' => $version,
            'template_text' => $templateText,
            'is_active' => $isActive ? 1 : 0,
            'created_by_user_id' => $actorId > 0 ? $actorId : null,
            'updated_by_user_id' => $actorId > 0 ? $actorId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $prompt = $this->prompts->findByPublicId($publicId);
        if (!$prompt) {
            return ['ok' => false, 'code' => 'AI_PROMPT_CREATE_FAILED'];
        }

        $this->logger->audit([
            'action' => 'ai_prompt_template_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_prompt_template',
            'entity_public_id' => $publicId,
            'intent_code' => $intentCode,
            'meta' => ['locale' => $locale, 'version' => $version, 'is_active' => $isActive],
        ]);

        return ['ok' => true, 'prompt' => $this->normalizePrompt($prompt)];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{ok:bool,code?:string,prompt?:array<string,mixed>}
     */
    public function update(string $publicId, array $input, array $actor): array
    {
        $row = $this->prompts->findByPublicId($publicId);
        if (!$row) {
            return ['ok' => false, 'code' => 'AI_PROMPT_NOT_FOUND'];
        }

        $set = [];
        if (array_key_exists('template_text', $input)) {
            $templateText = trim((string)$input['template_text']);
            if ($templateText === '' || mb_strlen($templateText) > 64000) {
                return ['ok' => false, 'code' => 'AI_PROMPT_VALIDATION_FAILED'];
            }
            $set['template_text'] = $templateText;
        }
        if (array_key_exists('version', $input)) {
            $set['version'] = max(1, (int)$input['version']);
        }
        if (array_key_exists('is_active', $input)) {
            $isActive = $this->toBool($input['is_active']);
            $set['is_active'] = $isActive ? 1 : 0;
            if ($isActive) {
                $this->prompts->deactivateForIntentLocale((string)$row['intent_code'], (string)$row['locale']);
            }
        }

        if ($set === []) {
            return ['ok' => false, 'code' => 'AI_PROMPT_NO_CHANGES'];
        }

        $actorId = (int)($actor['id'] ?? 0);
        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $set['updated_by_user_id'] = $actorId > 0 ? $actorId : null;
        $this->prompts->updateByPublicId($publicId, $set);
        $updated = $this->prompts->findByPublicId($publicId);
        if (!$updated) {
            return ['ok' => false, 'code' => 'AI_PROMPT_UPDATE_FAILED'];
        }

        $this->logger->audit([
            'action' => 'ai_prompt_template_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'ai_prompt_template',
            'entity_public_id' => $publicId,
            'intent_code' => (string)($updated['intent_code'] ?? ''),
        ]);

        return ['ok' => true, 'prompt' => $this->normalizePrompt($updated)];
    }

    /** @return array<string,mixed>|null */
    public function resolveActive(string $intentCode, string $locale): ?array
    {
        $normalizedLocale = trim($locale) !== '' ? strtolower(trim($locale)) : 'ru-ru';
        $row = $this->prompts->findActiveForIntentLocale($intentCode, $normalizedLocale);
        if (!$row && $normalizedLocale !== 'ru-ru') {
            $row = $this->prompts->findActiveForIntentLocale($intentCode, 'ru-ru');
        }

        return $row ? $this->normalizePrompt($row) : null;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizePrompt(array $row): array
    {
        return [
            'public_id' => (string)($row['public_id'] ?? ''),
            'intent_code' => (string)($row['intent_code'] ?? ''),
            'locale' => (string)($row['locale'] ?? ''),
            'version' => (int)($row['version'] ?? 1),
            'template_text' => (string)($row['template_text'] ?? ''),
            'is_active' => (bool)($row['is_active'] ?? false),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
