<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

interface MigrationInterface
{
    public function key(): string;

    public function description(): string;

    public function up(PDO $pdo, string $driver): void;
}
