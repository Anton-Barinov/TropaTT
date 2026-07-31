<?php
declare(strict_types=1);

namespace Api\Model\Ai;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class AiIntentSettingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('ai_intent_settings');

        if (!empty($filters['intent_code'])) {
            $query->where('intent_code', '=', trim((string)$filters['intent_code']));
        }

        if (array_key_exists('is_enabled', $filters) && $filters['is_enabled'] !== '' && $filters['is_enabled'] !== null) {
            $enabled = ((string)$filters['is_enabled'] === '1' || (string)$filters['is_enabled'] === 'true') ? 1 : 0;
            $query->where('is_enabled', '=', $enabled);
        }

        return $query
            ->select([
                'public_id',
                'intent_code',
                'provider_id',
                'model',
                'feature_flag',
                'required_permission',
                'allow_sensitive_context',
                'max_tokens',
                'temperature',
                'is_enabled',
                'intent_payload',
                'created_at',
                'updated_at',
            ])
            ->orderBy('intent_code', 'ASC')
            ->get();
    }

    public function findByIntentCode(string $intentCode): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('ai_intent_settings')
            ->where('intent_code', '=', $intentCode)
            ->first();
    }

    public function create(array $payload): string
    {
        $publicId = Ulid::generate('ait');
        $payload['public_id'] = $publicId;
        (new QueryBuilder($this->pdo))
            ->from('ai_intent_settings')
            ->insert($payload);

        return $publicId;
    }

    public function updateByIntentCode(string $intentCode, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('ai_intent_settings')
            ->where('intent_code', '=', $intentCode)
            ->update($set) > 0;
    }
}
