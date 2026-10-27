<?php

/**
 * Local spool for orders that could not be delivered to the CRM.
 */
class TropattFileQueue
{
    /**
     * @return string
     */
    public static function enqueue(array $canonical, $dir)
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return '';
        }

        $externalId = isset($canonical['external_id']) ? (string)$canonical['external_id'] : '';
        $name = date('Ymd-His') . '-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $externalId) . '-' . bin2hex(TropattSignature::nonce()) . '.json';
        $path = rtrim($dir, '/') . '/' . $name;

        return @file_put_contents($path, (string)json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false ? '' : $path;
    }

    /**
     * @return array
     */
    public static function listFiles($dir, $limit = 50)
    {
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
    public static function count($dir)
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $files = glob(rtrim($dir, '/') . '/*.json');

        return $files === false ? 0 : count($files);
    }
}
