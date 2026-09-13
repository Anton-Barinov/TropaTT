<?php
declare(strict_types=1);

namespace Api\System\Library\Support;

use PDO;
use Throwable;

/**
 * Single source of truth for task-status semantics.
 *
 * A "terminal" status is one where the task is no longer active — it must be
 * excluded from every "active"/"open" counter and filter. Two sources combine:
 *
 *  1. The dictionary (`statuses`, scope = task, `is_closed = 1`) is authoritative
 *     for every code it contains, so an administrator can mark any custom status
 *     as terminal (or clear the mark from a built-in one).
 *  2. Codes that exist in the data but not in the dictionary — legacy aliases
 *     such as `completed` or `new`, produced by the API, imports or older
 *     versions — fall back to the canonical alias list below, which mirrors
 *     TaskRepository::expandStatusAliases().
 *
 * The resolver is cached per PDO connection for the lifetime of the request and
 * must be reset when the dictionary changes (StatusRepository does this).
 */
final class TaskStatusSemantics
{
    public const SCOPE = 'task';

    /**
     * Canonical terminal codes, applied only to codes absent from the dictionary.
     * Kept in the same spirit as TaskRepository::expandStatusAliases():
     * `done` <-> `completed`, `canceled` <-> `cancelled`.
     */
    public const FALLBACK_TERMINAL_CODES = ['done', 'completed', 'closed', 'canceled', 'cancelled', 'archived'];

    /** Statuses that mean "finished successfully" (used for behaviour triggers). */
    public const COMPLETED_CODES = ['done', 'completed', 'closed'];

    /** Statuses that mean "not started yet" (used for behaviour triggers). */
    public const PENDING_CODES = ['new', 'todo'];

    /** @var array<int, array<string, bool>> spl_object_id(PDO) => code => is terminal */
    private static array $cache = [];

    /** @return list<string> lower-cased terminal status codes */
    public static function terminalCodes(PDO $pdo): array
    {
        $map = self::map($pdo);

        return array_keys(array_filter($map, static fn(bool $terminal): bool => $terminal));
    }

    public static function isTerminal(PDO $pdo, string $code): bool
    {
        return self::map($pdo)[strtolower(trim($code))] ?? false;
    }

    public static function isCompleted(string $code): bool
    {
        return in_array(strtolower(trim($code)), self::COMPLETED_CODES, true);
    }

    /**
     * Dictionary-free terminal check for callers that have no PDO (advisory
     * paths such as AI suggestions). Custom statuses marked in the dictionary
     * are only honoured where a connection is available.
     */
    public static function isCanonicalTerminal(string $code): bool
    {
        return in_array(strtolower(trim($code)), self::FALLBACK_TERMINAL_CODES, true);
    }

    /**
     * SQL fragment + bound params excluding terminal statuses from a column.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function notTerminalSql(PDO $pdo, string $column = 't.status_code'): array
    {
        $codes = self::terminalCodes($pdo);
        if ($codes === []) {
            return ['1 = 1', []];
        }

        $placeholders = implode(', ', array_fill(0, count($codes), '?'));

        return [$column . ' NOT IN (' . $placeholders . ')', $codes];
    }

    /** @return array{0: string, 1: list<string>} SQL fragment selecting terminal statuses */
    public static function terminalSql(PDO $pdo, string $column = 't.status_code'): array
    {
        $codes = self::terminalCodes($pdo);
        if ($codes === []) {
            return ['1 = 0', []];
        }

        $placeholders = implode(', ', array_fill(0, count($codes), '?'));

        return [$column . ' IN (' . $placeholders . ')', $codes];
    }

    /**
     * Quote a list of codes for embedding inside a SQL string (CASE/SUM
     * expressions and correlated sub-queries cannot bind positional params).
     * An empty list renders a never-matching literal so `IN (...)` matches
     * nothing and `NOT IN (...)` matches everything — both are the intended
     * meaning of "no terminal statuses".
     */
    public static function literalList(PDO $pdo, array $codes): string
    {
        if ($codes === []) {
            return $pdo->quote('__no_status__');
        }

        return implode(', ', array_map(static fn(string $code): string => $pdo->quote($code), $codes));
    }

    /** Terminal statuses as a quoted SQL literal list. */
    public static function terminalLiteralList(PDO $pdo): string
    {
        return self::literalList($pdo, self::terminalCodes($pdo));
    }

    /** Successfully-completed statuses as a quoted SQL literal list. */
    public static function completedLiteralList(PDO $pdo): string
    {
        return self::literalList($pdo, self::COMPLETED_CODES);
    }

    /**
     * Reset the memoized dictionary. Pass a PDO to drop one connection's cache,
     * or null to clear everything (used by tests).
     */
    public static function resetCache(?PDO $pdo = null): void
    {
        if ($pdo === null) {
            self::$cache = [];
            return;
        }

        unset(self::$cache[spl_object_id($pdo)]);
    }

    /** @return array<string, bool> code => is terminal */
    private static function map(PDO $pdo): array
    {
        $key = spl_object_id($pdo);
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = self::resolve($pdo);
        }

        return self::$cache[$key];
    }

    /** @return array<string, bool> */
    private static function resolve(PDO $pdo): array
    {
        $known = [];
        $terminal = [];

        try {
            $stmt = $pdo->prepare('SELECT code, is_closed FROM statuses WHERE scope = :scope');
            $stmt->execute([':scope' => self::SCOPE]);
            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $code = strtolower(trim((string)($row['code'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $known[$code] = true;
                $terminal[$code] = ((int)($row['is_closed'] ?? 0)) === 1;
            }
        } catch (Throwable $e) {
            // Pre-migration install (no is_closed column) or missing table:
            // fall through to the canonical list so counters stay correct.
            $known = [];
            $terminal = [];
        }

        foreach (self::FALLBACK_TERMINAL_CODES as $code) {
            if (!isset($known[$code])) {
                $terminal[$code] = true;
            }
        }

        return $terminal;
    }
}
