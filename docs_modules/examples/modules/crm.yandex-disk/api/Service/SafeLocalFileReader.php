<?php
declare(strict_types=1);

namespace Module\Crm\YandexDisk\Service;

use RuntimeException;

/** Best-effort race-resistant bounded reader for an already-authorized CRM file. */
final class SafeLocalFileReader
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    public static function read(string $path, int $expectedSize, string $storageRoot): string
    {
        if ($path === '' || $expectedSize < 0 || $expectedSize > self::MAX_BYTES) throw new RuntimeException('File size is invalid or exceeds 20 MB.');
        $root = realpath($storageRoot);
        $rootLexical = rtrim(str_replace('\\', DIRECTORY_SEPARATOR, $storageRoot), DIRECTORY_SEPARATOR);
        if ($root === false || !is_dir($root) || $rootLexical === '') throw new RuntimeException('CRM file storage root is unavailable.');
        $inputPath = str_replace('\\', '/', trim($path));
        if ($inputPath === '' || preg_match('/[\x00-\x1F\x7F]/', $inputPath)) throw new RuntimeException('File path is invalid.');
        foreach (explode('/', $inputPath) as $segment) {
            if ($segment === '.' || $segment === '..') throw new RuntimeException('File path traversal is not allowed.');
        }
        if (str_starts_with($inputPath, '/')) {
            $candidate = $inputPath;
        } else {
            $candidate = $rootLexical . DIRECTORY_SEPARATOR . $inputPath;
        }
        $rootLexicalPrefix = $rootLexical . DIRECTORY_SEPARATOR;
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $candidateNormalized = str_replace('\\', DIRECTORY_SEPARATOR, $candidate);
        if (!str_starts_with($candidateNormalized, $rootLexicalPrefix)) throw new RuntimeException('File is outside CRM storage.');
        $relative = substr($candidateNormalized, strlen($rootLexicalPrefix));
        $cursor = $rootLexical;
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            if ($segment === '') throw new RuntimeException('File path is invalid.');
            $cursor .= DIRECTORY_SEPARATOR . $segment;
            clearstatcache(true, $cursor);
            if (is_link($cursor)) throw new RuntimeException('Symlinks are not allowed in CRM file paths.');
        }
        clearstatcache(true, $candidateNormalized);
        $before = @lstat($candidateNormalized);
        $real = realpath($candidateNormalized);
        if (!is_array($before) || $real === false || !str_starts_with($real, $rootPrefix) || !is_file($real) || !is_readable($real)) throw new RuntimeException('File is unavailable, unsafe, or outside CRM storage.');
        $resolvedBefore = @lstat($real);
        if (!self::sameRegularFile($before, $resolvedBefore) || (int)$before['size'] !== $expectedSize || !self::hasMemoryBudget($expectedSize)) throw new RuntimeException('File changed, does not match its CRM metadata, or exceeds this host memory budget.');

        $stream = @fopen($real, 'rb');
        if (!is_resource($stream)) throw new RuntimeException('File could not be opened safely.');
        try {
            $opened = fstat($stream);
            if (!self::sameRegularFile($resolvedBefore, $opened) || (int)$opened['size'] !== $expectedSize) throw new RuntimeException('File changed before it could be read.');
            $bytes = '';
            while (!feof($stream)) {
                $chunk = fread($stream, min(65536, self::MAX_BYTES + 1 - strlen($bytes)));
                if ($chunk === false) throw new RuntimeException('File read failed.');
                if ($chunk === '' && !feof($stream)) throw new RuntimeException('File read stalled.');
                $bytes .= $chunk;
                if (strlen($bytes) > self::MAX_BYTES || strlen($bytes) > $expectedSize) throw new RuntimeException('File grew beyond its authorized size.');
            }
            $openedAfter = fstat($stream);
        } finally {
            fclose($stream);
        }

        clearstatcache(true, $candidateNormalized);
        $after = @lstat($candidateNormalized);
        $resolvedAfter = realpath($candidateNormalized);
        $afterStat = $resolvedAfter !== false ? @lstat($resolvedAfter) : false;
        if (strlen($bytes) !== $expectedSize || $resolvedAfter === false || !str_starts_with($resolvedAfter, $rootPrefix) || !self::sameRegularFile($opened, $openedAfter)
            || (int)$openedAfter['size'] !== $expectedSize || (int)$opened['mtime'] !== (int)$openedAfter['mtime'] || (int)$opened['ctime'] !== (int)$openedAfter['ctime'] || !self::sameRegularFile($before, $after)
            || $resolvedAfter !== $real || !self::sameRegularFile($openedAfter, $afterStat)) {
            throw new RuntimeException('File changed while it was being read.');
        }

        return $bytes;
    }

    private static function sameRegularFile(array|false $left, array|false $right): bool
    {
        if (!is_array($left) || !is_array($right)) return false;
        return (($left['mode'] & 0170000) === 0100000) && (($right['mode'] & 0170000) === 0100000)
            && $left['dev'] === $right['dev'] && $left['ino'] === $right['ino'];
    }

    private static function hasMemoryBudget(int $sizeBytes): bool
    {
        $limit = trim((string)ini_get('memory_limit'));
        if ($limit === '' || $limit === '-1') return true;
        if (!preg_match('/^(\d+)\s*([KMG]?)$/i', $limit, $matches)) return false;
        $limitBytes = (int)$matches[1] * match (strtoupper($matches[2])) { 'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024, default => 1 };
        return memory_get_usage(true) + ($sizeBytes * 3) + (4 * 1024 * 1024) < $limitBytes;
    }
}
