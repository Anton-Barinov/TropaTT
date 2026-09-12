<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway\Service;

use Api\System\Library\Service\IntakeItemService;

/**
 * Adapts the core intake service to the gateway's narrow writer contract.
 *
 * The core service stays untouched: the module only depends on the small
 * interface, and the container builds the adapter at request time.
 */
final class CoreIntakeWriter implements IntakeWriterInterface
{
    public function __construct(private readonly IntakeItemService $intake)
    {
    }

    public function create(array $input, array $actor): array|string
    {
        return $this->intake->create($input, $actor);
    }

    public function get(string $publicId, array $actor): ?array
    {
        return $this->intake->get($publicId, $actor);
    }

    public function update(string $publicId, array $input, array $actor): array|string|null
    {
        return $this->intake->update($publicId, $input, $actor);
    }
}
