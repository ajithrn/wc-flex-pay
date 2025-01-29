<?php
/**
 * Product data panel template
 *
 * @package WC_Flex_Pay\Admin\Templates
 */

if (!defined('ABSPATH')) {
    exit;
}

$enabled = $product->get_meta('_wcfp_enabled');
?>

<div id="flex_pay_product_data" class="panel woocommerce_options_panel">
    <div class="options_group">
        <?php
        woocommerce_wp_checkbox(array(
            'id'          => '_wcfp_enabled',
            'label'       => __('Enable Flex Pay', 'wc-flex-pay'),
            'description' => __('Allow customers to pay for this product in installments.', 'wc-flex-pay'),
            'value'       => $enabled,
        ));
        ?>
    </div>

    <div class="options_group flex-pay-schedule" style="display: <?php echo 'yes' === $enabled ? 'block' : 'none'; ?>">
        <div class="form-field">
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                        <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                        <th><?php esc_html_e('Due Date', 'wc-flex-pay'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $payments = get_post_meta($product->get_id(), '_wcfp_payments', true);
                    $installments = !empty($payments['installments']) ? $payments['installments'] : array();
                    
                    if (!empty($installments)) :
                        foreach ($installments as $installment) :
                            ?>
                            <tr>
                                <td>
                                    <?php
                                    printf(
                                        /* translators: %d: installment number */
                                        esc_html__('Payment %d', 'wc-flex-pay'),
                                        $installment['number']
                                    );
                                    ?>
                                    <input type="hidden" 
                                           name="wcfp_installments[<?php echo esc_attr($installment['number']); ?>][number]" 
                                           value="<?php echo esc_attr($installment['number']); ?>">
                                </td>
                                <td>
                                    <input type="number" 
                                           name="wcfp_installments[<?php echo esc_attr($installment['number']); ?>][amount]" 
                                           value="<?php echo esc_attr($installment['amount']); ?>"
                                           step="0.01"
                                           min="0"
                                           required>
                                </td>
                                <td>
                                    <input type="date" 
                                           name="wcfp_installments[<?php echo esc_attr($installment['number']); ?>][due_date]" 
                                           value="<?php echo esc_attr($installment['due_date']); ?>"
                                           required>
                                </td>
                                <td>
                                    <button type="button" class="button remove-installment">
                                        <?php esc_html_e('Remove', 'wc-flex-pay'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                    endif;
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">
                            <button type="button" class="button add-installment">
                                <?php esc_html_e('Add Installment', 'wc-flex-pay'); ?>
                            </button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script type="text/template" id="tmpl-wcfp-installment-row">
    <tr>
        <td>
            <?php esc_html_e('Payment', 'wc-flex-pay'); ?> {{data.number}}
            <input type="hidden" name="wcfp_installments[{{data.number}}][number]" value="{{data.number}}">
        </td>
        <td>
            <input type="number" 
                   name="wcfp_installments[{{data.number}}][amount]" 
                   value="" 
                   step="0.01"
                   min="0"
                   required>
        </td>
        <td>
            <input type="date" 
                   name="wcfp_installments[{{data.number}}][due_date]" 
                   value="" 
                   required>
        </td>
        <td>
            <button type="button" class="button remove-installment">
                <?php esc_html_e('Remove', 'wc-flex-pay'); ?>
            </button>
        </td>
    </tr>
</script>

<script>
jQuery(function($) {
    // Handle enable/disable toggle
    $('#_wcfp_enabled').change(function() {
        var $schedule = $('.flex-pay-schedule');
        if ($(this).is(':checked')) {
            $schedule.slideDown(300);
        } else {
            $schedule.slideUp(300);
        }
    });

    // Add new installment row
    $('.add-installment').on('click', function() {
        var template = wp.template('wcfp-installment-row');
        var $tbody = $(this).closest('table').find('tbody');
        var number = $tbody.children().length + 1;
        
        $tbody.append(template({ number: number }));
        updateTotalAmount();
    });

    // Remove installment row
    $(document).on('click', '.remove-installment', function() {
        $(this).closest('tr').remove();
        updateInstallmentNumbers();
        updateTotalAmount();
    });

    // Update total amount when installment amounts change
    $(document).on('change', 'input[name^="wcfp_installments"][name$="[amount]"]', function() {
        updateTotalAmount();
    });

    // Update installment numbers based on date order
    function updateInstallmentNumbers() {
        var $rows = $('.flex-pay-schedule tbody tr').get();
        
        // Sort rows by date
        $rows.sort(function(a, b) {
            var dateA = $(a).find('input[name*="[due_date]"]').val();
            var dateB = $(b).find('input[name*="[due_date]"]').val();
            return new Date(dateA) - new Date(dateB);
        });

        // Reorder rows in the table
        var $tbody = $('.flex-pay-schedule tbody');
        $.each($rows, function(idx, row) {
            $tbody.append(row);
        });

        // Update numbers and names
        $('.flex-pay-schedule tbody tr').each(function(index) {
            var number = index + 1;
            $(this).find('td:first-child').html(
                '<?php esc_html_e('Payment', 'wc-flex-pay'); ?> ' + number +
                '<input type="hidden" name="wcfp_installments[' + number + '][number]" value="' + number + '">'
            );
            
            // Update input names
            $(this).find('input[name*="[amount]"]').attr('name', 'wcfp_installments[' + number + '][amount]');
            $(this).find('input[name*="[due_date]"]').attr('name', 'wcfp_installments[' + number + '][due_date]');
        });
    }

    // Function to update total amount
    function updateTotalAmount() {
        var total = 0;
        $('.flex-pay-schedule tbody tr').each(function() {
            var amount = parseFloat($(this).find('input[name^="wcfp_installments"][name$="[amount]"]').val()) || 0;
            total += amount;
        });
        $('#_regular_price').val(total.toFixed(2));
    }

    // Auto-sort when dates change
    $(document).on('change', 'input[name^="wcfp_installments"][name$="[due_date]"]', function() {
        updateInstallmentNumbers();
    });
});
</script>
