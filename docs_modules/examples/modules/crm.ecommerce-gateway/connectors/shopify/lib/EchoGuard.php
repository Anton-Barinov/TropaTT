<?php

namespace Tropatt\Shopify;

/**
 * File-backed guard with two jobs, both needed by the CRM→Shopify receiver:
 *
 *  - echo suppression: fulfilling or cancelling an order makes Shopify fire
 *    `orders/updated`, which would push the order straight back to the CRM and
 *    can bounce a stage back and forth. Orders the connector changed are marked
 *    for a short window and `public/webhook.php` skips forwarding them;
 *  - replay protection: the same signed CRM packet must not be applied twice
 *    inside the timestamp tolerance window.
 *
 * Files live in `var/echo/` and expire on their own, so a crash cannot leave a
 * permanent suppression behind.
 */
class EchoGuard
{
    const TTL_SECONDS = 600;

    /**
     * Remember that this connector changed the order (suppress the echo).
     *
     * @return bool
     */
    public static function markOrder($orderGid, $dir = null, $now = null)
    {
        $key = 'order-' . sha1((string)$orderGid);

        return self::write($key, $dir, $now);
    }

    /**
     * @return bool
     */
    public static function isOrderSuppressed($orderGid, $dir = null, $now = null)
    {
        return !self::expired('order-' . sha1((string)$orderGid), $dir, $now);
    }

    /**
     * @return bool
     */
    public static function markPacket($signature, $timestamp, $dir = null, $now = null)
    {
        return self::write('packet-' . sha1((string)$timestamp . '|' . (string)$signature), $dir, $now);
    }

    /**
     * True when this exact signed packet was already applied.
     *
     * @return bool
     */
    public static function isPacketReplayed($signature, $timestamp, $dir = null, $now = null)
    {
        return !self::expired('packet-' . sha1((string)$timestamp . '|' . (string)$signature), $dir, $now);
    }

    /**
     * @return string
     */
    public static function dir($dir = null)
    {
        return $dir === null ? rtrim(Config::queueDir(), '/') . '/echo' : (string)$dir;
    }

    /**
     * @return bool
     */
    private static function write($key, $dir = null, $now = null)
    {
        $dir = self::dir($dir);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $now = $now === null ? time() : (int)$now;

        return @file_put_contents(rtrim($dir, '/') . '/' . $key, (string)($now + self::TTL_SECONDS), LOCK_EX) !== false;
    }

    /**
     * @return bool true when the marker is absent or stale
     */
    private static function expired($key, $dir = null, $now = null)
    {
        $path = rtrim(self::dir($dir), '/') . '/' . $key;

        if (!is_file($path)) {
            return true;
        }

        $now = $now === null ? time() : (int)$now;
        $expiresAt = (int)@file_get_contents($path);

        if ($expiresAt > $now) {
            return false;
        }

        @unlink($path);

        return true;
    }
}
