<?php
declare(strict_types=1);

use Api\Model\Counterparty\CounterpartyRepository;
use Api\Model\User\UserManagementRepository;
use Api\System\Library\Policy\HierarchyPolicy;
use Api\System\Library\Service\CompanyService;
use Api\System\Library\Support\Autoloader;

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$loader = new Autoloader(dirname(__DIR__));
$loader->register();

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE counterparties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL,
    counterparty_type TEXT NOT NULL,
    title TEXT NOT NULL,
    status TEXT NOT NULL,
    tax_inn TEXT,
    email TEXT,
    created_by_user_id INTEGER,
    created_at TEXT,
    updated_at TEXT
)');

$users = new UserManagementRepository($pdo);
$service = new CompanyService(
    new CounterpartyRepository($pdo),
    $users,
    new HierarchyPolicy($users)
);
$actor = ['id' => 1, 'is_root' => 1];
$passed = 0;
$failed = 0;

/** @param mixed $actual */
function companyAssertSame(mixed $expected, mixed $actual, string $message): void
{
    global $passed, $failed;

    if ($expected === $actual) {
        $passed++;
        echo "PASS: {$message}\n";
        return;
    }

    $failed++;
    echo "FAIL: {$message}\n";
    echo '  Expected: ' . var_export($expected, true) . PHP_EOL;
    echo '  Actual: ' . var_export($actual, true) . PHP_EOL;
}

echo "=== Company Compatibility Tests ===\n";

$created = $service->create([
    'title' => 'Compatibility test company',
    'tax_number' => '1234567890',
    'email' => 'company-test@example.com',
], $actor);

companyAssertSame('organization', $created['counterparty_type'] ?? null, 'Company uses organization counterparty type');
companyAssertSame('active', $created['status'] ?? null, 'Company defaults to active status');
companyAssertSame('1234567890', $created['tax_number'] ?? null, 'Create maps tax_number to stored tax identifier');
companyAssertSame('company-test@example.com', $created['email'] ?? null, 'Create preserves email');

$updated = $service->update((string)($created['public_id'] ?? ''), [
    'tax_number' => '0987654321',
    'email' => 'company-updated@example.com',
], $actor);

companyAssertSame('0987654321', $updated['tax_number'] ?? null, 'Update maps tax_number to stored tax identifier');
companyAssertSame('company-updated@example.com', $updated['email'] ?? null, 'Update preserves email');

$listed = $service->list(['limit' => 10], $actor);
companyAssertSame('0987654321', $listed['items'][0]['tax_number'] ?? null, 'List returns legacy tax_number field');

echo "=== Results: {$passed} passed, {$failed} failed ===\n";
exit($failed === 0 ? 0 : 1);
