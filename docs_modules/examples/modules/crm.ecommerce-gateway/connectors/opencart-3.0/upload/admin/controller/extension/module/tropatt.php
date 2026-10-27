<?php
class ControllerExtensionModuleTropatt extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/tropatt');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_tropatt', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');

        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_gateway_url'] = $this->language->get('entry_gateway_url');
        $data['entry_store_key'] = $this->language->get('entry_store_key');
        $data['entry_store_secret'] = $this->language->get('entry_store_secret');
        $data['entry_webhook_url'] = $this->language->get('entry_webhook_url');
        $data['entry_send_quick_order'] = $this->language->get('entry_send_quick_order');
        $data['entry_debug'] = $this->language->get('entry_debug');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_test_connection'] = $this->language->get('button_test_connection');

        $data['user_token'] = $this->session->data['user_token'];

        $catalog_url = defined('HTTP_CATALOG') ? HTTP_CATALOG : (defined('HTTPS_CATALOG') ? HTTPS_CATALOG : '');
        $data['webhook_url_computed'] = rtrim($catalog_url, '/') . '/index.php?route=extension/module/tropatt/webhook';

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $fields = array(
            'module_tropatt_status',
            'module_tropatt_gateway_url',
            'module_tropatt_store_key',
            'module_tropatt_store_secret',
            'module_tropatt_send_quick_order',
            'module_tropatt_debug',
            'module_tropatt_status_mapping'
        );

        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } else {
                $data[$field] = $this->config->get($field);
            }
        }

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['action'] = $this->url->link('extension/module/tropatt', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['test_connection_url'] = $this->url->link('extension/module/tropatt/testConnection', 'user_token=' . $this->session->data['user_token'], true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/tropatt', $data));
    }

    public function testConnection() {
        $this->load->language('extension/module/tropatt');
        $json = array('success' => false, 'message' => '');

        $gateway_url = isset($this->request->post['gateway_url']) ? trim($this->request->post['gateway_url']) : $this->config->get('module_tropatt_gateway_url');
        $store_key = isset($this->request->post['store_key']) ? trim($this->request->post['store_key']) : $this->config->get('module_tropatt_store_key');
        $store_secret = isset($this->request->post['store_secret']) ? trim($this->request->post['store_secret']) : $this->config->get('module_tropatt_store_secret');

        if (empty($gateway_url) || empty($store_key) || empty($store_secret)) {
            $json['message'] = $this->language->get('error_gateway_url');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
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

        $ch = curl_init($ping_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $res = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $json['message'] = 'cURL Error: ' . $err;
        } elseif ($http_code === 200) {
            $decoded = json_decode($res, true);
            if (isset($decoded['code']) && $decoded['code'] === 'INGESTION_PONG') {
                $json['success'] = true;
                $json['message'] = $this->language->get('text_connection_ok');
            } else {
                $json['message'] = 'Unexpected response: ' . $res;
            }
        } else {
            $json['message'] = 'HTTP ' . $http_code . ': ' . $res;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install() {
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent(
            'tropatt_order_history',
            'catalog/model/checkout/order/addOrderHistory/after',
            'extension/module/tropatt/onOrderHistoryAdd'
        );
    }

    public function uninstall() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('tropatt_order_history');
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/tropatt')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}
