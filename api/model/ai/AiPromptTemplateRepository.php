<?php
declare(strict_types=1);

namespace Api\Model\Ai;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class AiPromptTemplateRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('ai_prompt_templates');

        if (!empty($filters['intent_code'])) {
            $query->where('intent_code', '=', trim((string)$filters['intent_code']));
        }
        if (!empty($filters['locale'])) {
            $query->where('locale', '=', trim((string)$filters['locale']));
        }
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $active = ((string)$filters['is_active'] === '1' || (string)$filters['is_active'] === 'true') ? 1 : 0;
            $query->where('is_active', '=', $active);
        }

        return $query
            ->select([
                'public_id',
                'intent_code',
                'locale',
                'version',
                'template_text',
                'is_active',
                'created_by_user_id',
                'created_at',
                'updated_at',
            ])
            ->orderBy('intent_code', 'ASC')
            ->orderBy('locale', 'ASC')
            ->orderBy('version', 'DESC')
            ->get();
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('ai_prompt_templates')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function create(array $payload): string
    {
        $publicId = Ulid::generate('aipm');
        $payload['public_id'] = $publicId;
        (new QueryBuilder($this->pdo))
            ->from('ai_prompt_templates')
            ->insert($payload);

        return $publicId;
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('ai_prompt_templates')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deactivateForIntentLocale(string $intentCode, string $locale): void
    {
        (new QueryBuilder($this->pdo))
            ->from('ai_prompt_templates')
            ->where('intent_code', '=', $intentCode)
            ->where('locale', '=', $locale)
            ->update(['is_active' => 0]);
    }

    public function findActiveForIntentLocale(string $intentCode, string $locale): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('ai_prompt_templates')
            ->where('intent_code', '=', $intentCode)
            ->where('locale', '=', $locale)
            ->where('is_active', '=', 1)
            ->orderBy('version', 'DESC')
            ->first();
    }
}

