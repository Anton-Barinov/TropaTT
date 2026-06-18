<?php
declare(strict_types=1);

namespace Updater\Security;

use Updater\Package\PathGuard;

final class ManifestVerifier
{
    public function __construct(private readonly string $publicKeyPath, private readonly array $protectedPaths)
    {
    }

    public function verify(array $manifest, array $package, string $product): array
    {
        $signature = (string)($manifest['manifest_signature'] ?? '');
        $payload = $manifest;
        unset($payload['manifest_signature']);
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        $signatureVerifier = new SignatureVerifier($this->publicKeyPath);
        $paths = $this->paths($manifest);
        $guard = new PathGuard($this->protectedPaths);
        $forbidden = [];
        foreach ($paths as $path) {
            if (!$guard->isAllowed($path)) {
                $forbidden[] = $path;
            }
        }

        return [
            'schema_version' => isset($manifest['schema_version']),
            'product' => ($manifest['product'] ?? null) === $product,
            'manifest_signature' => $signatureVerifier->verify($encoded, $signature),
            'package_sha' => ($manifest['package']['sha256'] ?? null) === ($package['sha256'] ?? null),
            'package_signature' => $signatureVerifier->verify((string)($package['sha256'] ?? ''), (string)($package['signature'] ?? '')),
            'no_forbidden_paths' => $forbidden === [],
            'forbidden_paths' => $forbidden,
            'file_count' => count($paths),
        ];
    }

    private function paths(array $manifest): array
    {
        $paths = [];
        foreach (($manifest['files'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ($group as $item) {
                if (is_string($item)) {
                    $paths[] = $item;
                }
            }
        }
        return array_values(array_unique($paths));
    }
}
