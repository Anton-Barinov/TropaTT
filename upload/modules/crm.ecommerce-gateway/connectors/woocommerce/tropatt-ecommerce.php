<?php
/**
 * Plugin Name: TropaTT CRM E-Commerce Gateway
 * Plugin URI: https://tropatt.com
 * Description: Двусторонняя синхронизация заказов, покупателей и статусов между WooCommerce и TropaTT CRM (Zero-Daemon Ingestion Gateway).
 * Version: 1.0.0
 * Author: Anton Barinov
 * Author URI: https://github.com/Anton-Barinov
 * Text Domain: tropatt-ecommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.9
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TROPATT_WC_VERSION', '1.0.0');
define('TROPATT_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once TROPATT_WC_PLUGIN_DIR . 'includes/class-tropatt-client.php';
require_once TROPATT_WC_PLUGIN_DIR . 'includes/class-tropatt-webhook-handler.php';
require_once TROPATT_WC_PLUGIN_DIR . 'includes/class-tropatt-admin-settings.php';

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    load_plugin_textdomain('tropatt-ecommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');

    Tropatt_Admin_Settings::init();
    Tropatt_Webhook_Handler::init();

    add_action('woocommerce_checkout_order_processed', array('Tropatt_Client', 'on_order_created'), 10, 3);
    add_action('woocommerce_order_status_changed', array('Tropatt_Client', 'on_order_status_changed'), 10, 4);
});
