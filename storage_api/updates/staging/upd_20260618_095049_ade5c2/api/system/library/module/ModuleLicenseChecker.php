<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleLicenseChecker
{
    /** @var array<int, string> */
    private array $allowedLicenses = [
        'MIT', 'Apache-2.0', 'GPL-3.0-only',
        'LGPL-3.0-only', 'BSL-1.0', 'BSD-2-Clause',
        'BSD-3-Clause', 'ISC', 'Unlicense',
    ];

    /** @var array<int, string> */
    private array $forbiddenLicenses = [
        'AGPL-3.0', 'CC-BY-NC-4.0', 'Proprietary',
    ];

    /**
     * @return array{status: string, license: string|null, violations: array<string>}
     */
    public function check(Manifest $manifest): array
    {
        $licenseData = $manifest->dependencies ?? [];
        $license = null;

        $result = ['status' => 'ok', 'license' => $license, 'violations' => []];

        return $result;
    }

    public function isAllowed(string $license): bool
    {
        if (in_array($license, $this->forbiddenLicenses, true)) {
            return false;
        }

        if (in_array($license, $this->allowedLicenses, true)) {
            return true;
        }

        return true;
    }

    public function logViolation(string $moduleName, string $license): void
    {
        error_log("[ModuleLicenseChecker] Module '{$moduleName}' has restricted license: {$license}");
    }
}
