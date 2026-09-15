<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Repair `knowledge_pages.last_version_number` for pages that already had
 * version snapshots.
 *
 * Version rows used to be inserted by hand (`KnowledgeRepository::legacyAddVersion()`)
 * without touching the counter, so pages kept reporting 0 versions while their
 * snapshots existed: the field is part of the page payload (`GET /api/v1/knowledge/pages`
 * and `.../pages/{public_id}`) and clients read "no versions" from it. Fixing the
 * writer only stopped the drift — rows written before it stay wrong until their
 * next save, and untouched pages never get a next save.
 *
 * Only ever raises the counter to the highest stored version number: a counter
 * that is ahead of the stored snapshots is left alone (it is not evidence of a
 * missing version, and lowering it would let a future save reuse a version
 * number). Soft-deleted snapshots count, because their numbers were consumed.
 *
 * The stored `content_hash` values of legacy snapshots are *not* backfilled:
 * the hash is computed from a composed snapshot in PHP, so SQL cannot reproduce
 * it faithfully. A NULL hash is treated by both writers as "no known hash", so
 * the first save after this migration writes one more snapshot and deduplication
 * resumes from there.
 *
 * Idempotent: guarded column add + a scoped UPDATE that is a no-op on a second run.
 */
final class KnowledgePageVersionCounterBackfillMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260915_000002_knowledge_page_version_counter_backfill';
    }

    public function description(): string
    {
        return 'Backfill knowledge_pages.last_version_number from stored page versions';
    }

    public function up(PDO $pdo, string $driver): void
    {
        // Installs that never ran KnowledgePageVersionsMigration may lack the
        // column entirely; the guarded helper is driver-aware.
        IndexHelper::addColumnIfNotExists($pdo, 'knowledge_pages', 'last_version_number', 'INTEGER NOT NULL DEFAULT 0', $driver);

        if (!IndexHelper::columnExists($pdo, $driver, 'knowledge_page_versions', 'page_id')) {
            // No version storage at all: nothing can be out of sync.
            return;
        }

        $pdo->exec('UPDATE knowledge_pages
            SET last_version_number = (
                SELECT MAX(kpv.version_number)
                FROM knowledge_page_versions kpv
                WHERE kpv.page_id = knowledge_pages.id
            )
            WHERE EXISTS (
                SELECT 1
                FROM knowledge_page_versions kpv_exists
                WHERE kpv_exists.page_id = knowledge_pages.id
            )
            AND last_version_number < (
                SELECT MAX(kpv_max.version_number)
                FROM knowledge_page_versions kpv_max
                WHERE kpv_max.page_id = knowledge_pages.id
            )');
    }
}
