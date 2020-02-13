/**
 * wallee Prestashop
 *
 * This Prestashop module enables to process payments with wallee (https://www.wallee.com).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2019 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function ($) {

  var wallee_moto_order = {

    defaultOrderStateValue: 0, 

    init: function() {
      if (jQuery('#payment_module_name').length > 0) {
        jQuery('#payment_module_name').live('change', this.paymentMethodSelectionChanged.bind(this));
      }
    },

    paymentMethodSelectionChanged: function() {
      var value =  jQuery('#payment_module_name :selected').val();
      if (value == 'wallee') {
        this.updateWalleeAjaxPaymentMethods();
        this.selectWalleeInitOrderState();
      } else {
        this.destroy();
      }
    },

    destroy: function() {
      jQuery('#wallee_moto_payment_method_wrapper').remove();
    },

    updateWalleeAjaxPaymentMethods: function() {
      this.initWalleePaymentMethods();
      $.ajax({
        type: 'GET',
        url: wallee_admin_order_link,
        cache: false,
        dataType: 'json',
        data: {
          ajax: 1,
          action: 'loadWalleePayment',
          id_cart: id_cart
        },
        success: function (data) {
          console.log(data);
          this.defaultOrderStateValue = data.initOrderState;
          this.addWalleePaymentMethods(data.paymentMethods);
          this.selectWalleeInitOrderState();
          // updateIFrame(data.formUrl);
         }.bind(this)
      });
    },

    initWalleePaymentMethods: function() {
      if (jQuery('#wallee_moto_payment_method').length == 0) {
        $label = '<label class="control-label col-lg-3">Wallee Methode</label>';
        $select = '<select name="wallee_moto_payment_method" id="wallee_moto_payment_method" style="width:90%;"></select>';
        $loader = '<div class="wallee-loader" style="width:10px;height:10px;float:left;margin-right:10px"></div>'
        $('#summary_part .order_message_right .form-group').eq(3).after('<div class="form-group" id="wallee_moto_payment_method_wrapper">'+ $label +'<div class="col-lg-9">'+ $loader + $select +'</div></div>');
        $('#wallee_moto_payment_method').live('change', function() {
          var value =  jQuery('#wallee_moto_payment_method :selected').val();
          if (value !== '') {
            console.log(value);
            // this.walleePaymentMethodsChanged(value);
          }
        });
      }
    },

    addWalleePaymentMethods: function(paymentMethods) {
      $select = '';
      for(i = 0; i < paymentMethods.length; i++) {
        $select += '<option value="' + paymentMethods[i].value + '">'+paymentMethods[i].name+'</option>';
      }
      jQuery('#wallee_moto_payment_method')[0].innerHTML = $select;
      jQuery('#wallee_moto_payment_method_wrapper .wallee-loader').hide();
    },

    selectWalleeInitOrderState: function() {
      // wallee Processing
      jQuery('#id_order_state').val(this.defaultOrderStateValue);
    },

    updateIFrame: function(url) {
      if (jQuery('#wallee_moto_iframe').length == 0) {
        jQuery('#wallee_moto_payment_method_wrapper').after('<iframe id="wallee_moto_iframe"></iframe>');
      }
      $('#wallee_moto_iframe').attr('src', url);
    }
  }

  wallee_moto_order.init();

});