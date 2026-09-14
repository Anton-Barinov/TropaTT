<?php
declare(strict_types=1);

/**
 * Audit web translation references against every supported web locale.
 *
 * Usage: php upload/api/scripts/audit_web_i18n.php
 * Exit code 1 means at least one referenced key is absent from a locale.
 */

$projectRoot = dirname(__DIR__, 2);
$webRoot = $projectRoot . '/web';
$languageRoot = $webRoot . '/language';

/** @var array<string, array<int, array{file: string, line: int, fallback: string}>> $references */
$references = [];

/** @var array<string, array<int, string>> $mapIssues Inline JS i18n maps missing a key the script asks for */
$mapIssues = [];

$collect = static function (string $file) use (&$references, &$mapIssues, $projectRoot): void {
    $source = (string)file_get_contents($file);
    $lineAt = static function (int $offset) use ($source): int {
        return substr_count(substr($source, 0, $offset), "\n") + 1;
    };

    if (str_ends_with($file, '.php')) {
        preg_match_all(
            '/\$t\(\s*([\'\"])([^\'\"]+)\1\s*,\s*([\'\"])((?:\\\\.|(?!\3).)*)\3/s',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        foreach ($matches[2] ?? [] as $index => $keyMatch) {
            $key = (string)$keyMatch[0];
            $fallback = (string)($matches[4][$index][0] ?? '');
            $references[$key][] = [
                'file' => substr($file, strlen($projectRoot) + 1),
                'line' => $lineAt((int)$keyMatch[1]),
                'fallback' => $fallback,
            ];
        }

        // Skip attribute values that embed PHP code (dynamic keys) — they are not
        // literal translation keys, so exclude any value containing '<'.
        preg_match_all('/data-i18n(?:-placeholder|-title|-aria-label)?="([^"<]+)"/', $source, $attributeMatches, PREG_OFFSET_CAPTURE);
        foreach ($attributeMatches[1] ?? [] as $keyMatch) {
            $key = (string)$keyMatch[0];
            $references[$key] ??= [];
            $references[$key][] = [
                'file' => substr($file, strlen($projectRoot) + 1),
                'line' => $lineAt((int)$keyMatch[1]),
                'fallback' => '',
            ];
        }

        // Pages that embed their own map for JavaScript
        // (`const i18n = json_encode($auJs, ...)` + `$auJs = [...]`) look strings
        // up with `tr('key', 'русский литерал')`. A key the script asks for but
        // that the map does not carry silently falls back to that Russian
        // literal for every locale — and the locale comparison below cannot see
        // it, because the language file does contain the key. Exactly that
        // happened to the empty-update-channel messages on 2026-09-14.
        if (preg_match_all('/const\s+\w+\s*=\s*<\?=\s*json_encode\(\$(\w+)/', $source, $mapRefs) && $mapRefs[1] !== []) {
            preg_match_all("/\\btr\\(\\s*'([A-Za-z0-9_.]+)'/", $source, $usedKeys);
            $asked = array_values(array_unique($usedKeys[1] ?? []));
            foreach (array_unique($mapRefs[1]) as $mapVar) {
                $arrayStart = strpos($source, '$' . $mapVar . ' = [');
                if ($arrayStart === false) {
                    continue;
                }
                $arrayEnd = strpos($source, '];', $arrayStart);
                if ($arrayEnd === false) {
                    continue;
                }
                $block = substr($source, $arrayStart, $arrayEnd - $arrayStart);
                preg_match_all("/^\\s*'([A-Za-z0-9_.]+)'\\s*=>/m", $block, $definedKeys);
                $unmapped = array_values(array_diff($asked, array_unique($definedKeys[1] ?? [])));
                if ($unmapped !== []) {
                    $mapIssues[substr($file, strlen($projectRoot) + 1)] = $unmapped;
                }
            }
        }
    }

    if (str_ends_with($file, '.js')) {
        preg_match_all(
            '/(?:window\.CRM\.i18n\.t|\b(?:tpFmt|mpT|tp|_t|t|translate))\(\s*([\'\"])([^\'\"]+)\1\s*,\s*([\'\"])((?:\\\\.|(?!\3).)*)\3/s',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        foreach ($matches[2] ?? [] as $index => $keyMatch) {
            $key = (string)$keyMatch[0];
            $fallback = (string)($matches[4][$index][0] ?? '');
            $references[$key][] = [
                'file' => substr($file, strlen($projectRoot) + 1),
                'line' => $lineAt((int)$keyMatch[1]),
                'fallback' => $fallback,
            ];
        }
    }
};

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($webRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || !in_array($fileInfo->getExtension(), ['php', 'js'], true)) {
        continue;
    }
    $path = $fileInfo->getPathname();
    if (str_contains($path, '/language/') || str_contains($path, '/vendor/')) {
        continue;
    }
    $collect($path);
}

$flatten = static function (mixed $value, string $prefix = '') use (&$flatten): array {
    if (!is_array($value)) {
        return $prefix === '' ? [] : [$prefix];
    }
    $keys = [];
    foreach ($value as $name => $child) {
        $key = $prefix === '' ? (string)$name : $prefix . '.' . $name;
        $keys = array_merge($keys, is_array($child) ? $flatten($child, $key) : [$key]);
    }
    return $keys;
};

$failed = false;

// Locale-independent: a key missing from an inline map shows the Russian literal
// in every locale, so it is reported once, before the per-locale comparison.
foreach ($mapIssues as $file => $unmapped) {
    $failed = true;
    echo "[FAIL] {$file}: " . count($unmapped) . " key(s) used by tr() are missing from the inline i18n map\n";
    foreach ($unmapped as $key) {
        echo "  - {$key}\n";
    }
}

foreach (glob($languageRoot . '/*.php') ?: [] as $languageFile) {
    // NOTE: never name this variable `$locale` — required language files
    // (js_overrides.php) run top-level `foreach (... as $locale => ...)` loops
    // that would silently clobber it and mislabel every failure as the last
    // locale in the file.
    $localeName = basename($languageFile, '.php');
    if (in_array($localeName, ['overrides', 'js_overrides'], true)) {
        continue;
    }
    $messages = require $languageFile;
    if (!is_array($messages)) {
        $messages = [];
    }
    foreach (['overrides.php', 'js_overrides.php'] as $supplementalName) {
        $supplementalPath = $languageRoot . '/' . $supplementalName;
        if (!is_file($supplementalPath)) {
            continue;
        }
        $supplemental = require $supplementalPath;
        if (!is_array($supplemental)) {
            continue;
        }
        if (is_array($supplemental['ru-ru'] ?? null)) {
            $messages = array_replace_recursive($messages, $supplemental['ru-ru']);
        }
        if ($localeName !== 'ru-ru' && is_array($supplemental[$localeName] ?? null)) {
            $messages = array_replace_recursive($messages, $supplemental[$localeName]);
        }
    }
    $available = array_fill_keys($flatten($messages), true);
    $missing = array_diff(array_keys($references), array_keys($available));
    if ($missing === []) {
        echo "[OK] {$localeName}: all " . count($references) . " referenced keys are present\n";
        continue;
    }

    $failed = true;
    echo "[FAIL] {$localeName}: missing " . count($missing) . " key(s)\n";
    foreach ($missing as $key) {
        $reference = $references[$key][0] ?? ['file' => '', 'line' => 0, 'fallback' => ''];
        printf("  - %s (%s:%d)\n", $key, $reference['file'], $reference['line']);
    }
}

if ($mapIssues === []) {
    echo "[OK] inline JS i18n maps cover every tr() key\n";
}

exit($failed ? 1 : 0);
