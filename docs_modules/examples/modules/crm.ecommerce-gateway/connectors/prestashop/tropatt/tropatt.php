<?php
/**
 * TropaTT CRM — e-commerce gateway connector for PrestaShop.
 *
 * The module registers two hooks, queues every order event and delivers it to
 * the TropaTT gateway with an HMAC-SHA256 signature. The CRM webhook
 * (`index.php?fc=module&module=tropatt&controller=webhook`) applies status
 * changes through the PrestaShop order state machine with echo suppression.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/TropattSignature.php';
require_once __DIR__ . '/classes/TropattClient.php';
require_once __DIR__ . '/classes/TropattOrderMapper.php';
require_once __DIR__ . '/classes/TropattStatusMapper.php';
require_once __DIR__ . '/classes/TropattFileQueue.php';
require_once __DIR__ . '/classes/TropattLogger.php';
require_once __DIR__ . '/classes/TropattConfig.php';

class Tropatt extends Module
{
    /** Storage flag: a status written by the CRM webhook must not be pushed back. */
    const SKIP_SYNC = 'TROPATT_SKIP_SYNC';

    public function __construct()
    {
        $this->name = 'tropatt';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Anton Barinov';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6.0.0', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('TropaTT CRM — e-commerce gateway');
        $this->description = $this->l('Two-way synchronisation of orders, customers and statuses with the TropaTT self-hosted CRM.');
        $this->confirmUninstall = $this->l('Remove the TropaTT CRM connector?');
    }

    /**
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('actionObjectOrderUpdateAfter')
            && TropattConfig::installDefaults();
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return TropattConfig::removeDefaults() && parent::uninstall();
    }

    /**
     * Hook: a new order has been validated.
     *
     * @param array $params
     * @return void
     */
    public function hookActionValidateOrder($params)
    {
        if (empty($params['order']) || !is_object($params['order'])) {
            return;
        }

        $this->dispatch((int)$params['order']->id, 'created');
    }

    /**
     * Hook: an order status changed.
     *
     * @param array $params
     * @return void
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        if (TropattConfig::isEchoSuppressed()) {
            return;
        }

        if (empty($params['id_order'])) {
            return;
        }

        $this->dispatch((int)$params['id_order'], 'status_changed');
    }

    /**
     * Hook: any order update.
     *
     * @param array $params
     * @return void
     */
    public function hookActionObjectOrderUpdateAfter($params)
    {
        if (TropattConfig::isEchoSuppressed()) {
            return;
        }

        if (empty($params['object']) || !is_object($params['object'])) {
            return;
        }

        $this->dispatch((int)$params['object']->id, 'updated');
    }

    /**
     * Map one order and deliver it; a failure is spooled, never thrown at the shop.
     *
     * @param int    $orderId
     * @param string $event
     * @return void
     */
    public function dispatch($orderId, $event)
    {
        $config = TropattConfig::load();

        if (!$orderId || empty($config['enabled']) || !TropattConfig::isConfigured($config)) {
            return;
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        $data = TropattOrderMapper::collect($order);
        $stage = TropattStatusMapper::crmStageFor($config['status_mapping'], (string)$data['state_id']);
        $canonical = TropattOrderMapper::toCanonical($data, $stage === null ? $config['default_stage'] : $stage);

        $client = new TropattClient($config['gateway_url'], $config['store_key'], $config['store_secret'], $config['timeout']);
        $result = $client->pushOrder($canonical);

        if (!$result['success']) {
            TropattFileQueue::enqueue($canonical, $config['queue_dir']);
            TropattLogger::write($config, 'order spooled after a delivery failure', array(
                'external_id' => $canonical['external_id'],
                'http_code' => $result['http_code'],
                'event' => $event,
            ));
        }
    }

    /**
     * Settings form.
     *
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitTropatt')) {
            TropattConfig::save(array(
                'enabled' => (int)Tools::getValue('TROPATT_ENABLED'),
                'gateway_url' => (string)Tools::getValue('TROPATT_GATEWAY_URL'),
                'store_key' => (string)Tools::getValue('TROPATT_STORE_KEY'),
                'store_secret' => (string)Tools::getValue('TROPATT_STORE_SECRET'),
                'webhook_secret' => (string)Tools::getValue('TROPATT_WEBHOOK_SECRET'),
                'default_stage' => (string)Tools::getValue('TROPATT_DEFAULT_STAGE'),
                'status_mapping' => (string)Tools::getValue('TROPATT_STATUS_MAPPING'),
                'debug' => (int)Tools::getValue('TROPATT_DEBUG'),
            ));
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        $fields = array(
            'TROPATT_ENABLED' => array('label' => $this->l('Module enabled'), 'type' => 'switch'),
            'TROPATT_GATEWAY_URL' => array('label' => $this->l('TropaTT gateway URL'), 'type' => 'text'),
            'TROPATT_STORE_KEY' => array('label' => $this->l('Store public key (stk_...)'), 'type' => 'text'),
            'TROPATT_STORE_SECRET' => array('label' => $this->l('Store secret'), 'type' => 'text'),
            'TROPATT_WEBHOOK_SECRET' => array('label' => $this->l('Webhook secret'), 'type' => 'text'),
            'TROPATT_DEFAULT_STAGE' => array('label' => $this->l('Default CRM stage'), 'type' => 'text'),
            'TROPATT_STATUS_MAPPING' => array('label' => $this->l('Status mapping (PrestaShop state id = CRM stage)'), 'type' => 'textarea'),
            'TROPATT_DEBUG' => array('label' => $this->l('Debug logging'), 'type' => 'switch'),
        );

        $helper = new HelperForm();
        $helper->submit_action = 'submitTropatt';
        $helper->fields_value = TropattConfig::loadRaw();
        $helper->fields_value['TROPATT_WEBHOOK_URL_DISPLAY'] = TropattConfig::webhookUrl();

        $inputs = array();
        foreach ($fields as $name => $field) {
            $input = array('type' => $field['type'], 'label' => $field['label'], 'name' => $name);
            if ($field['type'] === 'switch') {
                $input['values'] = array(
                    array('id' => 'on', 'value' => 1, 'label' => $this->l('Yes')),
                    array('id' => 'off', 'value' => 0, 'label' => $this->l('No')),
                );
            }
            $inputs[] = $input;
        }

        $inputs[] = array('type' => 'free', 'label' => $this->l('Inbound webhook URL'), 'name' => 'TROPATT_WEBHOOK_URL_DISPLAY');

        return $output . $helper->generateForm(array(array(
            'form' => array(
                'legend' => array('title' => $this->l('TropaTT CRM settings'), 'icon' => 'icon-cogs'),
                'input' => $inputs,
                'submit' => array('title' => $this->l('Save')),
            ),
        )));
    }
}
