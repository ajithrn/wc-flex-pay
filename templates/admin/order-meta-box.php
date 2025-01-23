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

<?php
// Check if this is a sub-order
$is_sub_order = !empty($order->get_meta('_wcfp_parent_order'));
$parent_order_id = $order->get_meta('_wcfp_parent_order');
$installment_number = $order->get_meta('_wcfp_installment_number');

if ($is_sub_order) {
    $parent_order = wc_get_order($parent_order_id);
    ?>
    <div class="wcfp-sub-order-info">
        <p>
            <strong><?php esc_html_e('Parent Order:', 'wc-flex-pay'); ?></strong>
            <a href="<?php echo esc_url(get_edit_post_link($parent_order_id)); ?>">
                #<?php echo esc_html($parent_order->get_order_number()); ?>
            </a>
        </p>
        <p>
            <strong><?php esc_html_e('Installment:', 'wc-flex-pay'); ?></strong>
            <?php echo esc_html($installment_number); ?>
        </p>
    </div>
    <?php
}
?>

<div class="wcfp-order-payments">
    <div class="wcfp-payment-summary">
        <div class="wcfp-summary-grid">
            <div class="wcfp-summary-item">
                <span class="wcfp-label"><?php esc_html_e('Total Paid:', 'wc-flex-pay'); ?></span>
                <span class="wcfp-value wcfp-success"><?php echo wc_price($total_paid); ?></span>
            </div>
            <div class="wcfp-summary-item">
                <span class="wcfp-label"><?php esc_html_e('Remaining Balance:', 'wc-flex-pay'); ?></span>
                <span class="wcfp-value wcfp-warning"><?php echo wc_price($total_remaining); ?></span>
            </div>
            <?php if (!$is_sub_order) : ?>
                <div class="wcfp-summary-item">
                    <span class="wcfp-label"><?php esc_html_e('Sub-orders:', 'wc-flex-pay'); ?></span>
                    <span class="wcfp-value">
                        <?php
                        $sub_orders = wc_get_orders(array(
                            'meta_key' => '_wcfp_parent_order',
                            'meta_value' => $order->get_id(),
                            'return' => 'ids',
                        ));
                        printf(
                            /* translators: %d: number of sub-orders */
                            esc_html__('%d orders', 'wc-flex-pay'),
                            count($sub_orders)
                        );
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <table class="wcfp-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Due Date', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Status', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Transaction ID', 'wc-flex-pay'); ?></th>
                <th><?php esc_html_e('Sub-order', 'wc-flex-pay'); ?></th>
                <?php if (!$is_sub_order) : ?>
                    <th><?php esc_html_e('Actions', 'wc-flex-pay'); ?></th>
                <?php endif; ?>
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
                <tr class="wcfp-payment-row <?php echo esc_attr($status_class); ?>">
                    <td data-label="<?php esc_attr_e('Installment', 'wc-flex-pay'); ?>">
                        <?php 
                        printf(
                            /* translators: %d: installment number */
                            esc_html__('Payment %d', 'wc-flex-pay'),
                            $payment['installment_number']
                        );
                        ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Amount', 'wc-flex-pay'); ?>">
                        <?php echo wc_price($payment['amount']); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Due Date', 'wc-flex-pay'); ?>">
                        <?php 
                        echo date_i18n(
                            get_option('date_format'), 
                            strtotime($payment['due_date'])
                        );
                        if ($is_overdue) {
                            echo ' <span class="wcfp-overdue">' . esc_html__('(Overdue)', 'wc-flex-pay') . '</span>';
                        }
                        ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Status', 'wc-flex-pay'); ?>">
                        <span class="wcfp-status-badge status-<?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html(ucfirst($payment['status'])); ?>
                        </span>
                    </td>
                    <td data-label="<?php esc_attr_e('Transaction ID', 'wc-flex-pay'); ?>">
                        <?php echo $payment['transaction_id'] ? esc_html($payment['transaction_id']) : '—'; ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Sub-order', 'wc-flex-pay'); ?>">
                        <?php 
                        if (!empty($payment['payment_suborder'])) :
                            $sub_order = wc_get_order($payment['payment_suborder']);
                            if ($sub_order) :
                            ?>
                                <a href="<?php echo esc_url(get_edit_post_link($payment['payment_suborder'])); ?>" 
                                   class="button button-small">
                                    <?php 
                                    printf(
                                        /* translators: %s: sub-order number */
                                        esc_html__('#%s', 'wc-flex-pay'),
                                        esc_html($sub_order->get_order_number())
                                    );
                                    ?>
                                </a>
                            <?php 
                            endif;
                        else :
                            echo '—';
                        endif;
                        ?>
                    </td>
                    <?php if (!$is_sub_order) : ?>
                        <td data-label="<?php esc_attr_e('Actions', 'wc-flex-pay'); ?>">
                            <?php if ($payment['status'] === 'pending') : ?>
                                <button type="button" 
                                        class="button process-payment" 
                                        data-payment-id="<?php echo esc_attr($payment['id']); ?>"
                                        data-nonce="<?php echo wp_create_nonce('wcfp-admin'); ?>">
                                    <?php esc_html_e('Process Payment', 'wc-flex-pay'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
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
