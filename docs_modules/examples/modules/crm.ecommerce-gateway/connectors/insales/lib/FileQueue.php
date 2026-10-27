<?php

namespace Tropatt\InSales;

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
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return '';
        }

        $externalId = isset($canonical['external_id']) ? (string)$canonical['external_id'] : '';
        $name = date('Ymd-His') . '-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $externalId) . '-' . bin2hex(random_bytes(4)) . '.json';
        $path = rtrim($dir, '/') . '/' . $name;

        return @file_put_contents($path, json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false ? '' : $path;
    }

    /**
     * @return array
     */
    public static function listFiles($dir = null, $limit = 50)
    {
        $dir = $dir === null ? Config::queueDir() : $dir;
        if (!is_dir($dir)) {
            return array();
        }

        $files = glob(rtrim($dir, '/') . '/*.json');
        if ($files === false) {
            return array();
        }

        sort($files);

        return array_slice($files, 0, max(1, (int)$limit));
    }

    /**
     * @return array|null
     */
    public static function read($path)
    {
        $raw = @file_get_contents($path);
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
        return @unlink($path);
    }

    /**
     * @return int
     */
    public static function count($dir = null)
    {
        $dir = $dir === null ? Config::queueDir() : $dir;
        if (!is_dir($dir)) {
            return 0;
        }

        $files = glob(rtrim($dir, '/') . '/*.json');

        return $files === false ? 0 : count($files);
    }
}
