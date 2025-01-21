<?php
/**
 * Payment Complete email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/payment-complete.php.
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

$next_payment = reset($remaining_payments);
$total_remaining = array_sum(array_column($remaining_payments, 'amount'));

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading)) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: Customer first name */
echo sprintf(esc_html__('Hi %s,', 'wc-flex-pay'), esc_html($order->get_billing_first_name())) . "\n\n";

echo esc_html__('Great news! Your scheduled payment for your order has been successfully processed.', 'wc-flex-pay') . "\n\n";

/* translators: %s: Order number */
echo sprintf(esc_html__('Payment Details (Order #%s)', 'wc-flex-pay'), esc_html($order->get_order_number())) . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html__('Payment Amount:', 'wc-flex-pay') . ' ' . wp_strip_all_tags(wc_price($payment['amount'])) . "\n";
echo esc_html__('Payment Date:', 'wc-flex-pay') . ' ' . date_i18n(get_option('date_format'), current_time('timestamp')) . "\n";
echo esc_html__('Payment Method:', 'wc-flex-pay') . ' ' . wp_strip_all_tags($order->get_payment_method_title()) . "\n";

if (!empty($payment['transaction_id'])) {
    echo esc_html__('Transaction ID:', 'wc-flex-pay') . ' ' . esc_html($payment['transaction_id']) . "\n";
}

echo "\n";

if (!empty($remaining_payments)) {
    echo esc_html__('Upcoming Payments', 'wc-flex-pay') . "\n";
    echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

    /* translators: %1$s: Remaining amount, %2$d: Number of remaining payments */
    echo sprintf(
        esc_html__('You have %1$s in remaining payments (%2$d installment(s)).', 'wc-flex-pay'),
        wp_strip_all_tags(wc_price($total_remaining)),
        count($remaining_payments)
    ) . "\n\n";

    if ($next_payment) {
        /* translators: %1$s: Next payment amount, %2$s: Next payment date */
        echo sprintf(
            esc_html__('Your next payment of %1$s is scheduled for %2$s.', 'wc-flex-pay'),
            wp_strip_all_tags(wc_price($next_payment['amount'])),
            date_i18n(get_option('date_format'), strtotime($next_payment['due_date']))
        ) . "\n\n";
    }

    echo esc_html__('You can view your complete payment schedule and manage your payments in your account dashboard:', 'wc-flex-pay') . "\n";
    echo esc_url($order->get_view_order_url()) . "\n\n";

    echo esc_html__('Please ensure your payment method is up to date to avoid any interruption in service.', 'wc-flex-pay') . "\n\n";
} else {
    echo esc_html__('This was your final payment. Thank you for completing all payments for this order!', 'wc-flex-pay') . "\n\n";
}

echo esc_html__('Thank you for your business!', 'wc-flex-pay') . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
