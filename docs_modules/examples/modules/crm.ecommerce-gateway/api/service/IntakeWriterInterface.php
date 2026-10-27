<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

/**
 * The slice of the core intake service the gateway needs.
 *
 * Depending on this contract instead of the concrete `IntakeItemService` keeps
 * the ingestion pipeline testable without the whole core container (the core
 * service is final and pulls in tasks, projects and notifications).
 */
interface IntakeWriterInterface
{
    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string an error code string on failure
     */
    public function create(array $input, array $actor): array|string;

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function get(string $publicId, array $actor): ?array;

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|string|null
     */
    public function update(string $publicId, array $input, array $actor): array|string|null;
}
