<?php
/**
 * Order Details Email Template (Plain Text)
 *
 * @package WC_Flex_Pay\Templates\Emails
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading));
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Ensure required variables are available
if (!isset($payment_data) || !is_array($payment_data)) {
    $payment_data = array();
}

// Initialize payment data with defaults
$payment_data = array_merge(array(
    'total_amount' => 0,
    'paid_amount' => 0,
    'pending_amount' => 0,
    'current_installment' => null,
    'sub_order_id' => null,
    'payment_method' => $order->get_payment_method_title(),
    'expires_at' => null,
), $payment_data);

// Get payment status for all installments
$installments = array(
    'upcoming' => array(),
    'completed' => array()
);

foreach ($order->get_items() as $item) {
    if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
        $payment_status = $item->get_meta('_wcfp_payment_status');
        if (!empty($payment_status)) {
            foreach ($payment_status as $payment_id => $payment) {
                $amount = $payment['amount'] * $item->get_quantity();
                $payment_data['total_amount'] += $amount;
                
                if ($payment['status'] === 'completed') {
                    $payment_data['paid_amount'] += $amount;
                    $installments['completed'][] = array_merge($payment, array('number' => $payment_id + 1));
                } else {
                    $payment_data['pending_amount'] += $amount;
                    $installments['upcoming'][] = array_merge($payment, array('number' => $payment_id + 1));
                }
            }
        }
        break;
    }
}

// Sort upcoming installments by due date
usort($installments['upcoming'], function($a, $b) {
    return strtotime($a['due_date']) - strtotime($b['due_date']);
});

// Sort completed installments by payment date
usort($installments['completed'], function($a, $b) {
    return strtotime($b['payment_date'] ?? '0') - strtotime($a['payment_date'] ?? '0');
});

// Greeting
printf(
    /* translators: %1$s: customer first name, %2$s: order number */
    esc_html__('Hi %1$s, here are your payment details for order #%2$s.', 'wc-flex-pay'),
    esc_html($order->get_billing_first_name()),
    esc_html($order->get_order_number())
);
echo "\n\n";

// Payment Summary
echo "=================\n";
esc_html_e('Payment Summary', 'wc-flex-pay');
echo "\n=================\n\n";

printf(
    /* translators: %s: formatted amount */
    esc_html__('Total Amount: %s', 'wc-flex-pay') . "\n",
    wc_price($payment_data['total_amount'])
);
printf(
    /* translators: %s: formatted amount */
    esc_html__('Amount Paid: %s', 'wc-flex-pay') . "\n",
    wc_price($payment_data['paid_amount'])
);
printf(
    /* translators: %s: formatted amount */
    esc_html__('Pending Amount: %s', 'wc-flex-pay') . "\n\n",
    wc_price($payment_data['pending_amount'])
);

// Upcoming Payments
if (!empty($installments['upcoming'])) {
    echo "=================\n";
    esc_html_e('Upcoming Payments', 'wc-flex-pay');
    echo "\n=================\n\n";

    foreach ($installments['upcoming'] as $installment) {
        printf(
            /* translators: 1: installment number, 2: formatted amount, 3: formatted date, 4: status */
            esc_html__('Installment #%1$d: %2$s (Due: %3$s) - %4$s', 'wc-flex-pay') . "\n",
            $installment['number'],
            wc_price($installment['amount']),
            date_i18n(get_option('date_format'), strtotime($installment['due_date'])),
            ucfirst($installment['status'])
        );
    }
    echo "\n";
}

// Completed Payments
if (!empty($installments['completed'])) {
    echo "=================\n";
    esc_html_e('Completed Payments', 'wc-flex-pay');
    echo "\n=================\n\n";

    foreach ($installments['completed'] as $installment) {
        printf(
            /* translators: 1: installment number, 2: formatted amount, 3: formatted date, 4: transaction ID */
            esc_html__('Installment #%1$d: %2$s (Paid: %3$s) - Transaction ID: %4$s', 'wc-flex-pay') . "\n",
            $installment['number'],
            wc_price($installment['amount']),
            date_i18n(get_option('date_format'), strtotime($installment['payment_date'])),
            $installment['transaction_id'] ?? '-'
        );
    }
    echo "\n";
}

// Order Details
echo "=================\n";
esc_html_e('Order Details', 'wc-flex-pay');
echo "\n=================\n\n";

/* translators: %s: Order ID */
echo esc_html__('Order number:', 'wc-flex-pay') . ' ' . $order->get_order_number() . "\n";
/* translators: %s: Order date */
echo esc_html__('Order date:', 'wc-flex-pay') . ' ' . wc_format_datetime($order->get_date_created()) . "\n";
/* translators: %s: Order status */
echo esc_html__('Order status:', 'wc-flex-pay') . ' ' . wc_get_order_status_name($order->get_status()) . "\n\n";

// Next Payment Notice
if ($payment_data['pending_amount'] > 0 && !empty($installments['upcoming'])) {
    echo "=================\n";
    esc_html_e('Next Payment', 'wc-flex-pay');
    echo "\n=================\n\n";

}

// Additional content
if ($additional_content) {
    echo "----------\n\n";
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
    echo "\n----------\n\n";
}

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
