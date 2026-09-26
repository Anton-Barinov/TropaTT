<?php
declare(strict_types=1);

namespace Updater\Apply;

/**
 * Environment gate for the update preflight phase.
 *
 * The manifest declares what the package needs to run (requirements.php,
 * requirements.mysql, requirements.updater, requirements.min_core_build).
 * Preflight used to verify signatures, disk and locks but never the platform
 * versions — a package could be downloaded, applied and finalized on a host it
 * cannot run on, and the system only broke once the new code was live
 * ("update applied, site down"). These checks run BEFORE any mutation, so an
 * incompatible host is rejected while the current version still works.
 *
 * Checks are fail-closed but conservative: an unparseable constraint never
 * blocks (the manifest is the source of truth, not this parser), while an
 * unreachable database fails the requirement that depends on it — the update
 * could not take a backup or run migrations anyway.
 */
final class PreflightChecker
{
    /**
     * Resolve the environment (PHP version, updater version, database version)
     * and evaluate manifest['requirements'] against it.
     *
     * @param array $manifest Verified update manifest.
     * @param string $basePath Project root (upload/).
     * @param string|null $currentBuild Installed build, for min_core_build.
     * @return array<string, bool> named checks; only evaluable ones present.
     */
    public static function check(array $manifest, string $basePath, ?string $currentBuild = null): array
    {
        $requirements = is_array($manifest['requirements'] ?? null) ? $manifest['requirements'] : [];
        if ($requirements === []) {
            return [];
        }

        $updaterVersion = trim((string)@file_get_contents($basePath . '/updater/VERSION'));

        $dbDriver = null;
        $dbVersion = null;
        try {
            $connection = \Updater\Db\Connection::open($basePath);
            $dbDriver = (string)$connection['driver'];
            if (in_array($dbDriver, ['mysql', 'mariadb'], true)) {
                $value = $connection['pdo']->query('SELECT VERSION()')->fetchColumn();
                $dbVersion = is_string($value) && $value !== '' ? $value : null;
            }
        } catch (\Throwable) {
            // Unreachable database: the mysql requirement (if any) then fails
            // closed in evaluate(); drivers that carry no mysql requirement
            // are unaffected.
        }

        return self::evaluate(
            $requirements,
            PHP_VERSION,
            $updaterVersion !== '' ? $updaterVersion : null,
            $dbDriver,
            $dbVersion,
            $currentBuild,
        );
    }

    /**
     * Pure evaluator — requirements against known versions (unit-testable
     * without touching a database or the filesystem).
     *
     * @param array $requirements manifest['requirements'].
     * @param string $phpVersion Running PHP (the SAPI that will serve the app).
     * @param string|null $updaterVersion upload/updater/VERSION.
     * @param string|null $dbDriver Resolved driver (mysql/mariadb/sqlite…);
     *        null when the connection could not be opened.
     * @param string|null $dbVersion Full server version string ("8.0.36-log").
     * @param string|null $currentBuild Installed build for min_core_build.
     * @return array<string, bool>
     */
    public static function evaluate(
        array $requirements,
        string $phpVersion,
        ?string $updaterVersion,
        ?string $dbDriver,
        ?string $dbVersion,
        ?string $currentBuild,
    ): array {
        $checks = [];

        $php = trim((string)($requirements['php'] ?? ''));
        if ($php !== '' && $php !== 'null') {
            $checks['requirements_php'] = self::satisfies($php, $phpVersion);
        }

        $updater = trim((string)($requirements['updater'] ?? ''));
        if ($updater !== '' && $updater !== 'null' && $updaterVersion !== null) {
            $checks['requirements_updater'] = self::satisfies($updater, $updaterVersion);
        }

        $mysql = trim((string)($requirements['mysql'] ?? ''));
        if ($mysql !== '' && $mysql !== 'null') {
            if ($dbDriver !== null && !in_array($dbDriver, ['mysql', 'mariadb'], true)) {
                // sqlite and friends: the manifest's mysql constraint does not
                // apply to this deployment — nothing to check.
            } elseif ($dbVersion === null) {
                $checks['requirements_mysql'] = false;
            } else {
                $checks['requirements_mysql'] = self::satisfies($mysql, $dbVersion);
            }
        }

        $minCoreBuild = trim((string)($requirements['min_core_build'] ?? ''));
        if ($minCoreBuild !== '' && $minCoreBuild !== 'null'
            && $currentBuild !== null && preg_match('/^\d{8}\.\d+$/', $currentBuild)
            && preg_match('/^\d{8}\.\d+$/', (string)preg_replace('/^(>=|<=|>|<|==|!=|=)\s*/', '', $minCoreBuild))
        ) {
            $checks['requirements_min_core_build'] = self::satisfies($minCoreBuild, $currentBuild);
        }

        return $checks;
    }

    /**
     * Does $version satisfy a single constraint such as ">=8.1"?
     *
     * An unparseable constraint returns true on purpose: the manifest was
     * signature-verified, and refusing an update because this evaluator does
     * not understand a newer constraint syntax would brick the update channel.
     */
    public static function satisfies(string $constraint, string $version): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '') {
            return true;
        }
        if (!preg_match('/^(>=|<=|>|<|==|!=|=)?\s*([0-9][0-9A-Za-z_.+-]*)$/', $constraint, $matches)) {
            return true;
        }

        $operator = $matches[1] !== '' ? $matches[1] : '>=';
        if ($operator === '=') {
            $operator = '==';
        }
        $cmp = self::compare($version, $matches[2]);

        return match ($operator) {
            '>=' => $cmp >= 0,
            '>' => $cmp > 0,
            '<=' => $cmp <= 0,
            '<' => $cmp < 0,
            '!=' => $cmp !== 0,
            default => $cmp === 0,
        };
    }

    /**
     * Numeric segment-by-segment comparison: "8.1.0" == "8.1", "10.6.22-MariaDB"
     * > "5.7". Non-digit suffixes on a segment are ignored ("0RC1" → 0), which
     * only widens equality for pre-release labels and never reorders majors.
     */
    private static function compare(string $a, string $b): int
    {
        $segmentsA = array_map('intval', preg_split('/[^\d]+/', $a) ?: []);
        $segmentsB = array_map('intval', preg_split('/[^\d]+/', $b) ?: []);
        $length = max(count($segmentsA), count($segmentsB));

        for ($i = 0; $i < $length; $i++) {
            $left = $segmentsA[$i] ?? 0;
            $right = $segmentsB[$i] ?? 0;
            if ($left !== $right) {
                return $left <=> $right;
            }
        }

        return 0;
    }
}
