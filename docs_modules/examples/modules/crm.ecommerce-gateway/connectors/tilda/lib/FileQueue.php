<?php

namespace Tropatt\Tilda;

/**
 * Local spool for orders that could not be forwarded to the CRM.
 *
 * The webhook answers Tilda immediately (< 100 ms) and forwards afterwards; if
 * the CRM is unavailable the payload is written here and `cron/retry_queue.php`
 * delivers it later, so a Tilda cart is never lost.
 */
class FileQueue
{
    /**
     * @return string
     */
    public static function enqueue(array $canonical, $dir = null)
    {
        $dir = $dir === null ? Config::queueDir() : $dir;
        if (is_link($dir) || (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) || is_link($dir)) {
            return '';
        }
        @chmod($dir, 0700);
        $realDir = realpath($dir);
        if ($realDir === false || !is_dir($realDir)) {
            return '';
        }

        $externalId = isset($canonical['external_id']) ? (string)$canonical['external_id'] : '';
        $name = date('Ymd-His') . '-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $externalId) . '-' . bin2hex(random_bytes(4)) . '.json';
        $path = rtrim($realDir, '/\\') . DIRECTORY_SEPARATOR . $name;
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) > 1048576) {
            return '';
        }
        // Exclusive create prevents overwriting a pre-existing file or following
        // a planted symlink. The random suffix also makes collisions negligible.
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            return '';
        }
        @chmod($path, 0600);
        $written = fwrite($handle, $json);
        fflush($handle);
        fclose($handle);
        if ($written !== strlen($json)) {
            @unlink($path);
            return '';
        }

        return $path;
    }

    /**
     * @return array
     */
    public static function listFiles($dir = null, $limit = 50)
    {
        $dir = $dir === null ? Config::queueDir() : $dir;
        if (is_link($dir) || !is_dir($dir)) {
            return array();
        }

        $files = glob(rtrim($dir, '/') . '/*.json');
        if ($files === false) {
            return array();
        }

        sort($files);

        $safe = array();
        $realDir = realpath($dir);
        if ($realDir === false) {
            return array();
        }
        foreach ($files as $file) {
            if (is_link($file) || realpath($file) === false || dirname(realpath($file)) !== $realDir) {
                continue;
            }
            $safe[] = $file;
        }

        return array_slice($safe, 0, max(1, min(500, (int)$limit)));
    }

    /**
     * @return array|null
     */
    public static function read($path)
    {
        $dir = Config::queueDir();
        $realDir = realpath($dir);
        if ($realDir === false || is_link($path) || !is_file($path)) {
            return null;
        }
        $realPath = realpath($path);
        if ($realPath === false || dirname($realPath) !== $realDir || !preg_match('/^[0-9]{8}-[0-9]{6}-[A-Za-z0-9_.-]*-[a-f0-9]{8}\.json$/D', basename($realPath))) {
            return null;
        }
        $size = filesize($realPath);
        if ($size === false || $size > 1048576) {
            return null;
        }
        $raw = @file_get_contents($realPath);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @return bool
     */
    public static function remove($path)
    {
        $dir = Config::queueDir();
        $realDir = realpath($dir);
        $realPath = is_link($path) ? false : realpath($path);
        if ($realDir === false || $realPath === false || dirname($realPath) !== $realDir || !preg_match('/^[0-9]{8}-[0-9]{6}-[A-Za-z0-9_.-]*-[a-f0-9]{8}\.json$/D', basename($realPath))) {
            return false;
        }

        return @unlink($realPath);
    }

    /**
     * @return int
     */
    public static function count($dir = null)
    {
        $dir = $dir === null ? Config::queueDir() : $dir;
        if (is_link($dir) || !is_dir($dir)) {
            return 0;
        }

        $files = glob(rtrim($dir, '/') . '/*.json');

        return $files === false ? 0 : count($files);
    }
}
