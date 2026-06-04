<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;

final class AiSemanticIndexService
{
    /** @var array<int,string> */
    private const INDEXABLE_ENTITY_TYPES = [
        'tasks',
        'projects',
        'clients',
        'companies',
        'contacts',
        'comments',
        'files',
    ];
    private const LOCAL_VECTOR_DIMENSIONS = 64;

    public function __construct(
        private readonly Config $config,
        private readonly JsonLogger $logger
    ) {
    }

    /**
     * @return array<int,string>
     */
    public function indexableEntityTypes(): array
    {
        return self::INDEXABLE_ENTITY_TYPES;
    }

    public function isIndexableEntityType(string $entityType): bool
    {
        return in_array($this->normalizeEntityType($entityType), self::INDEXABLE_ENTITY_TYPES, true);
    }

    /**
     * @param array<string,mixed> $meta
     * @return array{ok:bool,code:string}
     */
    public function indexEntityDocument(string $entityType, string $entityPublicId, string $text, array $meta = []): array
    {
        $normalizedType = $this->normalizeEntityType($entityType);
        $entityPublicId = trim($entityPublicId);
        if (!in_array($normalizedType, self::INDEXABLE_ENTITY_TYPES, true) || $entityPublicId === '') {
            return ['ok' => false, 'code' => 'AI_SEMANTIC_ENTITY_NOT_INDEXABLE'];
        }
        if ($normalizedType === 'files' && $this->looksLikeUnsafeFileText($text)) {
            return ['ok' => false, 'code' => 'AI_SEMANTIC_FILE_TEXT_NOT_ALLOWED'];
        }

        return $this->indexDocument($normalizedType . ':' . $entityPublicId, $text, array_merge($meta, [
            'entity_type' => $normalizedType,
            'entity_public_id' => $entityPublicId,
        ]));
    }

    /**
     * @param array<string,mixed> $meta
     * @return array{ok:bool,code:string}
     */
    public function indexDocument(string $documentPublicId, string $text, array $meta = []): array
    {
        $documentPublicId = trim($documentPublicId);
        if ($documentPublicId === '' || trim($text) === '') {
            return ['ok' => false, 'code' => 'AI_SEMANTIC_INDEX_INVALID_INPUT'];
        }

        $index = $this->loadIndex();
        $index[$documentPublicId] = [
            'document_public_id' => $documentPublicId,
            'text' => mb_substr($text, 0, 20000),
            'embedding' => [
                'provider' => 'local_keyword_v1',
                'dimensions' => self::LOCAL_VECTOR_DIMENSIONS,
                'vector' => $this->textVector($text),
            ],
            'meta' => $this->sanitizeMeta($meta),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        $this->saveIndex($index);

        return ['ok' => true, 'code' => 'AI_SEMANTIC_INDEXED'];
    }

    /**
     * @return array{ok:bool,items:array<int,array<string,mixed>>}
     */
    public function search(string $query, int $limit = 10): array
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return ['ok' => true, 'items' => []];
        }

        $index = $this->loadIndex();
        $queryVector = $this->textVector($needle);
        $items = [];
        foreach ($index as $item) {
            if (!is_array($item)) {
                continue;
            }
            $haystack = mb_strtolower((string)($item['text'] ?? ''));
            $embedding = is_array($item['embedding'] ?? null) ? (array)$item['embedding'] : [];
            $vector = is_array($embedding['vector'] ?? null) ? (array)$embedding['vector'] : [];
            $score = $this->cosineSimilarity($queryVector, $vector);
            if ($haystack === '' || (!str_contains($haystack, $needle) && $score <= 0.0)) {
                continue;
            }
            $items[] = [
                'document_public_id' => (string)($item['document_public_id'] ?? ''),
                'score' => max(str_contains($haystack, $needle) ? 1.0 : 0.0, $score),
                'snippet' => mb_substr((string)($item['text'] ?? ''), 0, 280),
                'meta' => is_array($item['meta'] ?? null) ? (array)$item['meta'] : [],
            ];
        }

        usort($items, static function (array $left, array $right): int {
            return ((float)($right['score'] ?? 0.0) <=> (float)($left['score'] ?? 0.0));
        });

        return ['ok' => true, 'items' => array_slice($items, 0, max(1, min(100, $limit)))];
    }

    /**
     * @return array{ok:bool,code:string}
     */
    public function removeDocument(string $documentPublicId): array
    {
        $documentPublicId = trim($documentPublicId);
        if ($documentPublicId === '') {
            return ['ok' => false, 'code' => 'AI_SEMANTIC_INDEX_INVALID_INPUT'];
        }
        $index = $this->loadIndex();
        unset($index[$documentPublicId]);
        $this->saveIndex($index);

        return ['ok' => true, 'code' => 'AI_SEMANTIC_REMOVED'];
    }

    /**
     * @return array{ok:bool,code:string}
     */
    public function removeEntityDocument(string $entityType, string $entityPublicId): array
    {
        $normalizedType = $this->normalizeEntityType($entityType);
        $entityPublicId = trim($entityPublicId);
        if (!in_array($normalizedType, self::INDEXABLE_ENTITY_TYPES, true) || $entityPublicId === '') {
            return ['ok' => false, 'code' => 'AI_SEMANTIC_ENTITY_NOT_INDEXABLE'];
        }

        return $this->removeDocument($normalizedType . ':' . $entityPublicId);
    }

    /** @return array<string,mixed> */
    private function loadIndex(): array
    {
        $file = $this->indexFile();
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $index */
    private function saveIndex(array $index): void
    {
        $file = $this->indexFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $encoded = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $this->logger->error(['action' => 'ai_semantic_index_encode_failed']);
            return;
        }
        @file_put_contents($file, $encoded, LOCK_EX);
    }

    private function indexFile(): string
    {
        $defaultBase = rtrim((string)$this->config->get('default.storage.base', dirname(__DIR__, 4) . '/../storage_api'), '/\\');
        $base = (string)$this->config->get('ai.storage.cache', $defaultBase . '/ai/cache');
        return rtrim($base, '/\\') . '/semantic-index.json';
    }

    /** @param array<string,mixed> $meta @return array<string,mixed> */
    private function sanitizeMeta(array $meta): array
    {
        $safe = [];
        foreach ($meta as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $normalized = strtolower($key);
            if (
                str_contains($normalized, 'token')
                || str_contains($normalized, 'secret')
                || str_contains($normalized, 'password')
                || str_contains($normalized, 'storage_path')
                || str_contains($normalized, 'content')
                || str_contains($normalized, 'base64')
                || str_contains($normalized, 'binary')
                || str_contains($normalized, 'blob')
                || str_contains($normalized, 'raw')
            ) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }
        return $safe;
    }

    private function looksLikeUnsafeFileText(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/\b(?:content-transfer-encoding:\s*base64|application\/octet-stream)\b/iu', $trimmed) === 1) {
            return true;
        }

        if (preg_match('/^[A-Za-z0-9+\/\r\n]{512,}={0,2}$/', $trimmed) === 1) {
            return true;
        }

        $sample = mb_substr($trimmed, 0, 2048);
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $sample) === 1;
    }

    private function normalizeEntityType(string $entityType): string
    {
        $normalized = strtolower(trim($entityType));
        return match ($normalized) {
            'task' => 'tasks',
            'project' => 'projects',
            'client' => 'clients',
            'company' => 'companies',
            'contact' => 'contacts',
            'comment' => 'comments',
            'file', 'file_metadata' => 'files',
            default => $normalized,
        };
    }

    /** @return array<int,float> */
    private function textVector(string $text): array
    {
        $vector = array_fill(0, self::LOCAL_VECTOR_DIMENSIONS, 0.0);
        $tokens = preg_split('/[^\p{L}\p{N}_]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens)) {
            return $vector;
        }

        foreach ($tokens as $token) {
            $token = trim((string)$token);
            if (mb_strlen($token) < 2) {
                continue;
            }
            $slot = abs((int)crc32($token)) % self::LOCAL_VECTOR_DIMENSIONS;
            $vector[$slot] += 1.0;
        }

        $norm = 0.0;
        foreach ($vector as $value) {
            $norm += $value * $value;
        }
        if ($norm <= 0.0) {
            return $vector;
        }

        $norm = sqrt($norm);
        foreach ($vector as $index => $value) {
            $vector[$index] = round($value / $norm, 6);
        }

        return $vector;
    }

    /** @param array<int,mixed> $left @param array<int,mixed> $right */
    private function cosineSimilarity(array $left, array $right): float
    {
        $score = 0.0;
        for ($i = 0; $i < self::LOCAL_VECTOR_DIMENSIONS; $i += 1) {
            $score += (float)($left[$i] ?? 0.0) * (float)($right[$i] ?? 0.0);
        }

        return round(max(0.0, min(1.0, $score)), 6);
    }
}
