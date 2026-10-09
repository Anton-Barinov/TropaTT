<?php
declare(strict_types=1);

namespace Updater\Client;

/** Fail-closed validation of an exact-SHA target resolved from the update center. */
final class PinnedUpdatePlanResolver
{
    /**
     * @param array<string,mixed> $buildIndex
     * @param array<string,mixed> $manifest
     * @return array<string,mixed> Normalized updater plan for one signed full package.
     */
    public static function resolve(
        array $buildIndex,
        array $manifest,
        string $expectedProduct,
        string $channel,
        string $currentBuild,
        string $targetSha,
        ?string $currentSha = null
    ): array {
        $targetSha = strtolower(trim($targetSha));
        if (preg_match('/^[a-f0-9]{40}$/', $targetSha) !== 1) {
            throw new \InvalidArgumentException('PINNED_TARGET_SHA_INVALID');
        }
        if ($expectedProduct === '' || preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $expectedProduct) !== 1) {
            throw new \InvalidArgumentException('PINNED_TARGET_PRODUCT_INVALID');
        }
        if (($buildIndex['product'] ?? null) !== $expectedProduct || !is_array($buildIndex['builds'] ?? null)) {
            throw new \UnexpectedValueException('PINNED_BUILD_INDEX_INVALID');
        }
        $builds = $buildIndex['builds'];
        if (count($builds) > 10000) {
            throw new \UnexpectedValueException('PINNED_BUILD_INDEX_TOO_LARGE');
        }
        $matches = [];
        foreach ($builds as $build) {
            if (!is_array($build) || ($build['product'] ?? null) !== $expectedProduct
                || ($build['status'] ?? null) !== 'published'
                || !is_string($build['source_to_sha'] ?? null)) {
                continue;
            }
            if (strtolower($build['source_to_sha']) === $targetSha) {
                $matches[] = $build;
            }
        }
        if (count($matches) !== 1) {
            throw new \UnexpectedValueException(count($matches) === 0
                ? 'PINNED_TARGET_BUILD_NOT_FOUND'
                : 'PINNED_TARGET_BUILD_AMBIGUOUS');
        }
        $build = $matches[0];
        $buildNumber = (string)($build['core_build'] ?? '');
        if (preg_match('/^[A-Za-z0-9._+-]{1,64}$/', $buildNumber) !== 1) {
            throw new \UnexpectedValueException('PINNED_TARGET_BUILD_NUMBER_INVALID');
        }
        $package = $manifest['package'] ?? null;
        if (($manifest['schema_version'] ?? null) !== 1
            || ($manifest['product'] ?? null) !== $expectedProduct
            || ($manifest['channel'] ?? null) !== $channel
            || ($manifest['to_build'] ?? null) !== $buildNumber
            || !is_string($manifest['to_sha'] ?? null)
            || strtolower($manifest['to_sha']) !== $targetSha
            || !is_string($manifest['manifest_signature'] ?? null)
            || trim($manifest['manifest_signature']) === ''
            || !is_array($package)
            || ($package['type'] ?? null) !== 'full'
            || !is_string($package['url'] ?? null)
            || trim($package['url']) === ''
            || !is_int($package['size_bytes'] ?? null)
            || $package['size_bytes'] <= 0
            || !is_string($package['sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/i', $package['sha256']) !== 1
            || !is_string($package['signature'] ?? null)
            || trim($package['signature']) === '') {
            throw new \UnexpectedValueException('PINNED_TARGET_MANIFEST_MISMATCH');
        }
        return [
            'product' => $expectedProduct,
            'stream' => $expectedProduct,
            'channel' => $channel,
            'current_build' => $currentBuild,
            'target_build' => $buildNumber,
            'current_sha' => $currentSha,
            'target_sha' => $targetSha,
            'update_available' => $currentSha !== $targetSha,
            'recommended_package' => $currentSha === $targetSha ? null : $package,
            'fallback_package' => null,
            'summary' => is_array($manifest['summary'] ?? null) ? $manifest['summary'] : [],
            'requires' => ['backup' => true, 'maintenance' => true,
                'db_migration' => !empty($manifest['migrations'])],
            'manifest' => $manifest,
        ];
    }
}
