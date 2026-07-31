<?php
declare(strict_types=1);

namespace Api\System\Library\Support;

final class EnvLoader
{
    /** @var array<string,bool> */
    private static array $loadedKeys = [];

    /**
     * Pattern matching $_SERVER/PHP-supplied keys that must never be
     * overwritten by .env values. Covers HTTP_* headers (Host Header
     * Injection, Authorization spoofing), REQUEST_* and SERVER_* transport
     * variables, REMOTE_* and SSL_* protocol info. Anything else in this
     * prefix family is also rejected.
     */
    private const RESERVED_SERVER_KEY_PATTERN = '/^(HTTP_|REQUEST_|SERVER_|REMOTE_|REDIRECT_|CONTENT_|SSL_|PATH_|SCRIPT_|PHP_|DOCUMENT_|AUTH_|ORIG_|CONTEXT_|FCGI_)/i';

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

        if (str_ends_with($file, '.php')) {
            self::loadPhpFile($file);
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

            // Skip keys that would shadow $_SERVER-supplied values (HTTP_*,
            // REQUEST_*, SERVER_*, etc.) to prevent Host Header Injection,
            // Authorization spoofing, and other protocol-level attacks from
            // a writable .env. Covers many more prefixes than an enumerative
            // denylist could keep up with (HTTP_X_REAL_IP, HTTP_CLIENT_IP,
            // HTTP_ACCEPT_*, etc.).
            if (preg_match(self::RESERVED_SERVER_KEY_PATTERN, $key) === 1) {
                error_log(sprintf(
                    'EnvLoader: refused to overwrite reserved server key "%s" from .env',
                    $key
                ));
                self::$loadedKeys[$key] = true;
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

    private static function loadPhpFile(string $file): void
    {
        if (!defined('CRM_ACCESS')) {
            define('CRM_ACCESS', true);
        }

        /** @var array<string,string> $envData */
        $envData = require $file;
        if (!is_array($envData)) {
            return;
        }

        foreach ($envData as $key => $value) {
            if (!is_string($key) || $value === null) {
                continue;
            }

            $alreadyExternal = getenv($key) !== false
                && !isset(self::$loadedKeys[$key]);
            if ($alreadyExternal) {
                continue;
            }

            // Skip keys that would shadow $_SERVER-supplied values (HTTP_*,
            // REQUEST_*, etc.) — prevents Host Header Injection and other
            // protocol-level attacks from a writable local .php config file.
            if (preg_match(self::RESERVED_SERVER_KEY_PATTERN, $key) === 1) {
                error_log(sprintf(
                    'EnvLoader: refused to overwrite reserved server key "%s" from local .php config',
                    $key
                ));
                self::$loadedKeys[$key] = true;
                continue;
            }

            self::$loadedKeys[$key] = true;
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
