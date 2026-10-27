<?php

namespace Tropatt\Crm\Model;

/**
 * HMAC-SHA256 signing, identical to the other TropaTT connectors.
 */
class Signature
{
    public const TOLERANCE_SECONDS = 300;

    /**
     * @return string
     */
    public function canonicalString($method, $path, $timestamp, $nonce, $rawBody)
    {
        return strtoupper((string)$method) . "\n"
            . (string)$path . "\n"
            . (string)$timestamp . "\n"
            . (string)$nonce . "\n"
            . hash('sha256', (string)$rawBody);
    }

    /**
     * @return string
     */
    public function signRequest($method, $path, $timestamp, $nonce, $rawBody, $secret)
    {
        return base64_encode(hash_hmac('sha256', $this->canonicalString($method, $path, $timestamp, $nonce, $rawBody), (string)$secret, true));
    }

    /**
     * @return string
     */
    public function signWebhook($timestamp, $rawBody, $secret)
    {
        return base64_encode(hash_hmac('sha256', (string)$timestamp . '.' . (string)$rawBody, (string)$secret, true));
    }

    /**
     * @return bool
     */
    public function withinTolerance($timestamp, $now = null)
    {
        if (!is_numeric($timestamp)) {
            return false;
        }

        $now = $now === null ? time() : (int)$now;

        return abs($now - (int)$timestamp) <= self::TOLERANCE_SECONDS;
    }

    /**
     * @return bool
     */
    public function verifyWebhook($timestamp, $rawBody, $signature, $secret)
    {
        if ($secret === '' || $signature === '' || $timestamp === '') {
            return false;
        }

        return hash_equals($this->signWebhook($timestamp, $rawBody, $secret), (string)$signature);
    }

    /**
     * @return string
     */
    public function nonce()
    {
        return bin2hex(random_bytes(16));
    }
}
