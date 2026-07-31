<?php
declare(strict_types=1);

/*
 * SEC-011: Global helper functions for web templates.
 *
 * The e() function provides a single, consistent htmlspecialchars() entry
 * point with full quote/encoding safety. Templates should use e($x) for any
 * user-supplied data instead of bare <?= $x ?>, which would render any HTML
 * or JS in user input as live markup.
 *
 * Nullable signature: passing null returns an empty string. This is more
 * template-friendly than the non-nullable install.php helper (templates
 * often pass $data['x'] where x may be unset), at the cost of a small
 * divergence with web/install.php's e() definition. Both helpers use the
 * same flags (ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').
 *
 * The function_exists() guard makes this file safe to require multiple times
 * and safe to load alongside web/install.php (which defines the same
 * helper unconditionally).
 */

if (!function_exists('e')) {
    function e(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
