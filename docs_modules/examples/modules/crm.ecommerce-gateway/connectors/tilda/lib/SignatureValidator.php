<?php

namespace Tropatt\Tilda;

/**
 * HMAC-SHA256 signing for the TropaTT gateway, identical to the other connectors:
 *
 *   canonical = METHOD \n path \n timestamp \n nonce \n hex(sha256(raw_body))
 *   signature = base64(HMAC-SHA256(store_secret, canonical))
 */
class SignatureValidator
{
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
    public static function signRequest($method, $path, $timestamp, $nonce, $rawBody, $secret)
    {
        return base64_encode(hash_hmac(
            'sha256',
            self::canonicalString($method, $path, $timestamp, $nonce, $rawBody),
            (string)$secret,
            true
        ));
    }

    /**
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
}
