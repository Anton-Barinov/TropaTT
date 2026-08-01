<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

use Api\System\Library\Database\Builder\QueryBuilder;

function queryBuilderAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');

    $isNullQuery = (new QueryBuilder($pdo))
        ->from('ai_providers')
        ->where('is_active', '=', 1)
        ->where('deleted_at', 'IS', null)
        ->orderBy('updated_at', 'DESC')
        ->limit(1);

    queryBuilderAssert(
        $isNullQuery->toSql() === 'SELECT * FROM ai_providers WHERE (is_active = :p1) AND (deleted_at IS NULL) ORDER BY updated_at DESC LIMIT 1',
        'IS NULL must be compiled without a bound placeholder'
    );
    queryBuilderAssert(
        $isNullQuery->getBindings() === [':p1' => 1],
        'NULL comparison must not add a null binding'
    );

    $isNotNullQuery = (new QueryBuilder($pdo))
        ->from('users')
        ->where('deleted_at', '<>', null);

    queryBuilderAssert(
        $isNotNullQuery->toSql() === 'SELECT * FROM users WHERE (deleted_at IS NOT NULL)',
        '<> NULL must be normalized to IS NOT NULL'
    );

    echo "[OK] query_builder_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] query_builder_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
