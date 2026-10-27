<?php
/**
 * Copy to `config.php` (never commit the real file) and fill in the values.
 */

return array(
    // TropaTT CRM gateway and store credentials.
    'gateway_url' => 'https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1',
    'store_key' => 'stk_...',
    'store_secret' => '...',

    // Shopify app credentials (Partners dashboard → Apps → your app).
    'shopify_api_key' => '',
    'shopify_api_secret' => '',
    'shopify_scopes' => 'read_orders,write_orders,read_products,write_inventory,read_locations',
    // Admin API version. Shopify serves a version for ~12 months; a request for a
    // version it no longer serves is answered with the oldest supported one, so a
    // stale value keeps working silently. Keep it on a supported version.
    'shopify_api_version' => '2026-07',
    'shopify_shop' => 'my-shop.myshopify.com',

    // OAuth redirect target (defaults to this host + /shopify/callback.php).
    'shopify_redirect_uri' => '',

    // Webhook callback registered after install (defaults to this host + /shopify/webhook.php).
    'webhook_url' => '',

    // Inventory location used by the stock sync (Shopify location id).
    'shopify_location_id' => '',

    // Stock source for cron/sync_stock.php (a JSON file path or an HTTPS URL).
    'stock_source' => '',
    'default_stage' => 'new',

    // CRM stage → Shopify action (fulfill, cancel, close, noop).
    'status_mapping' => "done=fulfill\ncanceled=cancel",

    // Behaviour of the CRM→Shopify status receiver (public/status.php).
    // Money is never moved by default: `cancel_refund` stays off unless the
    // shop explicitly wants the CRM stage change to refund the buyer.
    'notify_customer' => false,
    'cancel_refund' => false,
    'cancel_restock' => true,

    // Diagnostics and local state.
    'debug' => false,
    'log_file' => __DIR__ . '/var/connector.log',
    'queue_dir' => __DIR__ . '/var/queue',
    'token_file' => __DIR__ . '/var/shopify-token.json',
);
