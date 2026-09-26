<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use RuntimeException;

/**
 * Thrown when a remote module install is attempted without MODULE_SIGNING_KEY.
 *
 * The underlying verification is fail-closed by design (SECURITY.md, audit
 * C-2): an unverifiable package must never be installed. But the failure used
 * to surface as a generic "Не удалось установить модуль" 500 — the admin had
 * no way to tell a network error from a missing signing key, and nothing
 * anywhere said the key had to be set. Callers map this to a dedicated error
 * code with an actionable message.
 *
 * Official-marketplace installs are NOT affected: they are verified through
 * the sha256 pin of the install-request response and do not require the key.
 */
final class ModuleSigningKeyMissingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Module signing key not configured — set MODULE_SIGNING_KEY in .env to install modules from a URL or an uploaded ZIP'
        );
    }
}
