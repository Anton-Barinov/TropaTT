<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use RuntimeException;

/**
 * Thrown when a downloaded module archive does not match the sha256 the
 * marketplace install-request declared for this release.
 *
 * The hash arrives over TLS from the configured marketplace base_url together
 * with the signed download URL, so a mismatch means the transfer (or the
 * endpoint answering it) is not serving the package the catalog recorded.
 * Callers map it to a dedicated error code — a generic install failure would
 * hide that the archive was rejected for integrity, not for content.
 */
final class ModulePackageHashMismatchException extends RuntimeException
{
    public function __construct(private readonly string $expectedSha256)
    {
        parent::__construct('Module package sha256 does not match the marketplace release');
    }

    public function expectedSha256(): string
    {
        return $this->expectedSha256;
    }
}
