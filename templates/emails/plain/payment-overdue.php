<?php
/**
 * Payment Overdue email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/payment-overdue.php.
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
$days_overdue = floor((current_time('timestamp') - strtotime($payment['due_date'])) / (60 * 60 * 24));
$grace_period = absint(get_option('wcfp_overdue_grace_period', 3));
$days_remaining = $grace_period - $days_overdue;
$retry_url = add_query_arg(array(
    'retry_payment' => $payment['id'],
    'order_key' => $order->get_order_key(),
), $order->get_checkout_payment_url());

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
echo sprintf(esc_html__('Hi %s,', 'wc-flex-pay'), esc_html($order->get_billing_first_name())) . "\n\n";

/* translators: %1$s: Order number, %2$d: Days overdue */
echo sprintf(
    esc_html__('This is an urgent notice regarding your payment for order #%1$s which is now %2$d days overdue.', 'wc-flex-pay'),
    esc_html($order->get_order_number()),
    $days_overdue
) . "\n\n";

echo esc_html__('Overdue Payment Details', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html__('Payment Amount:', 'wc-flex-pay') . ' ' . wp_strip_all_tags(wc_price($payment['amount'])) . "\n";
echo esc_html__('Due Date:', 'wc-flex-pay') . ' ' . date_i18n(get_option('date_format'), strtotime($payment['due_date'])) . "\n";
echo esc_html__('Days Overdue:', 'wc-flex-pay') . ' ' . $days_overdue . "\n";
echo esc_html__('Payment Method:', 'wc-flex-pay') . ' ' . wp_strip_all_tags($order->get_payment_method_title()) . "\n";

if (!empty($payment['error_message'])) {
    echo esc_html__('Error Details:', 'wc-flex-pay') . ' ' . esc_html($payment['error_message']) . "\n";
}

echo "\n";

echo esc_html__('Payment Schedule Overview', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html__('Total Remaining Balance:', 'wc-flex-pay') . ' ' . wp_strip_all_tags(wc_price($total_remaining)) . "\n";
echo esc_html__('Remaining Payments:', 'wc-flex-pay') . ' ' . count($remaining_payments) . "\n";
echo esc_html__('Grace Period Remaining:', 'wc-flex-pay') . ' ' . sprintf(
    /* translators: %d: number of days */
    _n('%d day', '%d days', $days_remaining, 'wc-flex-pay'),
    $days_remaining
) . "\n\n";

echo esc_html__('Required Action', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %d: number of days */
echo sprintf(
    esc_html__('To avoid order suspension, please process this overdue payment within the next %d days.', 'wc-flex-pay'),
    $days_remaining
) . "\n\n";

echo esc_html__('Process Payment Now:', 'wc-flex-pay') . "\n";
echo esc_url($retry_url) . "\n\n";

echo esc_html__('How to Resolve This', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo "1. " . esc_html__('Visit your account dashboard to review your payment details:', 'wc-flex-pay') . "\n";
echo "   " . esc_url($order->get_view_order_url()) . "\n\n";

echo "2. " . esc_html__('Ensure your payment method is up to date and has sufficient funds', 'wc-flex-pay') . "\n\n";

echo "3. " . esc_html__('Click the payment link above to complete the payment', 'wc-flex-pay') . "\n\n";

echo "4. " . esc_html__('If you need assistance, please contact our support team:', 'wc-flex-pay') . "\n";
echo "   " . esc_html(get_option('woocommerce_email_from_address')) . "\n\n";

echo esc_html__('Thank you for your immediate attention to this matter.', 'wc-flex-pay') . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
