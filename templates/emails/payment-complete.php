<?php
/**
 * Payment Complete Email Template (HTML)
 *
 * @package WC_Flex_Pay\Templates\Emails
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include common styles
include_once WCFP_PLUGIN_DIR . 'templates/emails/styles/common.php';

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

// Get sub-order if available
$sub_order = null;
$sub_order_id = $payment_data['sub_order_id'] ?? null;
if ($sub_order_id) {
    $sub_order = wc_get_order($sub_order_id);
}

// Initialize payment data
if (!isset($payment_data) || !is_array($payment_data)) {
    $payment_data = array();
}

// Prepare payment data
$payment_data = array_merge(array(
    'total_amount' => 0,
    'paid_amount' => 0,
    'pending_amount' => 0,
    'current_installment' => null,
    'sub_order_id' => $sub_order_id,
    'payment_method' => $sub_order ? $sub_order->get_payment_method_title() : $order->get_payment_method_title(),
    'expiry_date' => $link_data['expires_at'] ?? null,
    'transaction_id' => $sub_order ? $sub_order->get_transaction_id() : null
), $payment_data);

// Get current payment details from sub-order if available
if ($sub_order) {
    $payment_data['current_payment'] = array(
        'amount' => $sub_order->get_total(),
        'transaction_id' => $sub_order->get_transaction_id(),
        'payment_method' => $sub_order->get_payment_method_title(),
        'date' => $sub_order->get_date_paid() ? $sub_order->get_date_paid()->date_i18n(get_option('date_format')) : null
    );
}

// Get overall progress from parent order
foreach ($order->get_items() as $item) {
    if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
        $payment_status = $item->get_meta('_wcfp_payment_status');
        if (!empty($payment_status)) {
            foreach ($payment_status as $status) {
                $amount = $status['amount'] * $item->get_quantity();
                $payment_data['total_amount'] += $amount;
                if ($status['status'] === 'completed') {
                    $payment_data['paid_amount'] += $amount;
                } else {
                    $payment_data['pending_amount'] += $amount;
                }
            }
            if (!empty($payment_status[$installment_number - 1])) {
                $payment_data['current_installment'] = array_merge(
                    $payment_status[$installment_number - 1],
                    array('number' => $installment_number)
                );
            }
        }
        break;
    }
}
?>

<div class="wcfp-success-notice" style="margin-bottom: 20px; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
    <?php if ($sub_order) : ?>
        <?php
        printf(
            /* translators: %1$s: customer first name, %2$s: installment number, %3$s: sub-order number, %4$s: parent order number */
            esc_html__('Hi %1$s, your payment for installment #%2$d (Order #%3$s) has been received. This is part of your main order #%4$s.', 'wc-flex-pay'),
            esc_html($order->get_billing_first_name()),
            $installment_number,
            esc_html($sub_order->get_order_number()),
            esc_html($order->get_order_number())
        );
        ?>
    <?php else : ?>
        <?php
        printf(
            /* translators: %1$s: customer first name, %2$s: order number */
            esc_html__('Hi %1$s, your payment for order #%2$s has been received.', 'wc-flex-pay'),
            esc_html($order->get_billing_first_name()),
            esc_html($order->get_order_number())
        );
        ?>
    <?php endif; ?>
</div>

<?php if ($sub_order) : ?>
    <div class="wcfp-summary-box" style="margin-bottom: 30px;">
        <h3 class="wcfp-heading"><?php esc_html_e('Current Payment', 'wc-flex-pay'); ?></h3>
        <table class="wcfp-summary-table">
            <tr>
                <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                <td class="amount">
                    <?php 
                    printf(
                        /* translators: %d: installment number */
                        esc_html__('#%d', 'wc-flex-pay'),
                        $installment_number
                    ); 
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                <td class="amount"><?php echo wc_price($sub_order->get_total()); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Transaction ID', 'wc-flex-pay'); ?></th>
                <td class="amount"><?php echo esc_html($sub_order->get_transaction_id()); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Payment Method', 'wc-flex-pay'); ?></th>
                <td class="amount"><?php echo esc_html($sub_order->get_payment_method_title()); ?></td>
            </tr>
            <?php if ($sub_order->get_date_paid()) : ?>
            <tr>
                <th><?php esc_html_e('Payment Date', 'wc-flex-pay'); ?></th>
                <td class="amount">
                    <?php echo esc_html($sub_order->get_date_paid()->date_i18n(get_option('date_format'))); ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
<?php endif; ?>

<div class="wcfp-summary-box" style="margin-bottom: 30px;">
    <h3 class="wcfp-heading"><?php esc_html_e('Payment Summary', 'wc-flex-pay'); ?></h3>
    <?php include WCFP_PLUGIN_DIR . 'templates/emails/partials/payment-summary.php'; ?>
</div>

<div class="wcfp-summary-box" style="margin-bottom: 30px;">
    <h3 class="wcfp-heading"><?php esc_html_e('Order Details', 'wc-flex-pay'); ?></h3>
    <?php include WCFP_PLUGIN_DIR . 'templates/emails/partials/order-details.php'; ?>
</div>

<?php if ($payment_data['pending_amount'] > 0) : ?>
    <div class="wcfp-divider"></div>
    <div class="wcfp-installment-details">
        <?php
        esc_html_e('You still have pending payments for this order. You\'ll receive a reminder email before the next payment is due.', 'wc-flex-pay');
        ?>
    </div>
<?php endif; ?>

<?php if ($additional_content) : ?>
    <div class="wcfp-divider"></div>
    <div class="wcfp-installment-details">
        <?php echo wp_kses_post(wpautop(wptexturize($additional_content))); ?>
    </div>
<?php endif;

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
