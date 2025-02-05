<?php
/**
 * Payment summary partial template
 *
 * @package WC_Flex_Pay\Templates\Emails
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Variables available in this template:
 * 
 * @var WC_Order $order Order object
 * @var array $payment_data Payment data array
 */

// Ensure required variables are available
if (!isset($payment_data) || !is_array($payment_data)) {
    return;
}

// Set default values if not set
$payment_data = array_merge(
    array(
        'total_amount' => 0,
        'paid_amount' => 0,
        'pending_amount' => 0,
        'current_installment' => null
    ),
    $payment_data
);
?>

<div class="wcfp-summary-box">
    <?php if (!empty($payment_data['current_payment'])) : ?>
        <h4 class="wcfp-subheading"><?php esc_html_e('Current Payment', 'wc-flex-pay'); ?></h4>
        <table class="wcfp-summary-table">
            <?php if (!empty($payment_data['current_installment'])) : ?>
            <tr>
                <th><?php esc_html_e('Installment:', 'wc-flex-pay'); ?></th>
                <td class="amount">
                    <?php 
                    printf(
                        /* translators: %d: installment number */
                        esc_html__('#%d', 'wc-flex-pay'),
                        $payment_data['current_installment']['number']
                    ); 
                    ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><?php esc_html_e('Amount:', 'wc-flex-pay'); ?></th>
                <td class="amount"><?php echo wc_price(floatval($payment_data['current_payment']['amount'])); ?></td>
            </tr>
            <?php if (!empty($payment_data['sub_order_id'])) : ?>
            <tr>
                <th><?php esc_html_e('Sub Order:', 'wc-flex-pay'); ?></th>
                <td class="amount">
                    <?php 
                    $sub_order = wc_get_order($payment_data['sub_order_id']);
                    echo esc_html('#' . $sub_order->get_order_number());
                    ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($payment_data['current_payment']['transaction_id'])) : ?>
            <tr>
                <th><?php esc_html_e('Transaction ID:', 'wc-flex-pay'); ?></th>
                <td class="amount"><?php echo esc_html($payment_data['current_payment']['transaction_id']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($payment_data['current_payment']['payment_method'])) : ?>
            <tr>
                <th><?php esc_html_e('Payment Method:', 'wc-flex-pay'); ?></th>
                <td class="amount"><?php echo esc_html($payment_data['current_payment']['payment_method']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($payment_data['current_payment']['date'])) : ?>
            <tr>
                <th><?php esc_html_e('Payment Date:', 'wc-flex-pay'); ?></th>
                <td class="amount"><?php echo esc_html($payment_data['current_payment']['date']); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    <?php endif; ?>

    <h4 class="wcfp-subheading"><?php esc_html_e('Overall Progress', 'wc-flex-pay'); ?></h4>
    <table class="wcfp-summary-table">
        <?php
        // Get product name from order items
        $product_name = '';
        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $product_name = $item->get_name();
                break;
            }
        }
        if ($product_name) : ?>
        <tr>
            <th><?php esc_html_e('Product:', 'wc-flex-pay'); ?></th>
            <td class="amount"><?php echo esc_html($product_name); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th><?php esc_html_e('Total Amount:', 'wc-flex-pay'); ?></th>
            <td class="amount"><?php echo wc_price(floatval($payment_data['total_amount'])); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Amount Paid:', 'wc-flex-pay'); ?></th>
            <td class="amount"><?php echo wc_price(floatval($payment_data['paid_amount'])); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Pending Amount:', 'wc-flex-pay'); ?></th>
            <td class="amount"><?php echo wc_price(floatval($payment_data['pending_amount'])); ?></td>
        </tr>
        <?php
        // Calculate installment progress
        $total_installments = 0;
        $completed_installments = 0;
        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $payment_status = $item->get_meta('_wcfp_payment_status');
                if (!empty($payment_status)) {
                    $total_installments = count($payment_status);
                    foreach ($payment_status as $status) {
                        if ($status['status'] === 'completed') {
                            $completed_installments++;
                        }
                    }
                    break;
                }
            }
        }
    ?>
    </table>

    <?php if (!empty($payment_data['completed_payments'])) : ?>
        <h4 class="wcfp-subheading"><?php esc_html_e('Payment History', 'wc-flex-pay'); ?></h4>
        <table class="wcfp-summary-table">
            <?php foreach ($payment_data['completed_payments'] as $payment) : ?>
                <tr>
                    <td colspan="2" style="padding-top: 10px; border-top: 1px solid #dee2e6;">
                        <strong>
                            <?php 
                            printf(
                                /* translators: %d: installment number */
                                esc_html__('Installment #%d', 'wc-flex-pay'),
                                $payment['installment_number']
                            ); 
                            ?>
                        </strong>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Amount:', 'wc-flex-pay'); ?></th>
                    <td class="amount"><?php echo wc_price(floatval($payment['amount'])); ?></td>
                </tr>
                <?php if (!empty($payment['transaction_id'])) : ?>
                <tr>
                    <th><?php esc_html_e('Transaction ID:', 'wc-flex-pay'); ?></th>
                    <td class="amount"><?php echo esc_html($payment['transaction_id']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($payment['payment_date'])) : ?>
                <tr>
                    <th><?php esc_html_e('Payment Date:', 'wc-flex-pay'); ?></th>
                    <td class="amount"><?php echo esc_html($payment['payment_date']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($payment['sub_order_id'])) : ?>
                <tr>
                    <th><?php esc_html_e('Sub Order:', 'wc-flex-pay'); ?></th>
                    <td class="amount">
                        <?php 
                        $sub_order = wc_get_order($payment['sub_order_id']);
                        if ($sub_order) {
                            echo esc_html('#' . $sub_order->get_order_number());
                        }
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
