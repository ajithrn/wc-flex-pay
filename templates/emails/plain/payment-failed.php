<?php
/**
 * Payment Failed email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/payment-failed.php.
 *
 * @package WC_Flex_Pay\Templates\Emails
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!isset($order) || !isset($payment) || !isset($email)) {
    return;
}

$payment_manager = new \WCFP\Payment();
$remaining_payments = $payment_manager->get_order_payments($order->get_id());
$remaining_payments = array_filter($remaining_payments, function($p) {
    return $p['status'] === 'pending';
});

$total_remaining = array_sum(array_column($remaining_payments, 'amount'));
$grace_period = absint(get_option('wcfp_overdue_grace_period', 3));
$retry_url = add_query_arg(array(
    'retry_payment' => $payment['id'],
    'order_key' => $order->get_order_key(),
), $order->get_checkout_payment_url());

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
echo sprintf(esc_html__('Hi %s,', 'wc-flex-pay'), esc_html($order->get_billing_first_name())) . "\n\n";

echo esc_html__('Unfortunately, your scheduled payment for your order has failed to process.', 'wc-flex-pay') . "\n\n";

/* translators: %s: Order number */
echo sprintf(esc_html__('Payment Details (Order #%s)', 'wc-flex-pay'), esc_html($order->get_order_number())) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html__('Payment Amount:', 'wc-flex-pay') . ' ' . wp_strip_all_tags(wc_price($payment['amount'])) . "\n";
echo esc_html__('Due Date:', 'wc-flex-pay') . ' ' . date_i18n(get_option('date_format'), strtotime($payment['due_date'])) . "\n";
echo esc_html__('Payment Method:', 'wc-flex-pay') . ' ' . wp_strip_all_tags($order->get_payment_method_title()) . "\n";

if (!empty($payment['error_message'])) {
    echo esc_html__('Error Details:', 'wc-flex-pay') . ' ' . esc_html($payment['error_message']) . "\n";
}

echo "\n";

if (!empty($remaining_payments)) {
    echo esc_html__('Payment Schedule Overview', 'wc-flex-pay') . "\n";
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

    /* translators: %1$s: Remaining amount, %2$d: Number of remaining payments */
    echo sprintf(
        esc_html__('You have %1$s in remaining payments (%2$d installment(s)).', 'wc-flex-pay'),
        wp_strip_all_tags(wc_price($total_remaining)),
        count($remaining_payments)
    ) . "\n\n";

    echo esc_html__('Please ensure your payment method is up to date to avoid any interruption in your payment schedule.', 'wc-flex-pay') . "\n\n";
}

echo esc_html__('What to do next', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__('To ensure your order remains active, please take one of the following actions:', 'wc-flex-pay') . "\n\n";

echo "1. " . esc_html__('Retry Payment:', 'wc-flex-pay') . "\n";
echo "   " . esc_url($retry_url) . "\n\n";

echo "2. " . esc_html__('Update Payment Method:', 'wc-flex-pay') . "\n";
echo "   " . esc_url($order->get_view_order_url()) . "\n\n";

echo "3. " . esc_html__('Contact Support:', 'wc-flex-pay') . "\n";
echo "   " . esc_html(get_option('woocommerce_email_from_address')) . "\n\n";

/* translators: %d: Grace period in days */
echo sprintf(
    esc_html__('Important: If the payment is not processed within %d days, your order may be suspended.', 'wc-flex-pay'),
    $grace_period
) . "\n\n";

echo esc_html__('We appreciate your prompt attention to this matter.', 'wc-flex-pay') . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
