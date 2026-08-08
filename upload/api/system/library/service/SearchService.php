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
        $actorIsRoot = (bool)($actor['is_root'] ?? false);

        $tasks = $this->search->searchTasks($normalized, $limit, $actorUserId, $actorIsRoot);
        $projects = $this->search->searchProjects($normalized, $limit, $actorUserId, $actorIsRoot);
        $creatorIds = $this->creatorScope($actor);
        $counterparties = $this->rankCounterpartyRows($this->search->searchCounterparties($normalized, max($limit * 3, 30), null, $creatorIds), $normalized, $limit);
        $contacts = $this->search->searchContacts($normalized, $limit, $creatorIds);
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
            (bool)($actor['is_root'] ?? false)
        );
    }

    public function projects(string $query, array $actor, int $limit): array
    {
        $normalized = $this->normalizeQuery($query);

        return $this->search->searchProjects(
            $normalized,
            $limit,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false)
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
            $this->search->searchCounterparties($normalized, max($limit * 3, 30), $typeFilter, $this->creatorScope($actor)),
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
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $perTypeLimit = max(1, (int)ceil($limit / 6));

        $taskRows = $this->search->searchTasks($normalized, $perTypeLimit, $actorUserId, $actorIsRoot);
        $projectRows = $this->search->searchProjects($normalized, $perTypeLimit, $actorUserId, $actorIsRoot);
        $creatorIds = $this->creatorScope($actor);
        $counterpartyRows = $this->rankCounterpartyRows($this->search->searchCounterparties($normalized, max($perTypeLimit * 3, 15), null, $creatorIds), $normalized, $perTypeLimit);
        $contactRows = $this->search->searchContacts($normalized, $perTypeLimit, $creatorIds);
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
     * ContactService: root sees everything (empty list = no scope); non-root is
     * limited to records created by themselves or their hierarchy subtree.
     *
     * @param array<string,mixed> $actor
     * @return int[]
     */
    private function creatorScope(array $actor): array
    {
        if ((int)($actor['is_root'] ?? 0) === 1) {
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
