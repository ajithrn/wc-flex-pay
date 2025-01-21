<?php
/**
 * Order meta box template
 *
 * @package WC_Flex_Pay\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$payments = $this->get_order_payments($order->get_id());
if (empty($payments)) {
    ?>
    <p><?php esc_html_e('No Flex Pay payments found for this order.', 'wc-flex-pay'); ?></p>
    <?php
    return;
}

$total_paid = 0;
$total_remaining = 0;
foreach ($payments as $payment) {
    if ($payment['status'] === 'completed') {
        $total_paid += $payment['amount'];
    } else {
        $total_remaining += $payment['amount'];
    }
}
?>

<div class="wcfp-order-payments">
    <div class="wcfp-payment-summary">
        <p>
            <strong><?php esc_html_e('Total Paid:', 'wc-flex-pay'); ?></strong>
            <?php echo wc_price($total_paid); ?>
        </p>
        <p>
            <strong><?php esc_html_e('Remaining Balance:', 'wc-flex-pay'); ?></strong>
            <?php echo wc_price($total_remaining); ?>
        </p>
    </div>

    <table class="widefat">
        <thead>
            <tr>
                <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Due Date', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Status', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Transaction ID', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Actions', 'wc-flex-pay'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            foreach ($payments as $payment) : 
                $status_class = $this->get_status_class($payment['status']);
                $is_overdue = strtotime($payment['due_date']) < current_time('timestamp') && $payment['status'] === 'pending';
                if ($is_overdue) {
                    $status_class = 'overdue';
                }
            ?>
                <tr>
                    <td>
                        <?php 
                        printf(
                            /* translators: %d: installment number */
                            esc_html__('Payment %d', 'wc-flex-pay'),
                            $payment['installment_number']
                        );
                        ?>
                    </td>
                    <td><?php echo wc_price($payment['amount']); ?></td>
                    <td>
                        <?php 
                        echo date_i18n(
                            get_option('date_format'), 
                            strtotime($payment['due_date'])
                        );
                        if ($is_overdue) {
                            echo ' <span class="wcfp-status overdue">' . esc_html__('Overdue', 'wc-flex-pay') . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <span class="wcfp-status <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html(ucfirst($payment['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo $payment['transaction_id'] ? esc_html($payment['transaction_id']) : '—'; ?>
                    </td>
                    <td>
                        <?php if ($payment['status'] === 'pending') : ?>
                            <button type="button" 
                                    class="button process-payment" 
                                    data-payment-id="<?php echo esc_attr($payment['id']); ?>"
                                    data-nonce="<?php echo wp_create_nonce('wcfp-admin'); ?>">
                                <?php esc_html_e('Process Payment', 'wc-flex-pay'); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($payment['notes'])) : ?>
                    <tr class="payment-notes">
                        <td colspan="6">
                            <div class="wcfp-payment-notes">
                                <strong><?php esc_html_e('Notes:', 'wc-flex-pay'); ?></strong>
                                <?php echo wp_kses_post($payment['notes']); ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(function($) {
    $('.process-payment').on('click', function() {
        var button = $(this);
        var paymentId = button.data('payment-id');
        var nonce = button.data('nonce');
        
        if (confirm(wcfp_admin_params.i18n.confirm_process)) {
            button.prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'wcfp_process_payment',
                payment_id: paymentId,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                    button.prop('disabled', false);
                }
            });
        }
    });
});
</script>
