<?php
declare(strict_types=1);

namespace Api\System\Library\Support;

/**
 * Shared utility for escaping SQL LIKE wildcards.
 *
 * User input wrapped in %...% for LIKE queries must have its own
 * %, _, and \ characters escaped to prevent wildcard injection
 * (e.g. entering "%" to match all rows, or "_" for character substitution).
 *
 * Usage:
 *   $term = '%' . LikeEscaper::escape($userInput) . '%';
 *   $qb->whereRaw('title LIKE ?', [$term]);
 */
final class LikeEscaper
{
    /**
     * Escape LIKE wildcards in a user-supplied value.
     * Must be called BEFORE wrapping with %...%.
     */
    public static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
