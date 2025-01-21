<?php
/**
 * Admin payment history template
 *
 * @package WC_Flex_Pay\Admin\Templates
 */

if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_woocommerce')) {
    return;
}

$payment_manager = new \WCFP\Payment();
?>

<div class="wcfp-payment-history">
    <h3><?php esc_html_e('Payment History', 'wc-flex-pay'); ?></h3>

    <?php foreach ($payments as $payment) : ?>
        <?php
        $history = $payment_manager->get_payment_history($payment['id']);
        $due_date = strtotime($payment['due_date']);
        ?>
        <div class="payment-entry">
            <div class="payment-header">
                <h4>
                    <?php
                    printf(
                        /* translators: %1$d: installment number, %2$s: formatted amount */
                        esc_html__('Installment %1$d - %2$s', 'wc-flex-pay'),
                        $payment['installment_number'],
                        wc_price($payment['amount'])
                    );
                    ?>
                </h4>
                <mark class="payment-status status-<?php echo esc_attr($payment['status']); ?>">
                    <?php echo esc_html(ucfirst($payment['status'])); ?>
                </mark>
            </div>

            <div class="payment-details">
                <p>
                    <strong><?php esc_html_e('Due Date:', 'wc-flex-pay'); ?></strong>
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
                </p>

                <?php if (!empty($payment['transaction_id'])) : ?>
                    <p>
                        <strong><?php esc_html_e('Transaction ID:', 'wc-flex-pay'); ?></strong>
                        <?php echo esc_html($payment['transaction_id']); ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($history)) : ?>
                <div class="payment-logs">
                    <h5><?php esc_html_e('Activity Log', 'wc-flex-pay'); ?></h5>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', 'wc-flex-pay'); ?></th>
                                <th><?php esc_html_e('Type', 'wc-flex-pay'); ?></th>
                                <th><?php esc_html_e('Message', 'wc-flex-pay'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $log) : ?>
                                <tr>
                                    <td>
                                        <?php
                                        $log_date = strtotime($log['created_at']);
                                        echo esc_html(date_i18n(
                                            get_option('date_format') . ' ' . get_option('time_format'),
                                            $log_date
                                        ));
                                        ?>
                                    </td>
                                    <td>
                                        <mark class="log-type type-<?php echo esc_attr($log['type']); ?>">
                                            <?php echo esc_html(ucfirst($log['type'])); ?>
                                        </mark>
                                    </td>
                                    <td><?php echo esc_html($log['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($payment['status'] === 'pending' || $payment['status'] === 'failed' || $payment['status'] === 'overdue') : ?>
                <div class="payment-actions">
                    <button type="button" 
                            class="button process-payment" 
                            data-payment-id="<?php echo esc_attr($payment['id']); ?>"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('wcfp-admin')); ?>">
                        <?php esc_html_e('Process Payment', 'wc-flex-pay'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
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
            url: ajaxurl,
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
