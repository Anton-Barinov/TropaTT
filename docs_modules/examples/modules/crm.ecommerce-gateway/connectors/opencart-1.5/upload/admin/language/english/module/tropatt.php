<?php
// Heading
$_['heading_title']          = 'TropaTT CRM — Sync Gateway';

// Text
$_['text_extension']          = 'Extensions';
$_['text_success']            = 'Success: You have modified TropaTT CRM module!';
$_['text_edit']               = 'Edit TropaTT CRM Module';
$_['text_connection_ok']      = 'Connection successful! TropaTT Gateway responded with INGESTION_PONG.';
$_['text_connection_fail']    = 'Connection failed: please check the Gateway URL, Store Key and Store Secret.';

// Tabs
$_['tab_general']             = 'General Settings';
$_['tab_status_mapping']      = 'Status Mapping';

// Entry
$_['entry_status']            = 'Module Status';
$_['entry_gateway_url']       = 'TropaTT Gateway URL';
$_['entry_store_key']         = 'Store Public Key';
$_['entry_store_secret']      = 'Store Secret Key';
$_['entry_webhook_url']       = 'Inbound Webhook URL';
$_['entry_stock_url']         = 'Stock Sync URL';
$_['entry_debug']             = 'Debug Logging';
$_['entry_paid_statuses']     = 'Order statuses meaning "paid"';
$_['entry_status_mapping']    = 'CRM stage codes used for reverse status sync: new, in_progress, completed, cancelled.';

// Help
$_['help_gateway_url']        = 'Full URL to the TropaTT Ingestion API, e.g. https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1';
$_['help_store_key']          = 'Store public key stk_... created in the TropaTT CRM control panel (E-Commerce Gateway module).';
$_['help_store_secret']       = 'Store secret returned once when the store is registered in TropaTT CRM.';
$_['help_webhook_url']        = 'Copy this URL into the store card in TropaTT CRM: it is how the CRM reports CRM task stage changes back to the store.';
$_['help_stock_url']          = 'Batch stock sync: mode=pull returns a paginated catalogue snapshot, mode=push applies a batch of stock levels in one transaction with row locks.';
$_['help_debug']              = 'Write gateway traffic to system/storage/logs/tropatt.log.';
$_['help_paid_statuses']      = 'Statuses that mean the order is paid, so it is pushed to the CRM with the paid flag set. Use Ctrl (Cmd) + click to select several.';
$_['help_status_mapping']     = 'Map OpenCart order statuses to TropaTT CRM task stages. When a task stage changes in the CRM, the module moves the OpenCart order to the mapped status.';

// Columns
$_['column_order_status']     = 'OpenCart status';
$_['column_crm_stage']        = 'CRM stage code (e.g. new, in_progress, completed, cancelled)';

// Buttons
$_['button_test_connection']  = 'Test Connection';
$_['button_copy']             = 'Copy';

// Error
$_['error_permission']        = 'Warning: You do not have permission to modify the TropaTT CRM module!';
$_['error_fields_required']   = 'Gateway URL, Store Key and Store Secret are required!';
$_['error_unexpected_response'] = 'Unexpected gateway response: %s. Make sure the URL points to the TropaTT Ingestion API.';
