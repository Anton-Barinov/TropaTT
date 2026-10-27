<?php

namespace Tropatt\InSales;

/**
 * Verifies incoming InSales webhooks.
 *
 * The documented scheme is HMAC-SHA256 over the raw request body with the
 * application password (sent hex- or base64-encoded); shops that cannot sign the
 * request authenticate with a shared token in the webhook URL or in a header, so
 * both are supported and selectable through `webhook_verification`.
 */
class WebhookVerifier
{
    /**
     * @return bool
     */
    public static function verify($appPassword, $rawBody, $signature)
    {
        $appPassword = (string)$appPassword;
        $signature = trim((string)$signature);

        if ($appPassword === '' || $signature === '') {
            return false;
        }

        $expectedHex = hash_hmac('sha256', (string)$rawBody, $appPassword);
        if (SignatureValidator::secureEquals($expectedHex, strtolower($signature))) {
            return true;
        }

        $expectedBase64 = base64_encode(hash_hmac('sha256', (string)$rawBody, $appPassword, true));

        return SignatureValidator::secureEquals($expectedBase64, $signature);
    }

    /**
     * Signature header, tolerant to the header names shops actually send.
     *
     * The published webhook model does not pin one header name down, so every
     * plausible spelling is accepted before the request is rejected.
     *
     * @return string
     */
    public static function header(array $server)
    {
        $signature = self::signature($server);
        if ($signature !== '') {
            return $signature;
        }

        // Backwards compatible: the token header was accepted here as a fallback.
        return self::token($server);
    }

    /**
     * @return string
     */
    public static function signature(array $server)
    {
        foreach (array(
            'HTTP_X_INSALES_WEBHOOK_SIGNATURE',
            'HTTP_X_INSALES_SIGNATURE',
            'HTTP_X_INSALES_HMAC_SHA256',
            'HTTP_X_SIGNATURE',
        ) as $key) {
            if (!empty($server[$key])) {
                return (string)$server[$key];
            }
        }

        return '';
    }

    /**
     * Shared token: either an InSales header or ?token=/?secret=/?key= in the
     * webhook URL (the pattern used when a shop authenticates the webhook by URL).
     *
     * @return string
     */
    public static function token(array $server, array $query = array())
    {
        foreach (array('HTTP_X_INSALES_WEBHOOK_TOKEN', 'HTTP_X_INSALES_TOKEN', 'HTTP_X_INSALES_SECRET') as $key) {
            if (!empty($server[$key])) {
                return (string)$server[$key];
            }
        }

        foreach (array('token', 'secret', 'key') as $name) {
            if (isset($query[$name]) && is_scalar($query[$name]) && (string)$query[$name] !== '') {
                return (string)$query[$name];
            }
        }

        return '';
    }

    /**
     * Verifies an incoming webhook with the configured policy.
     *
     * `webhook_verification`:
     *   - `auto` (default): HMAC signature OR shared token, otherwise reject;
     *   - `signature`: only the HMAC signature is accepted;
     *   - `token`: only the shared token is accepted;
     *   - `off`: accept anything (documented as insecure, for shops whose
     *     webhooks cannot be authenticated; the caller logs a warning).
     *
     * @param array<string,mixed> $config connector configuration
     * @param array<string,mixed> $server usually $_SERVER
     * @param array<string,mixed> $query usually $_GET
     */
    public static function verifyRequest(array $config, $rawBody, array $server, array $query = array())
    {
        $mode = strtolower(trim((string)($config['webhook_verification'] ?? 'auto')));
        if ($mode === 'off') {
            return true;
        }

        $secret = trim((string)($config['webhook_secret'] ?? ''));
        if ($secret === '') {
            $secret = trim((string)($config['insales_app_password'] ?? ''));
        }
        if ($secret === '') {
            return false;
        }

        $signature = self::signature($server);
        if (($mode === 'auto' || $mode === 'signature') && $signature !== ''
            && self::verify($secret, $rawBody, $signature)) {
            return true;
        }

        $token = self::token($server, $query);
        if (($mode === 'auto' || $mode === 'token') && $token !== ''
            && SignatureValidator::secureEquals($secret, $token)) {
            return true;
        }

        return false;
    }
}
