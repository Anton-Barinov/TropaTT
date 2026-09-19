<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Http\Request;
use Api\System\Library\Organization\OrganizationMembershipReader;

/**
 * Resolves the organization selected for the current request.
 *
 * This service is deliberately opt-in: callers must ask for a context before
 * repositories become tenant-scoped. Existing endpoints therefore retain
 * their legacy behavior until they adopt the resolver explicitly.
 */
final class OrganizationContextService
{
    public function __construct(private readonly OrganizationMembershipReader $organizations)
    {
    }

    /**
     * @param array<string,mixed> $actor Authenticated user payload (not auth envelope)
     * @return array{status:string,source:string,organization:?array<string,mixed>,organization_public_id:?string,requires_selection:bool,is_explicit:bool}
     */
    public function resolve(Request $request, array $actor): array
    {
        $isRoot = (bool)($actor['is_root'] ?? false);
        $memberships = $isRoot
            ? $this->organizations->listAll()
            : $this->organizations->listForUser((int)($actor['id'] ?? 0));
        $requested = $this->requestedPublicId($request);

        if ($requested !== null) {
            foreach ($memberships as $organization) {
                if (hash_equals($requested, (string)($organization['public_id'] ?? ''))) {
                    return $this->active($organization, 'explicit', true);
                }
            }

            // Root may inspect any existing organization, but an unknown id is
            // still rejected so a typo can never become an unscoped write.
            return $this->empty('forbidden', true);
        }

        if (count($memberships) === 1) {
            return $this->active($memberships[0], 'single_membership', false);
        }

        if (count($memberships) > 1) {
            return $this->empty('selection_required', false, true);
        }

        // No membership remains a valid legacy state while migration is in
        // progress. Opt-in callers can distinguish it from a denied context.
        return $this->empty('legacy_unscoped', false);
    }

    /**
     * Write guard for adopters. Root users must explicitly select a workspace;
     * members may use the safe single-membership fallback. This method does
     * not throw and therefore cannot break legacy endpoints.
     */
    public function allowsWrite(Request $request, array $actor): bool
    {
        $context = $this->resolve($request, $actor);
        if ($context['status'] !== 'active') {
            return false;
        }

        if ((bool)($actor['is_root'] ?? false) && !$context['is_explicit']) {
            return false;
        }

        return true;
    }

    /** @return array<string,mixed>|null */
    public function organization(Request $request, array $actor): ?array
    {
        $resolved = $this->resolve($request, $actor);
        return $resolved['status'] === 'active' ? $resolved['organization'] : null;
    }

    private function requestedPublicId(Request $request): ?string
    {
        $headers = [
            $request->header('X-Organization-Id'),
            $request->header('X-Organization-Public-Id'),
        ];
        foreach ($headers as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                return substr($value, 0, 128);
            }
        }

        $value = trim((string)$request->input('organization_public_id', ''));
        return $value !== '' ? substr($value, 0, 128) : null;
    }

    /** @param array<string,mixed> $organization */
    private function active(array $organization, string $source, bool $explicit): array
    {
        $publicId = (string)($organization['public_id'] ?? '');
        return [
            'status' => 'active',
            'source' => $source,
            'organization' => $organization,
            'organization_public_id' => $publicId !== '' ? $publicId : null,
            'requires_selection' => false,
            'is_explicit' => $explicit,
        ];
    }

    /** @return array<string,mixed> */
    private function empty(string $status, bool $explicit, bool $requiresSelection = false): array
    {
        return [
            'status' => $status,
            'source' => 'none',
            'organization' => null,
            'organization_public_id' => null,
            'requires_selection' => $requiresSelection,
            'is_explicit' => $explicit,
        ];
    }
}
