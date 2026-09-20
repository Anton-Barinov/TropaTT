<?php
declare(strict_types=1);

namespace Api\Model\Recurring;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Language\LanguageManager;
use PDO;
use Api\Model\Task\TaskRepository;
use Api\Model\Project\ProjectRepository;
use Api\System\Library\Support\LikeEscaper;

final class RecurringRepository
{
    private LanguageManager $lang;

    public function __construct(
        private readonly PDO $pdo,
        ?LanguageManager $lang = null,
        private readonly ?TaskRepository $tasks = null,
        private readonly ?ProjectRepository $projects = null
    )
    {
        $this->lang = $lang ?? new LanguageManager(__DIR__ . '/../../language');
    }

    private function t(string $key, string $default = ''): string
    {
        return $this->lang->get($key, $default !== '' ? $default : $key);
    }

    public function list(array $filters, int $actorId = 0, ?int $organizationId = null): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $actorId, $organizationId)->count();
        $items = $this->buildListQuery($filters, $actorId, $organizationId)
            ->select(['public_id', 'title', 'entity_type', 'entity_public_id', 'rrule', 'is_active', 'last_processed_at', 'created_at', 'updated_at'])
            ->orderBy('updated_at', 'DESC')
            ->orderBy('public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, int $actorId = 0, ?int $organizationId = null): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('recurring_rules');

        if ($organizationId !== null && $organizationId > 0) {
            $query->where('organization_id', '=', $organizationId);
        }

        // A recurring rule must not become a side-channel for another user's
        // private Google event. The owner can see their own rule; root is not a
        // bypass for this deliberately private data.
        $query->whereRaw(
            "(entity_type <> 'calendar_event' OR NOT EXISTS (SELECT 1 FROM calendar_events ce WHERE ce.public_id = recurring_rules.entity_public_id AND ce.source_type IN ('google_calendar', 'yandex_calendar') AND ce.source_owner_user_id <> ?))",
            [$actorId]
        );

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', '=', (string)$filters['entity_type']);
        }

        if (!empty($filters['entity_public_id'])) {
            $query->where('entity_public_id', '=', (string)$filters['entity_public_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', '=', ((int)$filters['is_active'] === 1) ? 1 : 0);
        }

        if (!empty($filters['search'])) {
            $search = '%' . LikeEscaper::escape((string)$filters['search']) . '%';
            $query->whereRaw('(entity_public_id LIKE ? OR rrule LIKE ?)', [$search, $search]);
        }

        return $query;
    }

    public function findByPublicId(string $publicId, int $actorId = 0, ?int $organizationId = null): ?array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->select(['public_id', 'title', 'entity_type', 'entity_public_id', 'rrule', 'is_active', 'last_processed_at', 'created_at', 'updated_at'])
            ->where('public_id', '=', $publicId);
        if ($organizationId !== null && $organizationId > 0) {
            $query->where('organization_id', '=', $organizationId);
        }
        $row = $query
            ->whereRaw(
                "(entity_type <> 'calendar_event' OR NOT EXISTS (SELECT 1 FROM calendar_events ce WHERE ce.public_id = recurring_rules.entity_public_id AND ce.source_type IN ('google_calendar', 'yandex_calendar') AND ce.source_owner_user_id <> ?))",
                [$actorId]
            )
            ->first();

        return $row ?: null;
    }

    public function create(array $payload, ?int $organizationId = null): void
    {
        if ($organizationId !== null && $organizationId > 0) {
            $payload['organization_id'] = $organizationId;
        }
        (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set, int $actorId = 0, ?int $organizationId = null): bool
    {
        if ($set === []) {
            return false;
        }

        $query = (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->where('public_id', '=', $publicId);
        if ($organizationId !== null && $organizationId > 0) {
            $query->where('organization_id', '=', $organizationId);
        }

        return $query
            ->whereRaw(
                "(entity_type <> 'calendar_event' OR NOT EXISTS (SELECT 1 FROM calendar_events ce WHERE ce.public_id = recurring_rules.entity_public_id AND ce.source_type IN ('google_calendar', 'yandex_calendar') AND ce.source_owner_user_id <> ?))",
                [$actorId]
            )
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId, int $actorId = 0, ?int $organizationId = null): bool
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('recurring_rules')
            ->where('public_id', '=', $publicId);
        if ($organizationId !== null && $organizationId > 0) {
            $query->where('organization_id', '=', $organizationId);
        }

        return $query
            ->whereRaw(
                "(entity_type <> 'calendar_event' OR NOT EXISTS (SELECT 1 FROM calendar_events ce WHERE ce.public_id = recurring_rules.entity_public_id AND ce.source_type IN ('google_calendar', 'yandex_calendar') AND ce.source_owner_user_id <> ?))",
                [$actorId]
            )
            ->delete() > 0;
    }

    public function canUseEntity(string $entityType, string $entityPublicId, int $actorId = 0, ?int $organizationId = null): bool
    {
        $entityType = trim($entityType);
        $entityPublicId = trim($entityPublicId);

        if ($entityType === 'calendar_event') {
            $stmt = $this->pdo->prepare("SELECT source_owner_user_id FROM calendar_events WHERE public_id = :public_id AND source_type IN ('google_calendar', 'yandex_calendar') LIMIT 1");
            $stmt->execute(['public_id' => $entityPublicId]);
            $ownerId = $stmt->fetchColumn();
            return $ownerId === false || (int)$ownerId === $actorId;
        }

        // Recurring rules can also target tasks and projects. Those entities
        // are organization-scoped resources: an actor must not be able to
        // create/attach a recurring rule referencing another organization's
        // task or project just by knowing its public_id.
        if ($entityType === 'task') {
            if ($entityPublicId === '') return false;
            if ($this->tasks === null) return true; // repository not wired: fail open, unchanged legacy behavior
            return $this->tasks->findByPublicId($entityPublicId, $organizationId) !== null;
        }

        if ($entityType === 'project') {
            if ($entityPublicId === '') return false;
            if ($this->projects === null) return true; // repository not wired: fail open, unchanged legacy behavior
            return $this->projects->findByPublicId($entityPublicId, $organizationId) !== null;
        }

        return true;
    }

    public function resolveEntityTitle(string $entityType, string $entityPublicId): ?string
    {
        $entityType = trim($entityType);
        $entityPublicId = trim($entityPublicId);
        if ($entityType === '' || $entityPublicId === '') {
            return null;
        }

        if ($entityType === 'task') {
            return $this->fetchSingleTitle('SELECT title FROM tasks WHERE public_id = ? LIMIT 1', [$entityPublicId]);
        }

        if ($entityType === 'project') {
            return $this->fetchSingleTitle('SELECT title FROM projects WHERE public_id = ? LIMIT 1', [$entityPublicId]);
        }

        if ($entityType === 'calendar_event') {
            // Private external-calendar events are intentionally not resolvable
            // through the generic recurring subsystem. This prevents a title
            // leak through recurring-rule normalization, including for root.
            return $this->fetchSingleTitle("SELECT title FROM calendar_events WHERE public_id = ? AND (source_type IS NULL OR source_type NOT IN ('google_calendar', 'yandex_calendar')) LIMIT 1", [$entityPublicId]);
        }

        if ($entityType === 'reminder') {
            $title = $this->fetchSingleTitle(
                'SELECT CONCAT(?, COALESCE(t.title, r.public_id)) AS title
                 FROM reminders r
                 LEFT JOIN tasks t ON t.id = r.task_id
                 WHERE r.public_id = ?
                 LIMIT 1',
                [$this->t('recurring/messages.entity_reminder') . ': ', $entityPublicId]
            );
            if ($title !== null) {
                return $title;
            }

            $taskTitle = $this->fetchSingleTitle('SELECT title FROM tasks WHERE public_id = ? LIMIT 1', [$entityPublicId]);
            return $taskTitle !== null ? $this->t('recurring/messages.entity_reminder') . ': ' . $taskTitle : null;
        }

        return null;
    }

    private function fetchSingleTitle(string $sql, array $params): ?string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }

        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }
}
