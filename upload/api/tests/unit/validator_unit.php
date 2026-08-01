<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/validation/Validator.php';

use Api\System\Library\Validation\Validator;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $validator = new Validator();

    $validator
        ->require(['title' => ''], 'title', 'required')
        ->maxLen(['title' => str_repeat('a', 4)], 'title', 3, 'too_long')
        ->enum(['status' => 'bad'], 'status', ['ok', 'new'], 'bad_enum')
        ->date(['due' => 'not-a-date'], 'due', 'bad_date');

    unitAssert($validator->fails() === true, 'Validator must fail');
    $errors = $validator->errors();

    unitAssert(isset($errors['title']) && count($errors['title']) === 2, 'Title must contain 2 errors');
    unitAssert(isset($errors['status'][0]) && $errors['status'][0] === 'bad_enum', 'Status enum error must exist');
    unitAssert(isset($errors['due'][0]) && $errors['due'][0] === 'bad_date', 'Date error must exist');

    $validator2 = new Validator();
    $validator2
        ->require(['title' => 'ok'], 'title', 'required')
        ->maxLen(['title' => 'ok'], 'title', 10, 'too_long')
        ->enum(['status' => 'ok'], 'status', ['ok', 'new'], 'bad_enum')
        ->enum(['status' => ''], 'status', ['ok', 'new'], 'bad_enum')
        ->date(['due' => '2026-04-18'], 'due', 'bad_date')
        ->date(['due' => ''], 'due', 'bad_date');

    unitAssert($validator2->fails() === false, 'Validator must pass valid payload and optional empty enum/date');

    echo "[OK] validator_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] validator_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
