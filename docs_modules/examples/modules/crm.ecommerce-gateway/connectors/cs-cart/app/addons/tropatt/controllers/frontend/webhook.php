<?php
/**
 * Alias dispatch: `index.php?dispatch=tropatt.webhook` (the same handler as
 * `tropatt.status_update`, kept for shops configured with the shorter URL).
 */

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

require __DIR__ . '/status_update.php';
