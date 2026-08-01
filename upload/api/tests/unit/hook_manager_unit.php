<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/hook/HookManager.php';

use Api\System\Library\Hook\HookManager;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    // Test 1: dispatch without registered hooks does nothing
    $hm = new HookManager();
    $ctx = ['value' => 0];
    $hm->dispatch('nonexistent', $ctx);
    unitAssert($ctx === ['value' => 0], 'Dispatch on nonexistent hook must not modify context');
    echo '[OK] hook_manager: dispatch without handlers does nothing' . PHP_EOL;

    // Test 2: register and dispatch with context modification
    $hm2 = new HookManager();
    $hm2->register('test.modify', function (array &$ctx): void {
        $ctx['value'] = 42;
    });
    $ctx2 = ['value' => 0];
    $hm2->dispatch('test.modify', $ctx2);
    unitAssert($ctx2['value'] === 42, 'Handler must modify context');
    echo '[OK] hook_manager: register + dispatch modifies context' . PHP_EOL;

    // Test 3: priorities (lower number = higher priority, called first)
    $hm3 = new HookManager();
    $order = [];
    $hm3->register('test.priority', function (array &$ctx) use (&$order): void {
        $order[] = 20;
    }, 20);
    $hm3->register('test.priority', function (array &$ctx) use (&$order): void {
        $order[] = 10;
    }, 10);
    $hm3->register('test.priority', function (array &$ctx) use (&$order): void {
        $order[] = 30;
    }, 30);
    $hm3->dispatch('test.priority');
    unitAssert(
        $order === [10, 20, 30],
        'Handlers must be called in priority order (10, 20, 30). Got: ' . implode(', ', $order)
    );
    echo '[OK] hook_manager: priorities respected' . PHP_EOL;

    // Test 4: error in one handler does not break others
    $hm4 = new HookManager();
    $hm4->register('test.error', function (array &$ctx): void {
        throw new \RuntimeException('Handler 1 failed');
    });
    $hm4->register('test.error', function (array &$ctx): void {
        $ctx['reached'] = true;
    });
    $ctx4 = ['reached' => false];
    $hm4->dispatch('test.error', $ctx4);
    unitAssert($ctx4['reached'] === true, 'Second handler must still run after first throws');
    echo '[OK] hook_manager: error isolation works' . PHP_EOL;

    // Test 5: has() returns correct values
    $hm5 = new HookManager();
    unitAssert($hm5->has('nonexistent') === false, 'has() must return false for unregistered hook');
    $hm5->register('test.exists', function (array &$ctx): void {});
    unitAssert($hm5->has('test.exists') === true, 'has() must return true for registered hook');
    echo '[OK] hook_manager: has() works' . PHP_EOL;

    // Test 6: clear() removes handlers
    $hm6 = new HookManager();
    $hm6->register('test.clearable', function (array &$ctx): void {
        $ctx['called'] = true;
    });
    $hm6->clear('test.clearable');
    unitAssert($hm6->has('test.clearable') === false, 'clear(hookName) must remove handlers');
    $ctx6 = ['called' => false];
    $hm6->dispatch('test.clearable', $ctx6);
    unitAssert($ctx6['called'] === false, 'After clear(), handler must not be called');
    echo '[OK] hook_manager: clear() works' . PHP_EOL;

    // Test 7: clear() all
    $hm7 = new HookManager();
    $hm7->register('test.a', function (array &$ctx): void {});
    $hm7->register('test.b', function (array &$ctx): void {});
    $hm7->clear();
    unitAssert($hm7->has('test.a') === false, 'After clear(all), test.a must be removed');
    unitAssert($hm7->has('test.b') === false, 'After clear(all), test.b must be removed');
    echo '[OK] hook_manager: clear(all) works' . PHP_EOL;

    echo '[OK] hook_manager_unit' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] hook_manager_unit: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
