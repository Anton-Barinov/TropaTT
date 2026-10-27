<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

/**
 * Signing contract for store-to-CRM requests (E-COM-01 §4.2).
 *
 * canonical = METHOD \n request_path \n timestamp \n nonce \n hex(sha256(raw_body))
 * signature = base64(HMAC-SHA256(store_secret, canonical))
 *
 * The class is intentionally stateless and dependency-free so it can be unit
 * tested without a database.
 */
final class SignatureService
{
    public const HEADER_STORE_KEY = 'X-Store-Key';
    public const HEADER_TIMESTAMP = 'X-TropaTT-Timestamp';
    public const HEADER_NONCE = 'X-TropaTT-Nonce';
    public const HEADER_SIGNATURE = 'X-TropaTT-Signature';
    public const HEADER_IDEMPOTENCY = 'X-TropaTT-Idempotency-Key';

    public const DEFAULT_TIMESTAMP_TOLERANCE = 300;

    /** sha256 of an empty body — used for body-less requests (GET /ping). */
    public const EMPTY_BODY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    public static function canonicalString(string $method, string $requestPath, string $timestamp, string $nonce, string $rawBody): string
    {
        return strtoupper($method)
            . "\n" . $requestPath
            . "\n" . $timestamp
            . "\n" . $nonce
            . "\n" . hash('sha256', $rawBody);
    }

    public static function sign(string $secret, string $method, string $requestPath, string $timestamp, string $nonce, string $rawBody): string
    {
        return base64_encode(hash_hmac(
            'sha256',
            self::canonicalString($method, $requestPath, $timestamp, $nonce, $rawBody),
            $secret,
            true
        ));
    }

    /**
     * Constant-time signature comparison. A malformed (non base64, wrong length)
     * signature is always rejected without leaking which check failed.
     */
    public static function verify(string $secret, string $signature, string $method, string $requestPath, string $timestamp, string $nonce, string $rawBody): bool
    {
        if ($secret === '' || $signature === '') {
            return false;
        }

        $provided = base64_decode($signature, true);
        if (!is_string($provided) || strlen($provided) !== 32) {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            self::canonicalString($method, $requestPath, $timestamp, $nonce, $rawBody),
            $secret,
            true
        );

        return hash_equals($expected, $provided);
    }

    public static function isTimestampFresh(int $timestamp, int $now, int $tolerance = self::DEFAULT_TIMESTAMP_TOLERANCE): bool
    {
        if ($timestamp <= 0) {
            return false;
        }

        $tolerance = max(1, $tolerance);

        return abs($now - $timestamp) <= $tolerance;
    }

    /**
     * Nonces are opaque, url-safe and long enough to make collisions negligible.
     */
    public static function isValidNonce(string $nonce): bool
    {
        return $nonce !== ''
            && strlen($nonce) >= 16
            && strlen($nonce) <= 64
            && preg_match('/^[A-Za-z0-9_-]+$/', $nonce) === 1;
    }

    /**
     * Signature of an outbound webhook: timestamp + '.' + raw JSON body (E-COM-01 §11.2).
     */
    public static function signOutbound(string $secret, string $timestamp, string $rawBody): string
    {
        return base64_encode(hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret, true));
    }
}
