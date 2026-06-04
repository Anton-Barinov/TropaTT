<?php
declare(strict_types=1);

namespace Api\System\Library\Support;

final class EnvLoader
{
    /** @var array<string,bool> */
    private static array $loadedKeys = [];

    /**
     * @param array<int,string> $files
     */
    public static function loadFiles(array $files): void
    {
        foreach ($files as $file) {
            self::loadFile($file);
        }
    }

    public static function loadFile(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $entry = self::parseLine((string)$line);
            if ($entry === null) {
                continue;
            }

            [$key, $value] = $entry;
            $alreadyExternal = getenv($key) !== false
                && !isset(self::$loadedKeys[$key]);
            if ($alreadyExternal) {
                continue;
            }

            self::$loadedKeys[$key] = true;
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $equalsPos = strpos($line, '=');
        if ($equalsPos === false) {
            return null;
        }

        $key = trim(substr($line, 0, $equalsPos));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            return null;
        }

        $value = trim(substr($line, $equalsPos + 1));
        return [$key, self::parseValue($value)];
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $quote = $value[0];
        if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
            $value = substr($value, 1, -1);
            if ($quote === '"') {
                $value = strtr($value, [
                    '\\n' => "\n",
                    '\\r' => "\r",
                    '\\t' => "\t",
                    '\\"' => '"',
                    '\\\\' => '\\',
                ]);
            }

            return $value;
        }

        return trim((string)preg_replace('/\s+#.*$/', '', $value));
    }
}
