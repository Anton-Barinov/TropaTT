<?php
declare(strict_types=1);

namespace Api\Model\Ai;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class AiRuntimeRepository
{
    /** @var array<string,array<string,int>> */
    private const JSON_FIELD_MAX_BYTES = [
        'ai_suggestions' => [
            'suggestion_json' => 131072,
        ],
        'ai_jobs' => [
            'payload_json' => 262144,
            'result_json' => 131072,
        ],
        'ai_usage_logs' => [
            'request_meta' => 65536,
        ],
    ];

    /** @var array<string,bool>|null */
    private ?array $aiJobsColumns = null;
    /** @var array<string,bool>|null */
    private ?array $aiSuggestionsColumns = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createJob(array $payload): string
    {
        $publicId = Ulid::generate('aij');
        $payload['public_id'] = $publicId;
        $payload = $this->applyJsonFieldLimits('ai_jobs', $payload);

        (new QueryBuilder($this->pdo))
            ->from('ai_jobs')
            ->insert($payload);

        return $publicId;
    }

    public function updateJobByPublicId(string $publicId, array $set): void
    {
        if ($set === []) {
            return;
        }
        $set = $this->applyJsonFieldLimits('ai_jobs', $set);

        (new QueryBuilder($this->pdo))
            ->from('ai_jobs')
            ->where('public_id', '=', $publicId)
            ->update($set);
    }

    public function findJobByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('ai_jobs')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function findActiveJobByIdempotencyHash(string $hash): ?array
    {
        $value = trim($hash);
        if ($value === '') {
            return null;
        }

        return (new QueryBuilder($this->pdo))
            ->from('ai_jobs')
            ->where('idempotency_key_hash', '=', $value)
            ->whereIn('status', ['queued', 'running'])
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    /**
     * Reserve a bounded interactive AI slot before a slow provider call.
     *
     * A short DB critical section is intentionally used instead of waiting in
     * PHP-FPM. This keeps normal CRM requests responsive on shared hosting
     * while already started generations may run up to their provider timeout.
     *
     * @param array<string,mixed> $payload
     */
    public function claimInteractiveSlot(array $payload, int $maxConcurrent, int $staleAfterSeconds = 660): ?string
    {
        $maxConcurrent = max(1, min(16, $maxConcurrent));
        $staleBefore = gmdate('Y-m-d H:i:s', time() - max(60, $staleAfterSeconds));
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $hasAdvisoryLock = false;

        try {
            if ($driver === 'mysql') {
                $lock = $this->pdo->prepare('SELECT GET_LOCK(:name, 0)');
                $lock->execute(['name' => 'crm_ai_interactive_slots']);
                if ((int)$lock->fetchColumn() !== 1) {
                    return null;
                }
                $hasAdvisoryLock = true;
            }

            $this->pdo->beginTransaction();
            $cleanup = $this->pdo->prepare("UPDATE ai_jobs
                SET status = 'failed', error_code = 'AI_REQUEST_STALE', error_message = 'Interactive request did not finish', finished_at = :now, updated_at = :now
                WHERE job_type = 'interactive' AND status = 'running' AND started_at IS NOT NULL AND started_at < :stale_before");
            $now = gmdate('Y-m-d H:i:s');
            $cleanup->execute(['now' => $now, 'stale_before' => $staleBefore]);

            $count = $this->pdo->prepare("SELECT COUNT(*) FROM ai_jobs WHERE job_type = 'interactive' AND status = 'running'");
            $count->execute();
            if ((int)$count->fetchColumn() >= $maxConcurrent) {
                $this->pdo->rollBack();
                return null;
            }

            $publicId = $this->createJob($payload);
            $this->pdo->commit();
            return $publicId;
        } catch (\Throwable $e) {
            error_log('[AiRuntimeRepository::claimInteractiveSlot] ' . $e->getMessage());
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return null;
        } finally {
            if ($hasAdvisoryLock) {
                try {
                    $release = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
                    $release->execute(['name' => 'crm_ai_interactive_slots']);
                } catch (\Throwable $e) {
                    error_log('[AiRuntimeRepository::claimInteractiveSlot] SELECT: ' . $e->getMessage());
                    // The connection will release an advisory lock on close.
                }
            }
        }
    }

    public function listJobs(array $filters, bool $canViewAll, int $actorUserId, string $actorUserPublicId = ''): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $query = $this->buildJobsQuery($filters, $canViewAll, $actorUserId, $actorUserPublicId);
        $total = $query->count();

        $select = [
            'public_id',
            'job_type',
            'action_type',
            'intent_code',
            'status',
            'requested_by_user_id',
            'payload_json',
            'result_json',
            'error_code',
            'error_message',
            'created_at',
            'updated_at',
        ];
        foreach (['scope_type', 'scope_public_id', 'idempotency_key_hash', 'started_at', 'finished_at'] as $optionalColumn) {
            if ($this->hasAiJobsColumn($optionalColumn)) {
                $select[] = $optionalColumn;
            }
        }

        $items = $this->buildJobsQuery($filters, $canViewAll, $actorUserId, $actorUserPublicId)
            ->select($select)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    public function createUsageLog(array $payload): void
    {
        $payload['public_id'] = Ulid::generate('ail');
        $payload = $this->applyJsonFieldLimits('ai_usage_logs', $payload);
        (new QueryBuilder($this->pdo))
            ->from('ai_usage_logs')
            ->insert($payload);
    }

    public function listUsageLogs(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $query = $this->buildUsageLogsQuery($filters);
        $total = $query->count();

        $items = $this->buildUsageLogsQuery($filters)
            ->select([
                'public_id',
                'user_id',
                'provider_public_id',
                'action_type',
                'intent_code',
                'status',
                'error_code',
                'request_tokens',
                'response_tokens',
                'total_tokens',
                'latency_ms',
                'is_sensitive_context',
                'request_meta',
                'created_at',
            ])
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /**
     * @return array{request_count:int,total_tokens:int}
     */
    public function usageAggregateSince(string $sinceUtc, ?int $userId = null, ?string $actionType = null): array
    {
        $sql = 'SELECT COUNT(*) AS request_count, COALESCE(SUM(total_tokens), 0) AS total_tokens FROM ai_usage_logs WHERE created_at >= :since';
        $params = [':since' => $sinceUtc];
        if ($userId !== null && $userId > 0) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = $userId;
        }
        if ($actionType !== null && trim($actionType) !== '') {
            $sql .= ' AND action_type = :action_type';
            $params[':action_type'] = trim($actionType);
        }

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            return ['request_count' => 0, 'total_tokens' => 0];
        }
        foreach ($params as $name => $value) {
            if ($name === ':user_id') {
                $stmt->bindValue($name, (int)$value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue($name, (string)$value, \PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['request_count' => 0, 'total_tokens' => 0];
        }

        return [
            'request_count' => (int)($row['request_count'] ?? 0),
            'total_tokens' => (int)($row['total_tokens'] ?? 0),
        ];
    }

    public function createSuggestion(array $payload): string
    {
        $publicId = Ulid::generate('aisg');
        $payload['public_id'] = $publicId;
        foreach (array_keys($payload) as $column) {
            if (!is_string($column)) {
                continue;
            }
            if (in_array($column, [
                'public_id',
                'intent_code',
                'entity_type',
                'entity_public_id',
                'summary',
                'suggestion_json',
                'status',
                'created_by_user_id',
                'confirmed_by_user_id',
                'created_at',
                'updated_at',
                'expires_at',
            ], true)) {
                continue;
            }
            if (!$this->hasAiSuggestionsColumn($column)) {
                unset($payload[$column]);
            }
        }
        $payload = $this->applyJsonFieldLimits('ai_suggestions', $payload);

        (new QueryBuilder($this->pdo))
            ->from('ai_suggestions')
            ->insert($payload);

        return $publicId;
    }

    public function updateSuggestionByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('ai_suggestions')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function findSuggestionByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('ai_suggestions')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    public function findSuggestionByInputHash(string $intentCode, string $entityType, string $entityPublicId, string $inputHash): ?array
    {
        if (!$this->hasAiSuggestionsColumn('input_hash')) {
            return null;
        }

        $hash = trim($inputHash);
        if ($hash === '') {
            return null;
        }

        return (new QueryBuilder($this->pdo))
            ->from('ai_suggestions')
            ->where('intent_code', '=', trim($intentCode))
            ->where('entity_type', '=', trim($entityType))
            ->where('entity_public_id', '=', trim($entityPublicId))
            ->where('input_hash', '=', $hash)
            ->whereIn('status', ['draft', 'ready', 'applied', 'partially_applied', 'dismissed'])
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    public function findLatestSuggestionByCacheKey(
        string $intentCode,
        string $entityType,
        string $entityPublicId,
        int $createdByUserId,
        string $cacheKey
    ): ?array {
        if (!$this->hasAiSuggestionsColumn('cache_key')) {
            return null;
        }

        $cacheKey = trim($cacheKey);
        if ($cacheKey === '' || $createdByUserId <= 0) {
            return null;
        }

        return (new QueryBuilder($this->pdo))
            ->from('ai_suggestions')
            ->where('intent_code', '=', trim($intentCode))
            ->where('entity_type', '=', trim($entityType))
            ->where('entity_public_id', '=', trim($entityPublicId))
            ->where('created_by_user_id', '=', $createdByUserId)
            ->where('cache_key', '=', $cacheKey)
            // Cache hit supports both:
            // - actionable suggestions (draft/ready) for preview/apply
            // - read-only cached results (final statuses) with can_apply=false
            ->whereIn('status', ['draft', 'ready', 'applied', 'partially_applied', 'dismissed', 'confirmed'])
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    public function markSuggestionUsed(string $publicId, string $now): void
    {
        $set = ['updated_at' => $now];
        if ($this->hasAiSuggestionsColumn('last_used_at')) {
            $set['last_used_at'] = $now;
        }

        if ($this->hasAiSuggestionsColumn('usage_count')) {
            try {
                $sql = 'UPDATE ai_suggestions SET usage_count = COALESCE(usage_count, 0) + 1, updated_at = :updated_at';
                if ($this->hasAiSuggestionsColumn('last_used_at')) {
                    $sql .= ', last_used_at = :last_used_at';
                }
                $sql .= ' WHERE public_id = :public_id';
                $stmt = $this->pdo->prepare($sql);
                if ($stmt !== false) {
                    $stmt->bindValue(':updated_at', $now);
                    if ($this->hasAiSuggestionsColumn('last_used_at')) {
                        $stmt->bindValue(':last_used_at', $now);
                    }
                    $stmt->bindValue(':public_id', $publicId);
                    $stmt->execute();
                    return;
                }
                return;
            } catch (\Throwable $e) {
                error_log('[AiRuntimeRepository::markSuggestionUsed] ' . $e->getMessage());
                // fall through to plain update
            }
        }

        (new QueryBuilder($this->pdo))
            ->from('ai_suggestions')
            ->where('public_id', '=', $publicId)
            ->update($set);
    }

    public function findLatestDraftSuggestionForRange(
        string $intentCode,
        string $entityType,
        string $entityPublicId,
        string $fromDateTime,
        string $toDateTime
    ): ?array {
        return (new QueryBuilder($this->pdo))
            ->from('ai_suggestions')
            ->where('intent_code', '=', trim($intentCode))
            ->where('entity_type', '=', trim($entityType))
            ->where('entity_public_id', '=', trim($entityPublicId))
            ->where('status', '=', 'draft')
            ->where('created_at', '>=', trim($fromDateTime))
            ->where('created_at', '<=', trim($toDateTime))
            ->orderBy('updated_at', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    public function listSuggestions(array $filters, bool $canViewAll, int $actorUserId): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $query = $this->buildSuggestionsQuery($filters, $canViewAll, $actorUserId);
        $total = $query->count();

        $items = $this->buildSuggestionsQuery($filters, $canViewAll, $actorUserId)
            ->select([
                'public_id',
                'intent_code',
                'entity_type',
                'entity_public_id',
                'summary',
                'suggestion_json',
                'status',
                'created_by_user_id',
                'confirmed_by_user_id',
                'created_at',
                'updated_at',
                'expires_at',
                'cache_key',
                'dependency_fingerprint',
                'cache_status',
                'stale_reason',
                'date_bucket',
                'provider_public_id',
                'provider_code',
                'model',
                'last_used_at',
                'usage_count',
                'request_id',
                'invalidated_at',
                'result_meta_json',
            ])
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    /**
     * @param array<string,int|string|bool|null> $policies
     * @return array{suggestions_deleted:int,jobs_deleted:int,usage_logs_deleted:int}
     */
    public function cleanupByRetention(array $policies): array
    {
        $deleted = [
            'suggestions_deleted' => 0,
            'jobs_deleted' => 0,
            'usage_logs_deleted' => 0,
        ];

        try {
            $suggestionsDays = max(1, (int)($policies['suggestions_ttl_days'] ?? 30));
            $jobsDays = max(1, (int)($policies['jobs_ttl_days'] ?? 30));
            $usageDays = max(1, (int)($policies['usage_logs_ttl_days'] ?? 90));

            $suggestionsCutoff = gmdate('Y-m-d H:i:s', time() - ($suggestionsDays * 86400));
            $jobsCutoff = gmdate('Y-m-d H:i:s', time() - ($jobsDays * 86400));
            $usageCutoff = gmdate('Y-m-d H:i:s', time() - ($usageDays * 86400));

            $deleted['suggestions_deleted'] = (new QueryBuilder($this->pdo))
                ->from('ai_suggestions')
                ->where('created_at', '<', $suggestionsCutoff)
                ->delete();

            $deleted['jobs_deleted'] = (new QueryBuilder($this->pdo))
                ->from('ai_jobs')
                ->where('created_at', '<', $jobsCutoff)
                ->whereRaw('(status IS NULL OR status NOT IN (?, ?))', ['queued', 'running'])
                ->delete();

            $deleted['usage_logs_deleted'] = (new QueryBuilder($this->pdo))
                ->from('ai_usage_logs')
                ->where('created_at', '<', $usageCutoff)
                ->delete();
        } catch (\Throwable $e) {
            error_log('[AiRuntimeRepository::cleanupByRetention] DELETE: ' . $e->getMessage());
            return $deleted;
        }

        return $deleted;
    }

    private function buildSuggestionsQuery(array $filters, bool $canViewAll, int $actorUserId): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('ai_suggestions');

        if (!$canViewAll) {
            $query->where('created_by_user_id', '=', $actorUserId);
        } elseif (array_key_exists('created_by_user_id', $filters) && (int)$filters['created_by_user_id'] > 0) {
            $query->where('created_by_user_id', '=', (int)$filters['created_by_user_id']);
        }

        if (!empty($filters['intent_code'])) {
            $query->where('intent_code', '=', trim((string)$filters['intent_code']));
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', '=', trim((string)$filters['entity_type']));
        }

        if (!empty($filters['entity_public_id'])) {
            $query->where('entity_public_id', '=', trim((string)$filters['entity_public_id']));
        }

        if (!empty($filters['status'])) {
            $query->where('status', '=', trim((string)$filters['status']));
        }

        return $query;
    }

    private function buildUsageLogsQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('ai_usage_logs');

        if (array_key_exists('user_id', $filters) && (int)$filters['user_id'] > 0) {
            $query->where('user_id', '=', (int)$filters['user_id']);
        }
        if (!empty($filters['provider_public_id'])) {
            $query->where('provider_public_id', '=', trim((string)$filters['provider_public_id']));
        }
        if (!empty($filters['action_type'])) {
            $query->where('action_type', '=', trim((string)$filters['action_type']));
        }
        if (!empty($filters['intent_code'])) {
            $query->where('intent_code', '=', trim((string)$filters['intent_code']));
        }
        if (!empty($filters['status'])) {
            $query->where('status', '=', trim((string)$filters['status']));
        }
        if (!empty($filters['error_code'])) {
            $query->where('error_code', '=', trim((string)$filters['error_code']));
        }
        if (array_key_exists('is_sensitive_context', $filters)) {
            $query->where('is_sensitive_context', '=', (int)((bool)$filters['is_sensitive_context']));
        }

        return $query;
    }

    private function buildJobsQuery(array $filters, bool $canViewAll, int $actorUserId, string $actorUserPublicId = ''): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('ai_jobs');

        if (!$canViewAll) {
            if (
                $actorUserPublicId !== ''
                && $this->hasAiJobsColumn('scope_type')
                && $this->hasAiJobsColumn('scope_public_id')
            ) {
                $query->whereRaw('(requested_by_user_id = ? OR (scope_type = ? AND scope_public_id = ?))', [
                    $actorUserId,
                    'user',
                    $actorUserPublicId,
                ]);
            } else {
                $query->where('requested_by_user_id', '=', $actorUserId);
            }
        } elseif (array_key_exists('requested_by_user_id', $filters) && (int)$filters['requested_by_user_id'] > 0) {
            $query->where('requested_by_user_id', '=', (int)$filters['requested_by_user_id']);
        }

        if (!empty($filters['job_type'])) {
            $query->where('job_type', '=', trim((string)$filters['job_type']));
        }
        if (!empty($filters['action_type'])) {
            $query->where('action_type', '=', trim((string)$filters['action_type']));
        }
        if (!empty($filters['intent_code'])) {
            $query->where('intent_code', '=', trim((string)$filters['intent_code']));
        }
        if (!empty($filters['status'])) {
            $query->where('status', '=', trim((string)$filters['status']));
        }
        if (!empty($filters['scope_type']) && $this->hasAiJobsColumn('scope_type')) {
            $query->where('scope_type', '=', trim((string)$filters['scope_type']));
        }
        if (!empty($filters['scope_public_id']) && $this->hasAiJobsColumn('scope_public_id')) {
            $query->where('scope_public_id', '=', trim((string)$filters['scope_public_id']));
        }
        if (!empty($filters['error_code'])) {
            $query->where('error_code', '=', trim((string)$filters['error_code']));
        }

        return $query;
    }

    private function hasAiJobsColumn(string $column): bool
    {
        $columns = $this->aiJobsColumnMap();
        return isset($columns[$column]);
    }

    private function hasAiSuggestionsColumn(string $column): bool
    {
        $columns = $this->aiSuggestionsColumnMap();
        return isset($columns[$column]);
    }

    /** @return array<string,bool> */
    private function aiJobsColumnMap(): array
    {
        if (is_array($this->aiJobsColumns)) {
            return $this->aiJobsColumns;
        }

        $this->aiJobsColumns = [];
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ($driver === 'sqlite') {
                $rows = $this->pdo->query('PRAGMA table_info(ai_jobs)')->fetchAll() ?: [];
                foreach ($rows as $row) {
                    $name = trim((string)($row['name'] ?? ''));
                    if ($name !== '') {
                        $this->aiJobsColumns[$name] = true;
                    }
                }
                return $this->aiJobsColumns;
            }

            if ($driver === 'sqlsrv') {
                $stmt = $this->pdo->prepare('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(:table_name)');
                if ($stmt !== false) {
                    $stmt->execute(['table_name' => 'ai_jobs']);
                    $rows = $stmt->fetchAll() ?: [];
                    foreach ($rows as $row) {
                        $name = trim((string)($row['name'] ?? ''));
                        if ($name !== '') {
                            $this->aiJobsColumns[$name] = true;
                        }
                    }
                }
                return $this->aiJobsColumns;
            }

            $tableSchemaSql = $driver === 'pgsql'
                ? 'SELECT column_name AS name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table_name'
                : 'SELECT column_name AS name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name';

            $stmt = $this->pdo->prepare($tableSchemaSql);
            if ($stmt !== false) {
                $stmt->execute(['table_name' => 'ai_jobs']);
                $rows = $stmt->fetchAll() ?: [];
                foreach ($rows as $row) {
                    $name = trim((string)($row['name'] ?? ''));
                    if ($name !== '') {
                        $this->aiJobsColumns[$name] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[AiRuntimeRepository::aiJobsColumnMap] ' . $e->getMessage());
            $this->aiJobsColumns = [];
        }

        return $this->aiJobsColumns;
    }

    /** @return array<string,bool> */
    private function aiSuggestionsColumnMap(): array
    {
        if (is_array($this->aiSuggestionsColumns)) {
            return $this->aiSuggestionsColumns;
        }

        $this->aiSuggestionsColumns = [];
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ($driver === 'sqlite') {
                $rows = $this->pdo->query('PRAGMA table_info(ai_suggestions)')->fetchAll() ?: [];
                foreach ($rows as $row) {
                    $name = trim((string)($row['name'] ?? ''));
                    if ($name !== '') {
                        $this->aiSuggestionsColumns[$name] = true;
                    }
                }
                return $this->aiSuggestionsColumns;
            }

            if ($driver === 'sqlsrv') {
                $stmt = $this->pdo->prepare('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(:table_name)');
                if ($stmt !== false) {
                    $stmt->execute(['table_name' => 'ai_suggestions']);
                    $rows = $stmt->fetchAll() ?: [];
                    foreach ($rows as $row) {
                        $name = trim((string)($row['name'] ?? ''));
                        if ($name !== '') {
                            $this->aiSuggestionsColumns[$name] = true;
                        }
                    }
                }
                return $this->aiSuggestionsColumns;
            }

            $tableSchemaSql = $driver === 'pgsql'
                ? 'SELECT column_name AS name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table_name'
                : 'SELECT column_name AS name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name';

            $stmt = $this->pdo->prepare($tableSchemaSql);
            if ($stmt !== false) {
                $stmt->execute(['table_name' => 'ai_suggestions']);
                $rows = $stmt->fetchAll() ?: [];
                foreach ($rows as $row) {
                    $name = trim((string)($row['name'] ?? ''));
                    if ($name !== '') {
                        $this->aiSuggestionsColumns[$name] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[AiRuntimeRepository::aiSuggestionsColumnMap] ' . $e->getMessage());
            $this->aiSuggestionsColumns = [];
        }

        return $this->aiSuggestionsColumns;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function applyJsonFieldLimits(string $table, array $payload): array
    {
        $limits = self::JSON_FIELD_MAX_BYTES[$table] ?? [];
        if ($limits === []) {
            return $payload;
        }

        foreach ($limits as $field => $maxBytes) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $payload[$field] = $this->truncateJsonField($payload[$field], $field, $maxBytes);
        }

        return $payload;
    }

    private function truncateJsonField(mixed $value, string $field, int $maxBytes): mixed
    {
        if (!is_string($value) || $maxBytes < 256) {
            return $value;
        }

        $bytes = strlen($value);
        if ($bytes <= $maxBytes) {
            return $value;
        }

        $marker = [
            '_truncated' => true,
            '_field' => $field,
            '_original_bytes' => $bytes,
            '_max_bytes' => $maxBytes,
            '_sha1' => sha1($value),
        ];
        $encoded = json_encode($marker, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded) && strlen($encoded) <= $maxBytes) {
            return $encoded;
        }

        return '{"_truncated":true}';
    }
}
