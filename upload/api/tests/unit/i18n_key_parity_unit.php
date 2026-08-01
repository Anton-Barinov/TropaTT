<?php
declare(strict_types=1);

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function flattenKeys(array $array, string $prefix = ''): array
{
    $keys = [];
    foreach ($array as $key => $value) {
        $fullKey = $prefix === '' ? (string)$key : $prefix . '.' . (string)$key;
        if (is_array($value)) {
            $keys = array_merge($keys, flattenKeys($value, $fullKey));
        } else {
            $keys[] = $fullKey;
        }
    }
    return $keys;
}

try {
    $webRoot = dirname(__DIR__, 3) . '/web';

    $en = require $webRoot . '/language/en-gb.php';
    $ru = require $webRoot . '/language/ru-ru.php';

    unitAssert(is_array($en), 'en-gb.php must return array');
    unitAssert(is_array($ru), 'ru-ru.php must return array');

    $enKeys = flattenKeys($en);
    $ruKeys = flattenKeys($ru);

    $onlyInEn = array_diff($enKeys, $ruKeys);
    $onlyInRu = array_diff($ruKeys, $enKeys);

    unitAssert(
        count($onlyInEn) === 0,
        'Keys present in en-gb.php but missing in ru-ru.php: ' . implode(', ', $onlyInEn)
    );
    unitAssert(
        count($onlyInRu) === 0,
        'Keys present in ru-ru.php but missing in en-gb.php: ' . implode(', ', $onlyInRu)
    );

    echo '[OK] i18n_key_parity_unit: en-gb ↔ ru-ru keys match (' . count($enKeys) . ' keys)' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] i18n_key_parity_unit: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
