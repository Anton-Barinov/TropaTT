<?php
/**
 * TropaTT CRM — gateway connector for OpenCart / ocStore 2.3.x.
 *
 * Admin controller. OpenCart 2.3 specifics:
 *   - the extension list is `extension/extension&type=module`, not
 *     `marketplace/extension`;
 *   - the session token is `token`, not `user_token`;
 *   - extension models live under `extension/`, so events are registered
 *     through `model('extension/event')` with `addEvent()` / `deleteEvent()`.
 */
class ControllerExtensionModuleTropatt extends Controller {
    private $error = array();

    const EVENT_CODE = 'tropatt_order_history';
    const EVENT_TRIGGER = 'catalog/model/checkout/order/addOrderHistory/after';
    const EVENT_ACTION = 'extension/module/tropatt/onOrderHistoryAdd';

    public function index() {
        $this->load->language('extension/module/tropatt');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_tropatt', $this->sanitize($this->request->post));

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_yes'] = $this->language->get('text_yes');
        $data['text_no'] = $this->language->get('text_no');
        $data['text_connection_ok'] = $this->language->get('text_connection_ok');
        $data['text_connection_fail'] = $this->language->get('text_connection_fail');

        $data['tab_general'] = $this->language->get('tab_general');
        $data['tab_status_mapping'] = $this->language->get('tab_status_mapping');

        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_gateway_url'] = $this->language->get('entry_gateway_url');
        $data['entry_store_key'] = $this->language->get('entry_store_key');
        $data['entry_store_secret'] = $this->language->get('entry_store_secret');
        $data['entry_webhook_url'] = $this->language->get('entry_webhook_url');
        $data['entry_stock_url'] = $this->language->get('entry_stock_url');
        $data['entry_debug'] = $this->language->get('entry_debug');
        $data['entry_paid_statuses'] = $this->language->get('entry_paid_statuses');
        $data['entry_status_mapping'] = $this->language->get('entry_status_mapping');

        $data['help_gateway_url'] = $this->language->get('help_gateway_url');
        $data['help_store_key'] = $this->language->get('help_store_key');
        $data['help_store_secret'] = $this->language->get('help_store_secret');
        $data['help_webhook_url'] = $this->language->get('help_webhook_url');
        $data['help_stock_url'] = $this->language->get('help_stock_url');
        $data['help_debug'] = $this->language->get('help_debug');
        $data['help_paid_statuses'] = $this->language->get('help_paid_statuses');
        $data['help_status_mapping'] = $this->language->get('help_status_mapping');

        $data['column_order_status'] = $this->language->get('column_order_status');
        $data['column_crm_stage'] = $this->language->get('column_crm_stage');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_test_connection'] = $this->language->get('button_test_connection');
        $data['button_copy'] = $this->language->get('button_copy');

        $data['token'] = $this->session->data['token'];

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
            'module_tropatt_debug',
            'module_tropatt_paid_statuses',
            'module_tropatt_status_mapping',
        );

        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } else {
                $data[$field] = $this->config->get($field);
            }
        }

        if (!is_array($data['module_tropatt_paid_statuses'])) {
            $data['module_tropatt_paid_statuses'] = array();
        }
        if (!is_array($data['module_tropatt_status_mapping'])) {
            $data['module_tropatt_status_mapping'] = array();
        }

        $catalog_url = '';
        if (defined('HTTP_CATALOG')) {
            $catalog_url = HTTP_CATALOG;
        }
        if ($catalog_url === '') {
            $catalog_url = (string)$this->config->get('config_url');
        }
        $catalog_url = rtrim($catalog_url, '/');

        $data['webhook_url_computed'] = $catalog_url . '/index.php?route=extension/module/tropatt/webhook';
        $data['stock_url_computed'] = $catalog_url . '/index.php?route=extension/module/tropatt/syncStock';

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true),
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true),
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/tropatt', 'token=' . $this->session->data['token'], true),
        );

        $data['action'] = $this->url->link('extension/module/tropatt', 'token=' . $this->session->data['token'], true);
        $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=module', true);
        $data['test_connection_url'] = $this->url->link('extension/module/tropatt/testConnection', 'token=' . $this->session->data['token'], true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/tropatt', $data));
    }

    /**
     * Register the order-history hook. The event row is the whole point of the
     * connector on OpenCart 2.3: the core `event` table plus
     * `catalog/controller/startup/event.php` make the hook work without
     * touching a single core file (install.xml is only the fallback).
     *
     * @return void
     */
    public function install() {
        $this->load->model('extension/event');

        // Re-installation must not leave a duplicate trigger behind.
        $this->model_extension_event->deleteEvent(self::EVENT_CODE);
        $this->model_extension_event->addEvent(self::EVENT_CODE, self::EVENT_TRIGGER, self::EVENT_ACTION);
    }

    /**
     * @return void
     */
    public function uninstall() {
        $this->load->model('extension/event');
        $this->model_extension_event->deleteEvent(self::EVENT_CODE);
    }

    /**
     * Signed GET /ping against the gateway, used by the "Test connection"
     * button with the values currently typed into the form.
     *
     * @return void
     */
    public function testConnection() {
        $this->load->language('extension/module/tropatt');

        $json = array('success' => false, 'message' => '');

        $gateway_url = isset($this->request->post['gateway_url']) ? trim((string)$this->request->post['gateway_url']) : (string)$this->config->get('module_tropatt_gateway_url');
        $store_key = isset($this->request->post['store_key']) ? trim((string)$this->request->post['store_key']) : (string)$this->config->get('module_tropatt_store_key');
        $store_secret = isset($this->request->post['store_secret']) ? (string)$this->request->post['store_secret'] : (string)$this->config->get('module_tropatt_store_secret');

        if ($gateway_url === '' || $store_key === '' || $store_secret === '') {
            $json['message'] = $this->language->get('error_fields_required');
            $this->respondJson($json);
            return;
        }

        $this->load->model('extension/module/tropatt');
        $model = $this->model_extension_module_tropatt;

        $result = $model->request(
            'GET',
            '/_module/crm.ecommerce-gateway/v1/ping',
            rtrim($gateway_url, '/') . '/ping',
            '',
            $store_key,
            $store_secret
        );

        if (!$result['success']) {
            $json['message'] = $result['error'];
            $this->respondJson($json);
            return;
        }

        if ($result['code'] === 'INGESTION_PONG') {
            $json['success'] = true;
            $json['message'] = $this->language->get('text_connection_ok');
        } else {
            $json['message'] = sprintf($this->language->get('error_unexpected_response'), (string)$result['code']);
        }

        $this->respondJson($json);
    }

    /**
     * Keep stored settings predictable: the mapping and the paid status list
     * are the two fields where a stale checkbox or a whitespace-padded value
     * would silently change behaviour.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private function sanitize(array $post) {
        foreach (array('module_tropatt_gateway_url', 'module_tropatt_store_key', 'module_tropatt_store_secret') as $key) {
            if (isset($post[$key]) && is_scalar($post[$key])) {
                $post[$key] = trim((string)$post[$key]);
            }
        }

        $mapping = array();
        if (isset($post['module_tropatt_status_mapping']) && is_array($post['module_tropatt_status_mapping'])) {
            foreach ($post['module_tropatt_status_mapping'] as $order_status_id => $stage_code) {
                $stage_code = trim((string)$stage_code);
                if ($stage_code !== '') {
                    $mapping[(int)$order_status_id] = $stage_code;
                }
            }
        }
        $post['module_tropatt_status_mapping'] = $mapping;

        $paid = array();
        if (isset($post['module_tropatt_paid_statuses']) && is_array($post['module_tropatt_paid_statuses'])) {
            foreach ($post['module_tropatt_paid_statuses'] as $status_id) {
                if (is_scalar($status_id)) {
                    $paid[] = (int)$status_id;
                }
            }
        }
        $post['module_tropatt_paid_statuses'] = $paid;

        foreach (array('module_tropatt_status', 'module_tropatt_debug') as $key) {
            $post[$key] = !empty($post[$key]) ? 1 : 0;
        }

        return $post;
    }

    /**
     * @param array<string,mixed> $json
     * @return void
     */
    private function respondJson(array $json) {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @return bool
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/tropatt')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
