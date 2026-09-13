<?php
if (!defined('ABSPATH')) {
    exit;
}

class Tropatt_Admin_Settings {
    public static function init() {
        add_filter('woocommerce_settings_tabs_array', array(__CLASS__, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_tropatt', array(__CLASS__, 'settings_tab'));
        add_action('woocommerce_update_options_tropatt', array(__CLASS__, 'update_settings'));
        add_action('wp_ajax_tropatt_test_connection', array(__CLASS__, 'ajax_test_connection'));
    }

    public static function add_settings_tab($settings_tabs) {
        $settings_tabs['tropatt'] = __('TropaTT CRM', 'tropatt-ecommerce');
        return $settings_tabs;
    }

    public static function get_settings() {
        $webhook_url = get_rest_url(null, 'tropatt/v1/webhook');

        $settings = array(
            'section_title' => array(
                'name'     => __('Настройки синхронизации TropaTT CRM', 'tropatt-ecommerce'),
                'type'     => 'title',
                'desc'     => __('Шлюз гарантированной доставки заказов и реактивной синхронизации статусов (Zero-Daemon).', 'tropatt-ecommerce'),
                'id'       => 'tropatt_section_title'
            ),
            'enabled' => array(
                'name' => __('Включить модуль', 'tropatt-ecommerce'),
                'type' => 'checkbox',
                'desc' => __('Активировать автоматическую передачу заказов в TropaTT CRM', 'tropatt-ecommerce'),
                'id'   => 'tropatt_enabled',
                'default' => 'no'
            ),
            'gateway_url' => array(
                'name' => __('URL шлюза TropaTT', 'tropatt-ecommerce'),
                'type' => 'text',
                'desc' => __('Например: https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1', 'tropatt-ecommerce'),
                'id'   => 'tropatt_gateway_url',
                'css'  => 'min-width:400px;'
            ),
            'store_key' => array(
                'name' => __('Публичный ключ витрины (Store Key)', 'tropatt-ecommerce'),
                'type' => 'text',
                'desc' => __('Ключ витрины stk_..., полученный в CRM', 'tropatt-ecommerce'),
                'id'   => 'tropatt_store_key',
                'css'  => 'min-width:400px;'
            ),
            'store_secret' => array(
                'name' => __('Секретный ключ витрины (Store Secret)', 'tropatt-ecommerce'),
                'type' => 'password',
                'desc' => __('Секрет для вычисления HMAC-SHA256 подписи', 'tropatt-ecommerce'),
                'id'   => 'tropatt_store_secret',
                'css'  => 'min-width:400px;'
            ),
            'webhook_url_display' => array(
                'name' => __('URL входящих вебхуков', 'tropatt-ecommerce'),
                'type' => 'text',
                'desc' => __('Скопируйте этот URL и укажите его в настройках витрины в панели TropaTT CRM: <code>' . esc_html($webhook_url) . '</code>', 'tropatt-ecommerce'),
                'id'   => 'tropatt_webhook_url_display',
                'custom_attributes' => array('readonly' => 'readonly'),
                'default' => $webhook_url,
                'css'  => 'min-width:400px; background:#f0f0f1;'
            ),
            'section_end' => array(
                'type' => 'sectionend',
                'id'   => 'tropatt_section_end'
            )
        );

        return $settings;
    }

    public static function settings_tab() {
        woocommerce_admin_fields(self::get_settings());
        echo '<div style="margin-top:15px;"><button type="button" id="tropatt-test-btn" class="button button-secondary">Проверить соединение с TropaTT</button> <span id="tropatt-test-result" style="margin-left:10px;font-weight:bold;"></span></div>';
        echo '<script>
        jQuery("#tropatt-test-btn").on("click", function() {
            var btn = jQuery(this);
            var res = jQuery("#tropatt-test-result");
            btn.prop("disabled", true);
            res.text("Проверка соединения...").css("color", "#333");
            jQuery.post(ajaxurl, {
                action: "tropatt_test_connection",
                gateway_url: jQuery("#tropatt_gateway_url").val(),
                store_key: jQuery("#tropatt_store_key").val(),
                store_secret: jQuery("#tropatt_store_secret").val()
            }, function(response) {
                btn.prop("disabled", false);
                if (response.success) {
                    res.text("✓ " + response.data.message).css("color", "green");
                } else {
                    res.text("✗ " + (response.data ? response.data.message : "Ошибка")).css("color", "red");
                }
            });
        });
        </script>';
    }

    public static function update_settings() {
        woocommerce_update_options(self::get_settings());
    }

    public static function ajax_test_connection() {
        $gateway_url = isset($_POST['gateway_url']) ? trim((string)$_POST['gateway_url']) : get_option('tropatt_gateway_url', '');
        $store_key = isset($_POST['store_key']) ? trim((string)$_POST['store_key']) : get_option('tropatt_store_key', '');
        $store_secret = isset($_POST['store_secret']) ? trim((string)$_POST['store_secret']) : get_option('tropatt_store_secret', '');

        if (empty($gateway_url) || empty($store_key) || empty($store_secret)) {
            wp_send_json_error(array('message' => 'Заполните Gateway URL, Store Key и Store Secret'));
        }

        $ping_url = rtrim($gateway_url, '/') . '/ping';
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));
        $empty_sha256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

        $canonical = "GET\n/_module/crm.ecommerce-gateway/v1/ping\n" . $timestamp . "\n" . $nonce . "\n" . $empty_sha256;
        $signature = base64_encode(hash_hmac('sha256', $canonical, $store_secret, true));

        $headers = array(
            'X-Store-Key: ' . $store_key,
            'X-TropaTT-Timestamp: ' . $timestamp,
            'X-TropaTT-Nonce: ' . $nonce,
            'X-TropaTT-Signature: ' . $signature,
            'Accept: application/json'
        );

        $response = wp_remote_get($ping_url, array(
            'headers' => $headers,
            'timeout' => 10,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($code === 200 && isset($decoded['code']) && $decoded['code'] === 'INGESTION_PONG') {
            wp_send_json_success(array('message' => 'Соединение успешно установлено! Шлюз TropaTT отвечает (PONG).'));
        }

        wp_send_json_error(array('message' => 'HTTP ' . $code . ': ' . $body));
    }
}
