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

</div>
