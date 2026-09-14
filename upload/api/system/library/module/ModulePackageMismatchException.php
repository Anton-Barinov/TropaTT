<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use RuntimeException;

/**
 * Thrown when a module package declares a different module name than the one the
 * caller asked for.
 *
 * This is a distinct failure from a corrupt or unsigned package: the archive is
 * intact, but its manifest identity does not match the catalog entry that was
 * selected (a marketplace release published with a bare `name` while the catalog
 * advertises `<vendor>.<module>`). Callers map it to a dedicated error code so
 * the admin sees which side is wrong instead of a generic install failure.
 */
final class ModulePackageMismatchException extends RuntimeException
{
    public function __construct(
        private readonly string $declaredName,
        private readonly string $expectedName,
    ) {
        parent::__construct(
            "Module package declares name \"{$declaredName}\" but \"{$expectedName}\" was requested"
        );
    }

    public function declaredName(): string
    {
        return $this->declaredName;
    }

    public function expectedName(): string
    {
        return $this->expectedName;
    }
}
