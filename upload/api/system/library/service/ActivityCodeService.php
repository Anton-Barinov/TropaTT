<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Status\StatusRepository;

/**
 * Activity (work type) codes for time entries — TZ 2.5, Phase 6.
 *
 * Activity codes are NOT a new entity: they live in the existing `statuses`
 * dictionary under a dedicated scope, so CRUD is the generic /api/v1/statuses
 * endpoint (no new table, no new route). This service is a thin, typed
 * accessor plus a single source of truth for the scope name.
 */
final class ActivityCodeService
{
    /** Dictionary scope used for work type codes. */
    public const SCOPE = 'worklog_activity';

    /** Default codes seeded on install/migration (TZ 2.5). */
    public const DEFAULT_CODES = ['dev', 'design', 'analysis', 'consulting', 'support'];

    public function __construct(private readonly StatusRepository $statuses)
    {
    }

    /**
     * Active activity codes, ordered by sort_order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(bool $activeOnly = true): array
    {
        $filters = ['scope' => self::SCOPE, 'limit' => 100];
        if ($activeOnly) {
            $filters['is_active'] = '1';
        }

        [$items] = $this->statuses->list($filters);

        return $items;
    }

    /**
     * @return string[]
     */
    public function codes(bool $activeOnly = true): array
    {
        return array_values(array_filter(array_map(
            static fn(array $row): string => trim((string)($row['code'] ?? '')),
            $this->list($activeOnly)
        ), static fn(string $code): bool => $code !== ''));
    }

    public function exists(string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        return $this->statuses->findByScopeAndCode(self::SCOPE, $code) !== null;
    }
}
