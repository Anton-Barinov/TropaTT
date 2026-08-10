<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Tags gained a description column in the base schema. Installs created before
 * that change only get it through CREATE TABLE IF NOT EXISTS (no effect on an
 * existing table), so existing databases must be upgraded explicitly.
 */
final class TagDescriptionMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260810_000001_tags_description';
    }

    public function description(): string
    {
        return 'Add description column to tags for existing installs';
    }

    public function up(PDO $pdo, string $driver): void
    {
        IndexHelper::addColumnIfNotExists($pdo, 'tags', 'description', 'TEXT NULL', $driver);
    }
}
