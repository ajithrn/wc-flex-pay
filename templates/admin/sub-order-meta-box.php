<?php
/**
 * Sub-order meta box template
 *
 * @package WC_Flex_Pay\Admin\Templates
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if this is a sub-order
$parent_order_id = $order->get_meta('_wcfp_parent_order');
$installment_number = $order->get_meta('_wcfp_installment_number');

if (!$parent_order_id || !$installment_number) {
    return;
}

$parent_order = wc_get_order($parent_order_id);
if (!$parent_order) {
    return;
}

$payment_manager = new \WCFP\Payment();
$parent_payments = $payment_manager->get_order_payments($parent_order_id);
$current_installment = null;

foreach ($parent_payments['installments'] as $installment) {
    if ($installment['number'] === $installment_number) {
        $current_installment = $installment;
        break;
    }
}

if (!$current_installment) {
    return;
}
?>

<div class="wcfp-sub-order-details">
    <p>
        <strong><?php esc_html_e('Parent Order:', 'wc-flex-pay'); ?></strong>
        <a href="<?php echo esc_url(get_edit_post_link($parent_order_id)); ?>">
            #<?php echo esc_html($parent_order->get_order_number()); ?>
        </a>
    </p>

    <p>
        <strong><?php esc_html_e('Installment:', 'wc-flex-pay'); ?></strong>
        <?php 
        printf(
            /* translators: %d: installment number */
            esc_html__('#%d', 'wc-flex-pay'),
            $installment_number
        );
        ?>
    </p>

    <p>
        <strong><?php esc_html_e('Due Date:', 'wc-flex-pay'); ?></strong>
        <?php 
        echo esc_html(date_i18n(
            get_option('date_format'), 
            strtotime($current_installment['due_date'])
        ));

        if (strtotime($current_installment['due_date']) < current_time('timestamp') && $order->get_status() === 'pending') {
            echo ' <span class="wcfp-overdue">' . esc_html__('(Overdue)', 'wc-flex-pay') . '</span>';
        }
        ?>
    </p>

    <p>
        <strong><?php esc_html_e('Amount:', 'wc-flex-pay'); ?></strong>
        <?php echo wc_price($current_installment['amount']); ?>
    </p>

    <?php if (!empty($current_installment['payment_date'])) : ?>
        <p>
            <strong><?php esc_html_e('Payment Date:', 'wc-flex-pay'); ?></strong>
            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($current_installment['payment_date']))); ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($current_installment['transaction_id'])) : ?>
        <p>
            <strong><?php esc_html_e('Transaction ID:', 'wc-flex-pay'); ?></strong>
            <?php echo esc_html($current_installment['transaction_id']); ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($current_installment['payment_method'])) : ?>
        <p>
            <strong><?php esc_html_e('Payment Method:', 'wc-flex-pay'); ?></strong>
            <?php echo esc_html($current_installment['payment_method']); ?>
        </p>
    <?php endif; ?>
</div>

<div class="wcfp-sub-order-actions">
    <a href="<?php echo esc_url(get_edit_post_link($parent_order_id)); ?>" 
       class="button">
        <?php esc_html_e('View Parent Order', 'wc-flex-pay'); ?>
    </a>
</div>
