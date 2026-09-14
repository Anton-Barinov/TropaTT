<?php
declare(strict_types=1);

/**
 * Audit API translation references and hardcoded user-facing messages.
 *
 * Usage: php upload/api/scripts/audit_api_i18n.php
 * Exit code 1 means at least one locale is missing a referenced key, or a
 * controller answers a user-facing message as a literal string.
 *
 * This is the API-side counterpart of audit_web_i18n.php. Two checks:
 *
 *  1. Every key referenced as `->t('group/messages.key')` (or
 *     `$lang->get('group/messages.key')`) exists in all seven API locales.
 *     LanguageManager loads the fallback locale first and then the requested
 *     one into the same catalog and, when a key is absent everywhere, returns
 *     the inline default or the raw key — so a missing key is invisible in
 *     review and silently ships English (or "group/messages.key") to every
 *     non-English user. The reference sweep found exactly that: ten keys were
 *     referenced but existed in no locale at all.
 *
 *  2. No controller answers a user-facing message with a literal sentence
 *     (`JsonResponse::error`/`success` and `$this->error`/`success`). A
 *     literal cannot be translated and no language file can ever fix it.
 *
 *     The MCP controller is excluded on purpose: its tool descriptions and
 *     its argument-validation messages are English by design, because they are
 *     read by agents over the protocol rather than by a person in the UI.
 *     Its messages are also not responses of the API envelope. Every MCP
 *     message is therefore reported as "excluded", not as "clean".
 */

$projectRoot = dirname(__DIR__, 2);
$apiRoot = $projectRoot . '/api';
$controllerRoot = $apiRoot . '/controller';
$languageRoot = $apiRoot . '/language';

$locales = ['ru-ru', 'en-gb', 'de-de', 'es-es', 'fr-fr', 'pt-br', 'zh-cn'];

/** Directories whose PHP files are not part of the shipped runtime. */
$skipPath = static function (string $path): bool {
    foreach (['/language/', '/tests/', '/vendor/', '/node_modules/'] as $marker) {
        if (str_contains($path, $marker)) {
            return true;
        }
    }
    return false;
};

$lineAt = static function (string $source, int $offset): int {
    return substr_count(substr($source, 0, $offset), "\n") + 1;
};

// ---------------------------------------------------------------------------
// Check 1 — referenced translation keys must exist in all seven locales
// ---------------------------------------------------------------------------

/** @var array<string, array<int, array{file: string, line: int}>> $references */
$references = [];

$collectReferences = static function (string $file) use (&$references, $projectRoot, $lineAt): void {
    $source = (string)file_get_contents($file);
    $patterns = [
        // $this->t('domain/messages.key', 'fallback')  /  self::t(...) / $x->t(...)
        '/->t\(\s*([\'"])([A-Za-z0-9_\-]+(?:\/[A-Za-z0-9_\-]+)*\.[A-Za-z0-9_\-]+)\1/s',
        // $lang->get('domain/messages.key')
        '/lang->get\(\s*([\'"])([A-Za-z0-9_\-]+(?:\/[A-Za-z0-9_\-]+)*\.[A-Za-z0-9_\-]+)\1/s',
    ];
    foreach ($patterns as $pattern) {
        preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[2] ?? [] as $keyMatch) {
            $key = (string)$keyMatch[0];
            $references[$key][] = [
                'file' => substr($file, strlen($projectRoot) + 1),
                'line' => $lineAt($source, (int)$keyMatch[1]),
            ];
        }
    }
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($apiRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || $fileInfo->getExtension() !== 'php') {
        continue;
    }
    $path = $fileInfo->getPathname();
    if ($skipPath($path) || $path === __FILE__) {
        continue;
    }
    $collectReferences($path);
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

/**
 * Load one translation group for one locale. The require runs inside a closure
 * so a language file that happens to iterate over a `$locale` variable cannot
 * clobber the loop variable of this script.
 */
$loadGroup = static function (string $languageRoot, string $localeName, string $group): ?array {
    $return = static function (string $path): mixed {
        return require $path;
    };
    foreach ([
        $languageRoot . '/' . $localeName . '/' . $group . '.php',
        $languageRoot . '/' . $localeName . '/' . $group . '/messages.php',
    ] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $data = $return($path);

        return is_array($data) ? $data : [];
    }

    return null;
};

$failed = false;
$referencedTotal = count($references);

echo 'API translation audit: ' . $referencedTotal . " referenced key(s)\n";

/** @var array<string, array<string, true>> $catalogCache */
$catalogCache = [];
foreach ($locales as $localeName) {
    $missingByGroup = [];
    $missing = [];
    foreach (array_keys($references) as $key) {
        $separator = strpos($key, '.');
        if ($separator === false) {
            continue;
        }
        $group = substr($key, 0, $separator);
        $name = substr($key, $separator + 1);
        $cacheKey = $localeName . '|' . $group;
        if (!array_key_exists($cacheKey, $catalogCache)) {
            $data = $loadGroup($languageRoot, $localeName, $group);
            $catalogCache[$cacheKey] = $data === null
                ? null
                : array_fill_keys($flatten($data), true);
        }
        $catalog = $catalogCache[$cacheKey];
        if ($catalog === null) {
            $missing[] = $key;
            $missingByGroup[$group . ' (file missing)'] = true;
            continue;
        }
        if (!isset($catalog[$name])) {
            $missing[] = $key;
        }
    }

    if ($missing === []) {
        echo "[OK] api/{$localeName}: all {$referencedTotal} referenced keys are present\n";
        continue;
    }

    $failed = true;
    echo "[FAIL] api/{$localeName}: missing " . count($missing) . " key(s)\n";
    foreach ($missing as $key) {
        $reference = $references[$key][0] ?? ['file' => '', 'line' => 0];
        printf("  - %s (%s:%d)\n", $key, $reference['file'], $reference['line']);
    }
}

// ---------------------------------------------------------------------------
// Check 2 — controllers must not answer user-facing messages as literals
// ---------------------------------------------------------------------------

$excludedFromLiteralCheck = $controllerRoot . '/mcp';
$literalCalls = 0;
$excludedCalls = 0;
$literalViolations = [];

$scanLiterals = static function (string $file) use (&$literalCalls, &$literalViolations, $lineAt, $projectRoot): void {
    $source = (string)file_get_contents($file);
    // `JsonResponse::error('CODE', 'literal message'…` and the controller
    // helpers `$this->error('CODE', 'literal message'…` alike.
    $pattern = '/(?:JsonResponse::(?:error|success)|\$this->(?:error|success))\s*\(\s*([\'"])([A-Z_0-9]+)\1\s*,\s*([\'"])((?:\\\\.|(?!\3).)*)\3/s';
    preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);
    foreach ($matches[4] ?? [] as $index => $messageMatch) {
        $message = (string)$messageMatch[0];
        $literalCalls++;
        // A placeholder that carries no user-facing text ('OK', '', 'SUCCESS')
        // is not a translation gap: the envelope code already says everything.
        if (!preg_match('/\s/u', $message) || !preg_match('/\p{L}/u', $message)) {
            continue;
        }
        $literalViolations[] = [
            'file' => substr($file, strlen($projectRoot) + 1),
            'line' => $lineAt($source, (int)$messageMatch[1]),
            'code' => (string)($matches[2][$index][0] ?? ''),
            'message' => $message,
        ];
    }
};

$controllerIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($controllerRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($controllerIterator as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || $fileInfo->getExtension() !== 'php') {
        continue;
    }
    $path = $fileInfo->getPathname();
    if ($skipPath($path)) {
        continue;
    }
    if (str_starts_with($path, $excludedFromLiteralCheck)) {
        $excludedCalls++;
        continue;
    }
    $scanLiterals($path);
}

if ($literalViolations === []) {
    echo "[OK] controllers: {$literalCalls} user-facing message(s), none hardcoded\n";
} else {
    $failed = true;
    echo '[FAIL] controllers: ' . count($literalViolations) . " hardcoded user-facing message(s)\n";
    foreach ($literalViolations as $violation) {
        printf(
            "  - %s:%d  %s -> %s\n",
            $violation['file'],
            $violation['line'],
            $violation['code'],
            $violation['message']
        );
    }
}

echo "[SKIP] controller/mcp: {$excludedCalls} file(s) excluded — MCP tool messages are English by design\n";

exit($failed ? 1 : 0);
