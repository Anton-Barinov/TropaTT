<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Knowledge\KnowledgeRepository;
use Api\Model\Search\SearchRepository;
use Api\Model\User\UserManagementRepository;

final class SearchService
{
    public function __construct(
        private readonly SearchRepository $search,
        private readonly KnowledgeRepository $knowledge,
        private readonly UserManagementRepository $users
    )
    {
    }

    public function global(string $query, array $actor, int $limit): array
    {
        $normalized = $this->normalizeQuery($query);
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorIsRoot = $this->isTrueRoot($actor);
        $organizationId = $this->organizationScope($actor);

        $tasks = $this->search->searchTasks($normalized, $limit, $actorUserId, $actorIsRoot, $organizationId);
        $projects = $this->search->searchProjects($normalized, $limit, $actorUserId, $actorIsRoot, $organizationId);
        $creatorIds = $this->creatorScope($actor);
        $counterparties = $this->rankCounterpartyRows($this->search->searchCounterparties($normalized, max($limit * 3, 30), null, $creatorIds, $organizationId), $normalized, $limit);
        $contacts = $this->search->searchContacts($normalized, $limit, $creatorIds, $organizationId);
        $knowledge = $this->knowledge->search($normalized, ['limit' => $limit, 'status' => 'published'], $actor);

        return [
            'query' => $normalized,
            'results' => [
                'tasks' => $tasks,
                'projects' => $projects,
                'counterparties' => $counterparties,
                'contacts' => $contacts,
                'knowledge' => $knowledge,
            ],
            'counts' => [
                'tasks' => count($tasks),
                'projects' => count($projects),
                'counterparties' => count($counterparties),
                'contacts' => count($contacts),
                'knowledge' => count($knowledge),
            ],
        ];
    }

    public function tasks(string $query, array $actor, int $limit): array
    {
        $normalized = $this->normalizeQuery($query);

        return $this->search->searchTasks(
            $normalized,
            $limit,
            (int)($actor['id'] ?? 0),
            $this->isTrueRoot($actor),
            $this->organizationScope($actor)
        );
    }

    public function projects(string $query, array $actor, int $limit): array
    {
        $normalized = $this->normalizeQuery($query);

        return $this->search->searchProjects(
            $normalized,
            $limit,
            (int)($actor['id'] ?? 0),
            $this->isTrueRoot($actor),
            $this->organizationScope($actor)
        );
    }

    /**
     * Поиск по контрагентам (унифицировано: клиенты + компании).
     *
     * $actor defaults to [] on purpose: creatorScope([]) yields the -1 sentinel
     * (fail-closed, matches nothing). Do NOT change the default to a permissive
     * value — a forgotten actor must never see every record.
     */
    public function counterparties(string $query, int $limit, ?array $typeFilter = null, array $actor = []): array
    {
        $normalized = $this->normalizeQuery($query);
        return $this->rankCounterpartyRows(
            $this->search->searchCounterparties($normalized, max($limit * 3, 30), $typeFilter, $this->creatorScope($actor), $this->organizationScope($actor)),
            $normalized,
            $limit
        );
    }

    /**
     * Legacy: поиск по клиентам (обратная совместимость).
     * @deprecated Используйте counterparties()
     */
    public function clients(string $query, int $limit, array $actor = []): array
    {
        return $this->counterparties($query, $limit, ['individual', 'sole_proprietor', 'legal_entity'], $actor);
    }

    public function suggestions(string $query, array $actor, int $limit): array
    {
        $normalized = $this->normalizeQuery($query);
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorIsRoot = $this->isTrueRoot($actor);
        $organizationId = $this->organizationScope($actor);
        $perTypeLimit = max(1, (int)ceil($limit / 6));

        $taskRows = $this->search->searchTasks($normalized, $perTypeLimit, $actorUserId, $actorIsRoot, $organizationId);
        $projectRows = $this->search->searchProjects($normalized, $perTypeLimit, $actorUserId, $actorIsRoot, $organizationId);
        $creatorIds = $this->creatorScope($actor);
        $counterpartyRows = $this->rankCounterpartyRows($this->search->searchCounterparties($normalized, max($perTypeLimit * 3, 15), null, $creatorIds, $organizationId), $normalized, $perTypeLimit);
        $contactRows = $this->search->searchContacts($normalized, $perTypeLimit, $creatorIds, $organizationId);
        $knowledgeRows = $this->knowledge->search($normalized, ['limit' => $perTypeLimit, 'status' => 'published'], $actor);

        $items = [];
        foreach ($taskRows as $row) {
            $items[] = [
                'entity_type' => 'task',
                'public_id' => (string)($row['public_id'] ?? ''),
                'label' => (string)($row['title'] ?? ''),
                'meta' => [
                    'status_code' => $row['status_code'] ?? null,
                    'priority_code' => $row['priority_code'] ?? null,
                ],
            ];
        }
        foreach ($projectRows as $row) {
            $items[] = [
                'entity_type' => 'project',
                'public_id' => (string)($row['public_id'] ?? ''),
                'label' => (string)($row['title'] ?? ''),
                'meta' => [
                    'status_code' => $row['status_code'] ?? null,
                    'priority_code' => $row['priority_code'] ?? null,
                ],
            ];
        }
        foreach ($counterpartyRows as $row) {
            $items[] = [
                'entity_type' => 'counterparty',
                'public_id' => (string)($row['public_id'] ?? ''),
                'label' => (string)($row['title'] ?? ''),
                'meta' => [
                    'counterparty_type' => $row['counterparty_type'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                ],
            ];
        }
        foreach ($contactRows as $row) {
            $items[] = [
                'entity_type' => 'contact',
                'public_id' => (string)($row['public_id'] ?? ''),
                'label' => (string)($row['full_name'] ?? ''),
                'meta' => [
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                ],
            ];
        }
        foreach ($knowledgeRows as $row) {
            $items[] = [
                'entity_type' => 'knowledge',
                'public_id' => (string)($row['public_id'] ?? ''),
                'label' => (string)($row['title'] ?? ''),
                'meta' => [
                    'space_title' => $row['space_title'] ?? null,
                    'page_type' => $row['page_type'] ?? null,
                ],
            ];
        }

        $items = array_values(array_filter($items, static fn(array $item): bool => $item['public_id'] !== ''));
        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }

        return [
            'query' => $normalized,
            'items' => $items,
            'count' => count($items),
            'groups' => [
                'tasks' => count($taskRows),
                'projects' => count($projectRows),
                'counterparties' => count($counterpartyRows),
                'contacts' => count($contactRows),
                'knowledge' => count($knowledgeRows),
            ],
        ];
    }

    /**
     * Fail-closed creator scope for search, mirroring CounterpartyService and
     * ContactService: a true platform superadmin sees everything (empty list =
     * no scope); every other actor — including an ordinary org owner/admin who
     * also carries is_root=1 alongside a real organization_id — is limited to
     * records created by themselves or their hierarchy subtree, and is always
     * additionally confined to their own organization via organizationScope().
     *
     * @param array<string,mixed> $actor
     * @return int[]
     */
    private function creatorScope(array $actor): array
    {
        if ($this->isTrueRoot($actor)) {
            return [];
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return [-1];
        }

        $descendants = $this->users->descendantIds($actorId);
        if ($descendants === []) {
            $descendants = [$actorId];
        }

        return $descendants;
    }

    /**
     * True platform superadmin, per this codebase's established convention
     * (see AnalyticsService::assigneeDepartmentLoad()/completionVelocity()/
     * streamsOverview() and SubscriptionService::list()/delete()):
     * is_root=1 AND organization_id <= 0. An ordinary org owner/admin can also
     * carry is_root=1 while belonging to a real organization (see
     * UserService::create()'s $actorIsRoot check paired with hierarchy scope,
     * and how actor rows are built) — such an actor is NOT a true superadmin
     * and must always stay confined to their own organization_id.
     *
     * @param array<string,mixed> $actor
     */
    private function isTrueRoot(array $actor): bool
    {
        return (bool)($actor['is_root'] ?? false) && (int)($actor['organization_id'] ?? 0) <= 0;
    }

    /**
     * Multi-tenant workspace boundary for search: null means "no boundary" and
     * is returned ONLY for a true platform superadmin (see isTrueRoot()).
     * Every other actor gets their own organization_id, or the -1 sentinel
     * (fail-closed, matches nothing) when they have none — never a permissive
     * default.
     *
     * @param array<string,mixed> $actor
     */
    private function organizationScope(array $actor): ?int
    {
        if ($this->isTrueRoot($actor)) {
            return null;
        }

        $organizationId = (int)($actor['organization_id'] ?? 0);
        return $organizationId > 0 ? $organizationId : -1;
    }

    private function normalizeQuery(string $query): string
    {
        return trim($query);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function rankCounterpartyRows(array $rows, string $query, int $limit): array
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return array_slice($rows, 0, $limit);
        }

        $scored = [];
        foreach ($rows as $row) {
            $score = 0;
            $taxInn = strtolower(trim((string)($row['tax_inn'] ?? '')));
            $title = strtolower(trim((string)($row['title'] ?? '')));
            $legalName = strtolower(trim((string)($row['legal_name'] ?? '')));
            $email = strtolower(trim((string)($row['email'] ?? '')));
            $phone = strtolower(trim((string)($row['phone'] ?? '')));
            $website = strtolower(trim((string)($row['website'] ?? '')));

            if ($taxInn !== '') {
                if ($taxInn === $q) {
                    $score += 200;
                } elseif (str_starts_with($taxInn, $q)) {
                    $score += 130;
                } elseif (str_contains($taxInn, $q)) {
                    $score += 70;
                }
            }

            foreach ([[$title, 120, 80, 50], [$legalName, 110, 75, 45], [$email, 90, 60, 35], [$phone, 90, 60, 35], [$website, 70, 45, 25]] as $tuple) {
                [$value, $exact, $prefix, $contains] = $tuple;
                if ($value === '') {
                    continue;
                }
                if ($value === $q) {
                    $score += $exact;
                } elseif (str_starts_with($value, $q)) {
                    $score += $prefix;
                } elseif (str_contains($value, $q)) {
                    $score += $contains;
                }
            }

            $row['_search_score'] = $score;
            $scored[] = $row;
        }

        usort($scored, static function (array $a, array $b): int {
            $scoreCmp = (int)($b['_search_score'] ?? 0) <=> (int)($a['_search_score'] ?? 0);
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }
            $aUpdated = (string)($a['updated_at'] ?? '');
            $bUpdated = (string)($b['updated_at'] ?? '');
            return strcmp($bUpdated, $aUpdated);
        });

        $trimmed = array_slice($scored, 0, $limit);
        foreach ($trimmed as &$item) {
            unset($item['_search_score']);
        }
        unset($item);

        return $trimmed;
    }
}
