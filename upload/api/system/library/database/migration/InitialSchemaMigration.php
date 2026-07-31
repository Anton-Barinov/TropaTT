<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\SchemaManager;
use PDO;

final class InitialSchemaMigration implements MigrationInterface
{
    public function __construct(private readonly SchemaManager $schema)
    {
    }

    public function key(): string
    {
        return '20260417_000001_initial_schema';
    }

    public function description(): string
    {
        return 'Initial CRM schema and dictionaries';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $this->schema->createSchema($pdo, $driver);
        $this->schema->seedDictionaries($pdo);
    }
}
