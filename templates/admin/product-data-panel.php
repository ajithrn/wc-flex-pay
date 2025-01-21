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
                    global $wpdb;
                    $schedules = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}wcfp_payment_schedules WHERE product_id = %d ORDER BY installment_number ASC",
                            $product->get_id()
                        ),
                        ARRAY_A
                    );

                    if (!empty($schedules)) :
                        foreach ($schedules as $schedule) :
                            ?>
                            <tr>
                                <td>
                                    <?php
                                    printf(
                                        /* translators: %d: installment number */
                                        esc_html__('Payment %d', 'wc-flex-pay'),
                                        $schedule['installment_number']
                                    );
                                    ?>
                                    <input type="hidden" 
                                           name="wcfp_schedule[<?php echo esc_attr($schedule['installment_number']); ?>][installment_number]" 
                                           value="<?php echo esc_attr($schedule['installment_number']); ?>">
                                </td>
                                <td>
                                    <input type="number" 
                                           name="wcfp_schedule[<?php echo esc_attr($schedule['installment_number']); ?>][amount]" 
                                           value="<?php echo esc_attr($schedule['amount']); ?>"
                                           step="0.01"
                                           min="0"
                                           required>
                                </td>
                                <td>
                                    <input type="date" 
                                           name="wcfp_schedule[<?php echo esc_attr($schedule['installment_number']); ?>][due_date]" 
                                           value="<?php echo esc_attr($schedule['due_date']); ?>"
                                           required>
                                </td>
                                <td>
                                    <button type="button" class="button remove-schedule">
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
                            <button type="button" class="button add-schedule">
                                <?php esc_html_e('Add Payment', 'wc-flex-pay'); ?>
                            </button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script type="text/template" id="tmpl-wcfp-schedule-row">
    <tr>
        <td>
            <?php esc_html_e('Payment', 'wc-flex-pay'); ?> {{data.number}}
            <input type="hidden" name="wcfp_schedule[{{data.number}}][installment_number]" value="{{data.number}}">
        </td>
        <td>
            <input type="number" 
                   name="wcfp_schedule[{{data.number}}][amount]" 
                   value="" 
                   step="0.01"
                   min="0"
                   required>
        </td>
        <td>
            <input type="date" 
                   name="wcfp_schedule[{{data.number}}][due_date]" 
                   value="" 
                   required>
        </td>
        <td>
            <button type="button" class="button remove-schedule">
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

    // Add new schedule row
    $('.add-schedule').on('click', function() {
        var template = wp.template('wcfp-schedule-row');
        var $tbody = $(this).closest('table').find('tbody');
        var number = $tbody.children().length + 1;
        
        $tbody.append(template({ number: number }));
    });

    // Remove schedule row
    $(document).on('click', '.remove-schedule', function() {
        $(this).closest('tr').remove();
        
        // Update installment numbers
        $('.flex-pay-schedule tbody tr').each(function(index) {
            var number = index + 1;
            $(this).find('td:first-child').html(
                '<?php esc_html_e('Payment', 'wc-flex-pay'); ?> ' + number +
                '<input type="hidden" name="wcfp_schedule[' + number + '][installment_number]" value="' + number + '">'
            );
            
            // Update input names
            $(this).find('input[name*="[amount]"]').attr('name', 'wcfp_schedule[' + number + '][amount]');
            $(this).find('input[name*="[due_date]"]').attr('name', 'wcfp_schedule[' + number + '][due_date]');
        });
    });
});
</script>
