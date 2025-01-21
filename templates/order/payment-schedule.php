<?php
/**
 * Order payment schedule template
 *
 * @package WC_Flex_Pay\Templates
 */

if (!defined('ABSPATH')) {
    exit;
}

$payment_manager = new \WCFP\Payment();
?>

<div class="wcfp-order-schedule">
    <h3><?php esc_html_e('Payment Schedule', 'wc-flex-pay'); ?></h3>

    <table class="wcfp-schedule-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Due Date', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Status', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Actions', 'wc-flex-pay'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment) : 
                $due_date = strtotime($payment['due_date']);
                ?>
                <tr>
                    <td>
                        <?php echo esc_html(date_i18n(get_option('date_format'), $due_date)); ?>
                        <?php
                        if ($payment['status'] === 'overdue') {
                            $days_overdue = floor((current_time('timestamp') - $due_date) / (60 * 60 * 24));
                            echo ' <span class="overdue">(' . sprintf(
                                /* translators: %d: number of days */
                                _n('%d day overdue', '%d days overdue', $days_overdue, 'wc-flex-pay'),
                                $days_overdue
                            ) . ')</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo wc_price($payment['amount']); ?></td>
                    <td>
                        <mark class="payment-status status-<?php echo esc_attr($payment['status']); ?>">
                            <?php echo esc_html(ucfirst($payment['status'])); ?>
                        </mark>
                    </td>
                    <td>
                        <?php if ($payment['status'] === 'pending' || $payment['status'] === 'failed' || $payment['status'] === 'overdue') : ?>
                            <button type="button" 
                                    class="button process-payment" 
                                    data-payment-id="<?php echo esc_attr($payment['id']); ?>"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce('wcfp-frontend')); ?>">
                                <?php esc_html_e('Process Payment', 'wc-flex-pay'); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script type="text/javascript">
jQuery(function($) {
    $('.process-payment').on('click', function() {
        var button = $(this);
        var paymentId = button.data('payment-id');
        
        if (!confirm('<?php esc_html_e('Are you sure you want to process this payment?', 'wc-flex-pay'); ?>')) {
            return;
        }
        
        button.prop('disabled', true);
        
        $.ajax({
            url: wc_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wcfp_process_payment',
                payment_id: paymentId,
                nonce: button.data('nonce')
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data);
                    button.prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php esc_html_e('An error occurred. Please try again.', 'wc-flex-pay'); ?>');
                button.prop('disabled', false);
            }
        });
    });
});
</script>
