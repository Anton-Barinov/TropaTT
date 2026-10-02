<?php
declare(strict_types=1);

namespace Api\System\Library\Connector;

/**
 * Diagnostic, audit, health reporting and SSRF-safe HTTP helpers for connectors.
 */
interface ConnectorDiagnosticsInterface
{
    /**
     * Recursively redact all sensitive keys (passwords, tokens, API keys, signatures) in data.
     */
    public function sanitizePayload(mixed $data): mixed;

    /**
     * Format a standardized health / test-connection report.
     *
     * @param string $status 'healthy' | 'warning' | 'error' | 'disconnected'
     * @param array<string, array{status: string, message: string, latency_ms?: float}> $checks
     * @param string|null $correlationId
     * @return array<string, mixed>
     */
    public function formatHealthReport(string $status, array $checks, ?string $correlationId = null): array;

    /**
     * Validate an outbound target URL against SSRF and private address spaces.
     */
    public function validateOutboundUrl(string $url): bool;

    /**
     * Get safe cURL options bounded for shared hosting environments.
     *
     * @return array<int, mixed>
     */
    public function getSafeHttpOptions(int $timeoutSeconds = 15): array;
}
