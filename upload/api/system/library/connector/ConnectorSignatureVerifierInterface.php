<?php
declare(strict_types=1);

namespace Api\System\Library\Connector;

/**
 * Contract for verifying inbound webhook / callback signatures.
 */
interface ConnectorSignatureVerifierInterface
{
    /**
     * Verify request signature against expected secret.
     *
     * @param string $rawBody Raw, unparsed HTTP request body
     * @param string $signature Signature from HTTP header or query
     * @param string $secret Shared secret or webhook signing key
     * @param array<string, mixed> $options Verification options (algorithm, format, header prefix, timestamp)
     */
    public function verify(string $rawBody, string $signature, string $secret, array $options = []): bool;

    /**
     * Check if timestamp falls within the allowed replay window.
     *
     * @param int $timestamp Unix timestamp of the request
     * @param int $maxAgeSeconds Maximum permitted age in seconds (default 300)
     * @param int|null $now Reference time (defaults to current time)
     */
    public function verifyReplayWindow(int $timestamp, int $maxAgeSeconds = 300, ?int $now = null): bool;
}
