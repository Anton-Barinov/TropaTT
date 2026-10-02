<?php
declare(strict_types=1);

namespace Api\System\Library\Connector;

/**
 * Universal signature and replay-window verifier for incoming provider webhooks.
 * Supports HMAC-SHA256, HMAC-SHA1, hex/base64 formats, and timestamped payloads (Stripe, GitHub, Shopify, etc.).
 */
final class ConnectorSignatureVerifier implements ConnectorSignatureVerifierInterface
{
    /**
     * @param string $rawBody Raw, unparsed HTTP request body
     * @param string $signature Signature from header/query (e.g. "sha256=...", "v1=...", "t=123,v1=...")
     * @param string $secret Shared secret key
     * @param array<string, mixed> $options
     */
    public function verify(string $rawBody, string $signature, string $secret, array $options = []): bool
    {
        $signature = trim($signature);
        $secret = trim($secret);

        if ($signature === '' || $secret === '') {
            return false;
        }

        $algorithm = strtolower((string)($options['algorithm'] ?? 'sha256'));
        if (!in_array($algorithm, ['sha256', 'sha1', 'sha512'], true)) {
            $algorithm = 'sha256';
        }

        $format = strtolower((string)($options['format'] ?? 'hex'));

        // Handle composite headers like "t=1700000000,v1=abc12345..."
        $extractedTimestamp = null;
        $candidateSignatures = [];

        if (str_contains($signature, ',') && str_contains($signature, '=')) {
            $parts = explode(',', $signature);
            foreach ($parts as $part) {
                $sub = explode('=', trim($part), 2);
                if (count($sub) === 2) {
                    $k = trim($sub[0]);
                    $v = trim($sub[1]);
                    if ($k === 't' && is_numeric($v)) {
                        $extractedTimestamp = (int)$v;
                    } elseif ($k === 'v1' || $k === 'v0' || $k === 'sha256' || $k === 'sig' || $k === 'signature') {
                        $candidateSignatures[] = $v;
                    }
                }
            }
        }

        if ($candidateSignatures === []) {
            // Strip common prefixes like "sha256=", "sha1=", "v1="
            $cleanSignature = $signature;
            if (preg_match('/^(sha256|sha1|sha512|v1|v0|sig)\s*=\s*(.+)$/i', $signature, $m)) {
                $cleanSignature = trim($m[2]);
            }
            $candidateSignatures[] = $cleanSignature;
        }

        // If a timestamp was supplied in options or in the header, verify replay window if requested
        $timestamp = (int)($options['timestamp'] ?? $extractedTimestamp ?? 0);
        $checkReplay = (bool)($options['check_replay'] ?? false);
        $maxAge = (int)($options['max_age_seconds'] ?? 300);

        if ($checkReplay && $timestamp > 0) {
            $now = isset($options['now']) ? (int)$options['now'] : null;
            if (!$this->verifyReplayWindow($timestamp, $maxAge, $now)) {
                return false;
            }
        }

        // Determine the payload to hash
        $payloadToSign = $rawBody;
        if (!empty($options['prefix_timestamp']) && $timestamp > 0) {
            $payloadToSign = $timestamp . '.' . $rawBody;
        } elseif ($extractedTimestamp !== null && empty($options['raw_body_only'])) {
            $payloadToSign = $extractedTimestamp . '.' . $rawBody;
        }

        // Compute expected HMACs
        $expectedHex = hash_hmac($algorithm, $payloadToSign, $secret);
        $expectedBase64 = base64_encode(hash_hmac($algorithm, $payloadToSign, $secret, true));

        foreach ($candidateSignatures as $cand) {
            $cand = trim($cand);
            if ($format === 'base64') {
                if (hash_equals($expectedBase64, $cand)) {
                    return true;
                }
            } elseif ($format === 'hex') {
                if (hash_equals(strtolower($expectedHex), strtolower($cand))) {
                    return true;
                }
            } else {
                // Auto format: check both
                if (hash_equals(strtolower($expectedHex), strtolower($cand)) || hash_equals($expectedBase64, $cand)) {
                    return true;
                }
            }
        }

        // Fallback: if signing with timestamp failed and raw body wasn't tried, try raw body directly
        if ($payloadToSign !== $rawBody) {
            $fallbackHex = hash_hmac($algorithm, $rawBody, $secret);
            $fallbackBase64 = base64_encode(hash_hmac($algorithm, $rawBody, $secret, true));
            foreach ($candidateSignatures as $cand) {
                $cand = trim($cand);
                if (hash_equals(strtolower($fallbackHex), strtolower($cand)) || hash_equals($fallbackBase64, $cand)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Protect against clock skew and replay attacks.
     */
    public function verifyReplayWindow(int $timestamp, int $maxAgeSeconds = 300, ?int $now = null): bool
    {
        if ($timestamp <= 0 || $maxAgeSeconds <= 0) {
            return false;
        }

        $currentTime = $now ?? time();
        $diff = abs($currentTime - $timestamp);

        return $diff <= $maxAgeSeconds;
    }
}
