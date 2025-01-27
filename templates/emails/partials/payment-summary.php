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
    </table>

    <?php 
    // Show current installment details if available and has all required fields
    if (!empty($payment_data['current_installment']) && 
        isset($payment_data['current_installment']['number']) && 
        isset($payment_data['current_installment']['amount']) && 
        isset($payment_data['current_installment']['due_date']) && 
        isset($payment_data['current_installment']['status'])) : 
    ?>
        <div class="wcfp-installment-details">
            <h3><?php esc_html_e('Current Installment Details:', 'wc-flex-pay'); ?></h3>
            <table class="wcfp-summary-table">
                <tr>
                    <th>
                        <?php 
                        printf(
                            /* translators: %d: installment number */
                            esc_html__('Installment #%d:', 'wc-flex-pay'),
                            intval($payment_data['current_installment']['number'])
                        ); 
                        ?>
                    </th>
                    <td class="amount"><?php echo wc_price(floatval($payment_data['current_installment']['amount'])); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Due Date:', 'wc-flex-pay'); ?></th>
                    <td class="amount">
                        <?php 
                        $due_date = strtotime($payment_data['current_installment']['due_date']);
                        echo $due_date ? date_i18n(get_option('date_format'), $due_date) : esc_html__('N/A', 'wc-flex-pay');
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Status:', 'wc-flex-pay'); ?></th>
                    <td class="amount">
                        <span class="wcfp-status <?php echo esc_attr($payment_data['current_installment']['status']); ?>">
                            <?php echo esc_html(ucfirst($payment_data['current_installment']['status'])); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
    <?php endif; ?>
</div>
