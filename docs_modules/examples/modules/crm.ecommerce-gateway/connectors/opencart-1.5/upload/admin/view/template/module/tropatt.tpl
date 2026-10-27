<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="button" id="button-test" class="btn btn-info"><i class="fa fa-exchange"></i> <?php echo $button_test_connection; ?></button>
        <button type="submit" form="form-module" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
      </div>
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($error_warning) { ?>
    <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <div id="alert-connection"></div>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
      </div>
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-module" class="form-horizontal">
          <ul class="nav nav-tabs">
            <li class="active"><a href="#tab-general" data-toggle="tab"><i class="fa fa-cog"></i> <?php echo $tab_general; ?></a></li>
            <li><a href="#tab-status" data-toggle="tab"><i class="fa fa-exchange"></i> <?php echo $tab_status_mapping; ?></a></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane active" id="tab-general">
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
                <div class="col-sm-10">
                  <select name="module_tropatt_status" id="input-status" class="form-control">
                    <?php if ($module_tropatt_status) { ?>
                    <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                    <option value="0"><?php echo $text_disabled; ?></option>
                    <?php } else { ?>
                    <option value="1"><?php echo $text_enabled; ?></option>
                    <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-gateway-url"><span data-toggle="tooltip" title="<?php echo $help_gateway_url; ?>"><?php echo $entry_gateway_url; ?></span></label>
                <div class="col-sm-10">
                  <input type="text" name="module_tropatt_gateway_url" value="<?php echo $module_tropatt_gateway_url; ?>" placeholder="https://crm.example.com/api/index.php?route=/_module/crm.ecommerce-gateway/v1" id="input-gateway-url" class="form-control" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-store-key"><span data-toggle="tooltip" title="<?php echo $help_store_key; ?>"><?php echo $entry_store_key; ?></span></label>
                <div class="col-sm-10">
                  <input type="text" name="module_tropatt_store_key" value="<?php echo $module_tropatt_store_key; ?>" placeholder="stk_..." id="input-store-key" class="form-control" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-store-secret"><span data-toggle="tooltip" title="<?php echo $help_store_secret; ?>"><?php echo $entry_store_secret; ?></span></label>
                <div class="col-sm-10">
                  <input type="password" name="module_tropatt_store_secret" value="<?php echo $module_tropatt_store_secret; ?>" placeholder="stk_secret_..." id="input-store-secret" class="form-control" autocomplete="new-password" />
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label"><?php echo $entry_webhook_url; ?></label>
                <div class="col-sm-10">
                  <div class="input-group">
                    <input type="text" readonly="readonly" value="<?php echo $webhook_url_computed; ?>" id="input-webhook-url" class="form-control" />
                    <span class="input-group-btn">
                      <button class="btn btn-default" type="button" data-copy="#input-webhook-url" title="<?php echo $button_copy; ?>"><i class="fa fa-copy"></i></button>
                    </span>
                  </div>
                  <small class="help-block"><?php echo $help_webhook_url; ?></small>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label"><?php echo $entry_stock_url; ?></label>
                <div class="col-sm-10">
                  <div class="input-group">
                    <input type="text" readonly="readonly" value="<?php echo $stock_url_computed; ?>" id="input-stock-url" class="form-control" />
                    <span class="input-group-btn">
                      <button class="btn btn-default" type="button" data-copy="#input-stock-url" title="<?php echo $button_copy; ?>"><i class="fa fa-copy"></i></button>
                    </span>
                  </div>
                  <small class="help-block"><?php echo $help_stock_url; ?></small>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label" for="input-paid-statuses"><span data-toggle="tooltip" title="<?php echo $help_paid_statuses; ?>"><?php echo $entry_paid_statuses; ?></span></label>
                <div class="col-sm-10">
                  <select name="module_tropatt_paid_statuses[]" id="input-paid-statuses" class="form-control" multiple size="6">
                    <?php foreach ($order_statuses as $order_status) { ?>
                    <option value="<?php echo $order_status['order_status_id']; ?>"<?php if (in_array((int)$order_status['order_status_id'], $module_tropatt_paid_statuses)) { ?> selected="selected"<?php } ?>><?php echo $order_status['name']; ?></option>
                    <?php } ?>
                  </select>
                  <small class="help-block"><?php echo $help_paid_statuses; ?></small>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-2 control-label"><span data-toggle="tooltip" title="<?php echo $help_debug; ?>"><?php echo $entry_debug; ?></span></label>
                <div class="col-sm-10">
                  <label class="radio-inline">
                    <input type="radio" name="module_tropatt_debug" value="1"<?php if ($module_tropatt_debug) { ?> checked="checked"<?php } ?> /> <?php echo $text_yes; ?>
                  </label>
                  <label class="radio-inline">
                    <input type="radio" name="module_tropatt_debug" value="0"<?php if (!$module_tropatt_debug) { ?> checked="checked"<?php } ?> /> <?php echo $text_no; ?>
                  </label>
                </div>
              </div>
            </div>
            <div class="tab-pane" id="tab-status">
              <p class="text-muted"><?php echo $help_status_mapping; ?></p>
              <div class="table-responsive">
                <table class="table table-bordered table-hover">
                  <thead>
                    <tr>
                      <th><?php echo $column_order_status; ?></th>
                      <th><?php echo $column_crm_stage; ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($order_statuses as $order_status) { ?>
                    <tr>
                      <td><strong><?php echo $order_status['name']; ?></strong> (ID: <?php echo $order_status['order_status_id']; ?>)</td>
                      <td>
                        <input type="text" name="module_tropatt_status_mapping[<?php echo $order_status['order_status_id']; ?>]" value="<?php echo isset($module_tropatt_status_mapping[$order_status['order_status_id']]) ? $module_tropatt_status_mapping[$order_status['order_status_id']] : ''; ?>" placeholder="stage_code" class="form-control input-sm" />
                      </td>
                    </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
              <p class="text-muted"><?php echo $entry_status_mapping; ?></p>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
$('#button-test').on('click', function() {
  var btn = $(this);
  btn.button('loading');
  $('#alert-connection').empty();
  $.ajax({
    url: '<?php echo $test_connection_url; ?>',
    type: 'post',
    data: {
      gateway_url: $('#input-gateway-url').val(),
      store_key: $('#input-store-key').val(),
      store_secret: $('#input-store-secret').val()
    },
    dataType: 'json',
    success: function(json) {
      btn.button('reset');
      if (json['success']) {
        $('#alert-connection').html('<div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> ' + json['message'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
      } else {
        $('#alert-connection').html('<div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> ' + json['message'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
      }
    },
    error: function(xhr, ajaxOptions, thrownError) {
      btn.button('reset');
      $('#alert-connection').html('<div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> AJAX Error: ' + thrownError + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
    }
  });
});

$('[data-copy]').on('click', function() {
  var input = $($(this).attr('data-copy'));
  input.trigger('select');
  try {
    document.execCommand('copy');
  } catch (e) {
    window.prompt('<?php echo $button_copy; ?>', input.val());
  }
  input.trigger('blur');
});
</script>
<?php echo $footer; ?>
