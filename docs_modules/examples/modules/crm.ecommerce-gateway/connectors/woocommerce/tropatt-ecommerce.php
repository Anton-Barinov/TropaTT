<?php
/**
 * Plugin Name: TropaTT CRM E-Commerce Gateway
 * Plugin URI: https://tropatt.com
 * Description: Двусторонняя синхронизация заказов, покупателей и статусов между WooCommerce и TropaTT CRM (Zero-Daemon Ingestion Gateway).
 * Version: 1.1.2
 * Author: Anton Barinov
 * Author URI: https://github.com/Anton-Barinov
 * Text Domain: tropatt-ecommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.9
 * WC HPOS compatible: yes
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TROPATT_WC_VERSION', '1.1.2');
define('TROPATT_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once TROPATT_WC_PLUGIN_DIR . 'includes/class-tropatt-client.php';
require_once TROPATT_WC_PLUGIN_DIR . 'includes/class-tropatt-webhook-handler.php';
require_once TROPATT_WC_PLUGIN_DIR . 'includes/class-tropatt-admin-settings.php';

/**
 * Declare compatibility with High-Performance Order Storage (HPOS) and with the
 * Cart/Checkout blocks. Without this declaration WooCommerce shows an
 * incompatibility warning and refuses to enable HPOS on the store.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    load_plugin_textdomain('tropatt-ecommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');

    Tropatt_Admin_Settings::init();
    Tropatt_Webhook_Handler::init();

    // Action Scheduler handler: order events are dispatched asynchronously when
    // Action Scheduler is available (it ships with WooCommerce), so a slow or
    // unreachable CRM never blocks checkout and a failed send can be retried.
    add_action('tropatt_send_order_event', array('Tropatt_Client', 'process_queued_event'), 10, 2);

    add_action('woocommerce_checkout_order_processed', array('Tropatt_Client', 'on_order_created'), 10, 3);
    // Cart/Checkout blocks go through the Store API and never fire the legacy
    // hook above (WooCommerce 7.2+), so they need their own entry point. Without
    // it every order placed through the block checkout stayed CRM-less.
    add_action('woocommerce_store_api_checkout_order_processed', array('Tropatt_Client', 'on_store_api_order_processed'), 10, 1);
    add_action('woocommerce_order_status_changed', array('Tropatt_Client', 'on_order_status_changed'), 10, 4);
});
