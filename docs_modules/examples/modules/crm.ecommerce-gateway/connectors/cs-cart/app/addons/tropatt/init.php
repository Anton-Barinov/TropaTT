<?php
/**
 * CS-Cart addon bootstrap: hook registration and class autoloading.
 */

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

fn_register_hooks(
    'place_order',
    'change_order_status',
    'update_order',
    'get_order_info'
);

/**
 * @param string $class
 * @return void
 */
function fn_tropatt_autoload($class)
{
    $prefix = 'Tropatt\\CsCart\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/lib/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
}

spl_autoload_register('fn_tropatt_autoload');
