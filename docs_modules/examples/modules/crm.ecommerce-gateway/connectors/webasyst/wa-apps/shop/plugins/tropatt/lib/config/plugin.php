<?php
/**
 * Webasyst plugin manifest for Shop-Script.
 *
 * `frontend => true` is required for the plugin's own frontend routes from
 * `lib/config/routing.php` to be registered at all; without it the inbound CRM
 * webhook route (`/tropatt/webhook/`) is never added and every request to it
 * ends in 404. The icon declared in `img` must exist as well, otherwise Webasyst
 * renders a broken image in the plugin list.
 */

return array(
    'name' => 'TropaTT CRM — шлюз интернет-магазина',
    'description' => 'Двусторонняя синхронизация заказов, покупателей и статусов между Shop-Script (Webasyst) и TropaTT CRM.',
    'version' => '1.0.3',
    'frontend' => true,
    'vendor' => 'Anton Barinov',
    'img' => 'img/tropatt.png',
    'rights' => true,
    'handlers' => array(
        // A new order and every workflow action on it are pushed to the CRM.
        'order_action.create' => 'orderActionCreate',
        'order_action.*' => 'orderActionUpdate',
    ),
);
