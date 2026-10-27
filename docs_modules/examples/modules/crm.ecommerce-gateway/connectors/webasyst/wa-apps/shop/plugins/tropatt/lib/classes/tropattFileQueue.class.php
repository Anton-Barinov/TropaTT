<?php

/**
 * Local spool for orders that could not be delivered to the CRM.
 */
class shopTropattFileQueue
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
        $name = date('Ymd-His') . '-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $externalId) . '-' . bin2hex(random_bytes(4)) . '.json';
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

    /**
     * Deliver the orders that are still spooled.
     *
     * No cron is assumed (shared hosting): the spool is drained on the next store
     * event, right before the current one is sent. Before this existed the queue
     * was write-only — an order that failed once was never retried and stayed on
     * disk forever.
     *
     * A payload that keeps failing is moved to `<queue>/failed/` instead of
     * spinning forever, so the store administrator can inspect and replay it.
     *
     * @param callable $sender fn(array $canonical): array{success: bool, ...}
     * @return array{delivered: int, failed: int, dropped: int, remaining: int}
     */
    public static function flush($dir, $sender, $limit = 5, $maxAttempts = 5)
    {
        $result = array('delivered' => 0, 'failed' => 0, 'dropped' => 0, 'remaining' => 0);

        foreach (self::listFiles($dir, $limit) as $path) {
            $canonical = self::read($path);
            if (!is_array($canonical)) {
                self::drop($dir, $path);
                $result['dropped']++;

                continue;
            }

            $attempts = self::attemptsOf($path) + 1;
            $send = $sender($canonical);

            if (!empty($send['success'])) {
                self::remove($path);
                $result['delivered']++;

                continue;
            }

            if ($attempts >= max(1, (int)$maxAttempts)) {
                self::drop($dir, $path);
                $result['dropped']++;

                continue;
            }

            self::retag($path, $attempts);
            $result['failed']++;
        }

        $result['remaining'] = self::count($dir);

        return $result;
    }

    /**
     * Attempts already spent on a spooled payload (recorded in its file name as
     * `...-a2.json`, so no extra metadata files are needed).
     *
     * @return int
     */
    private static function attemptsOf($path)
    {
        return preg_match('/-a([0-9]+)\.json$/', (string)$path, $matches) === 1 ? (int)$matches[1] : 0;
    }

    /**
     * @return void
     */
    private static function retag($path, $attempts)
    {
        $target = preg_replace('/-a[0-9]+\.json$/', '', (string)$path);
        $target = preg_replace('/\.json$/', '', (string)$target) . '-a' . (int)$attempts . '.json';
        if ($target !== (string)$path) {
            @rename((string)$path, $target);
        }
    }

    /**
     * Move a payload out of the active spool (unreadable, or attempts exhausted).
     *
     * @return void
     */
    private static function drop($dir, $path)
    {
        $failedDir = rtrim((string)$dir, '/') . '/failed';
        if (!is_dir($failedDir)) {
            @mkdir($failedDir, 0775, true);
        }

        @rename((string)$path, $failedDir . '/' . basename((string)$path));
    }
}
