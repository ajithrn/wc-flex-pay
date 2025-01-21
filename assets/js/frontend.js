jQuery(function($) {
    'use strict';

    var WCFP_Frontend = {
        init: function() {
            this.initPaymentTypeSelection();
            this.initAddToCart();
            
            // Set initial value based on checked radio
            var $checkedRadio = $('.wcfp-payment-radio:checked');
            if ($checkedRadio.length) {
                $('.wcfp-payment-type-input').val($checkedRadio.val());
            }
        },

        // Initialize payment type selection
        initPaymentTypeSelection: function() {
            $('.wcfp-payment-radio').on('change', function() {
                var value = $(this).val();
                
                // Update hidden input that gets submitted with form
                $('.wcfp-payment-type-input').val(value);
                
                // Show/hide payment schedule
                if (value === 'installment') {
                    $('.wcfp-payment-schedule').slideDown();
                } else {
                    $('.wcfp-payment-schedule').slideUp();
                }
            });
        },

        // Initialize add to cart handling
        initAddToCart: function() {
            $('form.cart').on('submit', function(e) {
                var $form = $(this);
                var $paymentType = $form.find('.wcfp-payment-type-input');
                var $checkedRadio = $form.find('.wcfp-payment-radio:checked');

                // Update hidden input one last time before submit
                if ($checkedRadio.length) {
                    $paymentType.val($checkedRadio.val());
                }

                if ($paymentType.length && !$paymentType.val()) {
                    e.preventDefault();
                    alert(wcfp_params.i18n.select_payment_type);
                    return false;
                }
            });
        }
    };

    // Initialize on document ready
    WCFP_Frontend.init();
});
