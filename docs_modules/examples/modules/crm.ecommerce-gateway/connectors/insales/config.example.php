<?php
/**
 * Copy to `config.php` (never commit the real file) and fill in the values.
 */

return array(
    // TropaTT CRM gateway (Ingestion API prefix) and store credentials.
    'gateway_url' => 'https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1',
    'store_key' => 'stk_...',
    'store_secret' => '...',

    // InSales application: shop domain, client id/secret and the app password
    // (the password signs the incoming webhooks).
    'insales_shop' => 'my-shop.myinsales.ru',
    'insales_client_id' => '',
    'insales_client_secret' => '',
    'insales_app_password' => '',

    // OAuth redirect target (defaults to this host + /insales/install.php).
    'insales_redirect_uri' => '',

    // Webhook verification policy:
    //   auto (default) — HMAC signature OR shared token, otherwise reject;
    //   signature      — only the HMAC-SHA256 signature is accepted;
    //   token          — only the shared token (header or ?token=/?secret=/?key=) is accepted;
    //   off            — accept anything (insecure; only for shops whose webhooks cannot be authenticated).
    'webhook_verification' => 'auto',

    // Optional separate shared secret; falls back to `insales_app_password`.
    'webhook_secret' => '',

    // Webhook address registered on install (defaults to this host + /insales/webhook.php).
    'webhook_url' => '',

    // CRM stage codes per InSales fulfilment status (new / in_progress / shipped / delivered / declined).
    'status_mapping' => "new=new\nin_progress=in_progress\nshipped=review\ndelivered=done\ndeclined=canceled",
    'default_stage' => 'new',

    // Stock source for cron/sync_stock.php (a JSON file path or an HTTPS URL).
    'stock_source' => '',

    // InSales rate limit (the platform allows ~500 requests per 5 minutes).
    'rate_limit' => 480,
    'rate_window' => 300,

    // Diagnostics and local state.
    'debug' => false,
    'log_file' => __DIR__ . '/var/connector.log',
    'queue_dir' => __DIR__ . '/var/queue',
    'token_file' => __DIR__ . '/var/insales-token.json',
    'rate_limit_file' => __DIR__ . '/var/insales-rate.json',
);
