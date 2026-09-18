<?php
declare(strict_types=1);

namespace Api\System\Library\Organization;

/** Read-only organization access contract for the request context resolver. */
interface OrganizationMembershipReader
{
    /** @return array<int,array<string,mixed>> */
    public function listForUser(int $userId): array;

    /** @return array<int,array<string,mixed>> */
    public function listAll(): array;
}
