<?php

namespace Tropatt\Crm;

/**
 * HMAC-SHA256 transport security, identical to the other TropaTT connectors:
 *
 *   canonical = METHOD \n path \n timestamp \n nonce \n hex(sha256(raw_body))
 *   signature = base64(HMAC-SHA256(store_secret, canonical))
 *
 * Incoming CRM webhooks are signed as base64(HMAC-SHA256(secret, timestamp . '.' . body)).
 */
class SignatureValidator
{
    const TOLERANCE_SECONDS = 300;

    /** @var string */
    const EMPTY_BODY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    /**
     * @return string
     */
    public static function canonicalString($method, $path, $timestamp, $nonce, $rawBody)
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
    public static function sign($canonical, $secret)
    {
        return base64_encode(hash_hmac('sha256', (string)$canonical, (string)$secret, true));
    }

    /**
     * Signature for an outgoing request.
     *
     * @return string
     */
    public static function signRequest($method, $path, $timestamp, $nonce, $rawBody, $secret)
    {
        return self::sign(self::canonicalString($method, $path, $timestamp, $nonce, $rawBody), $secret);
    }

    /**
     * Signature of an incoming CRM webhook.
     *
     * @return string
     */
    public static function signWebhook($timestamp, $rawBody, $secret)
    {
        return base64_encode(hash_hmac('sha256', (string)$timestamp . '.' . (string)$rawBody, (string)$secret, true));
    }

    /**
     * @return bool
     */
    public static function withinTolerance($timestamp, $now = null)
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
    public static function verifyWebhook($timestamp, $rawBody, $signature, $secret)
    {
        if ($secret === '' || $signature === '' || $timestamp === '') {
            return false;
        }

        return self::secureEquals(self::signWebhook($timestamp, $rawBody, $secret), $signature);
    }

    /**
     * Constant-time comparison with a fallback for PHP < 5.6.
     *
     * @return bool
     */
    public static function secureEquals($known, $given)
    {
        $known = (string)$known;
        $given = (string)$given;

        if (function_exists('hash_equals')) {
            return hash_equals($known, $given);
        }

        if (strlen($known) !== strlen($given)) {
            return false;
        }

        $diff = 0;
        for ($i = 0, $length = strlen($known); $i < $length; $i++) {
            $diff |= ord($known[$i]) ^ ord($given[$i]);
        }

        return $diff === 0;
    }

    /**
     * Nonce for an outgoing request (32 hex characters).
     *
     * @return string
     */
    public static function nonce()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16));
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(16));
        }

        return md5(uniqid((string)mt_rand(), true));
    }
}
