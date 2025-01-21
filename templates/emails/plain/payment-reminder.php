<?php
/**
 * Payment Reminder email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/payment-reminder.php.
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
$days_until = floor((strtotime($payment['due_date']) - current_time('timestamp')) / (60 * 60 * 24));
$completed_payments = $payment_manager->get_order_payments($order->get_id());
$completed_payments = array_filter($completed_payments, function($p) {
    return $p['status'] === 'completed';
});

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
echo sprintf(esc_html__('Hi %s,', 'wc-flex-pay'), esc_html($order->get_billing_first_name())) . "\n\n";

/* translators: %1$s: Order number, %2$d: Days until payment */
echo sprintf(
    esc_html__('This is a friendly reminder that your next scheduled payment for order #%1$s is due in %2$d days.', 'wc-flex-pay'),
    esc_html($order->get_order_number()),
    $days_until
) . "\n\n";

echo esc_html__('Upcoming Payment Details', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html__('Payment Amount:', 'wc-flex-pay') . ' ' . wp_strip_all_tags(wc_price($payment['amount'])) . "\n";
echo esc_html__('Due Date:', 'wc-flex-pay') . ' ' . date_i18n(get_option('date_format'), strtotime($payment['due_date'])) . "\n";
echo esc_html__('Payment Method:', 'wc-flex-pay') . ' ' . wp_strip_all_tags($order->get_payment_method_title()) . "\n\n";

echo esc_html__('Payment Schedule Overview', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html__('Completed Payments:', 'wc-flex-pay') . ' ' . count($completed_payments) . "\n";
echo esc_html__('Remaining Payments:', 'wc-flex-pay') . ' ' . count($remaining_payments) . "\n";
echo esc_html__('Remaining Balance:', 'wc-flex-pay') . ' ' . wp_strip_all_tags(wc_price($total_remaining)) . "\n\n";

echo esc_html__('Important Information', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo "* " . esc_html__('The payment will be automatically processed using your saved payment method on the due date.', 'wc-flex-pay') . "\n\n";
echo "* " . esc_html__('Please ensure your payment method is up to date and has sufficient funds available.', 'wc-flex-pay') . "\n\n";

/* translators: %s: Order URL */
echo sprintf(
    esc_html__('You can review and manage your payment details in your account dashboard: %s', 'wc-flex-pay'),
    esc_url($order->get_view_order_url())
) . "\n\n";

echo esc_html__('Thank you for your business!', 'wc-flex-pay') . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
