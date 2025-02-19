<?php
/**
 * Payment Reminder Email Template (Plain Text)
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
    'sub_order_id' => $link_data['sub_order_id'] ?? null,
    'payment_method' => $order->get_payment_method_title(),
    'expiry_date' => $link_data['expires_at'] ?? null
), $payment_data);

// Get payment status for upcoming installments only
$upcoming_payments = array();
foreach ($order->get_items() as $item) {
    if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
        $payment_status = $item->get_meta('_wcfp_payment_status');
        if (!empty($payment_status)) {
            foreach ($payment_status as $payment_id => $payment) {
                $amount = $payment['amount'] * $item->get_quantity();
                $payment_data['total_amount'] += $amount;
                
                if ($payment['status'] === 'completed') {
                    $payment_data['paid_amount'] += $amount;
                } else {
                    $payment_data['pending_amount'] += $amount;
                    if (strtotime($payment['due_date']) > current_time('timestamp')) {
                        $upcoming_payments[] = array_merge($payment, array(
                            'number' => $payment_id + 1
                        ));
                    }
                }
            }
            
            // Set current installment
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

// Sort upcoming payments by due date
usort($upcoming_payments, function($a, $b) {
    return strtotime($a['due_date']) - strtotime($b['due_date']);
});

// Greeting
printf(
    /* translators: %1$s: customer first name, %2$s: order number */
    esc_html__('Hi %1$s, this is a reminder about your upcoming payment for order #%2$s.', 'wc-flex-pay'),
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

// Current Payment Due
if (!empty($payment_data['current_installment'])) {
    echo "=================\n";
    esc_html_e('Current Payment Due', 'wc-flex-pay');
    echo "\n=================\n\n";

    printf(
        /* translators: 1: installment number, 2: formatted amount, 3: formatted date */
        esc_html__('Installment #%1$d: %2$s (Due: %3$s)', 'wc-flex-pay') . "\n\n",
        $payment_data['current_installment']['number'],
        wc_price($payment_data['current_installment']['amount']),
        date_i18n(get_option('date_format'), strtotime($payment_data['current_installment']['due_date']))
    );
}

// Upcoming Payments
if (!empty($upcoming_payments)) {
    echo "=================\n";
    esc_html_e('Upcoming Payments', 'wc-flex-pay');
    echo "\n=================\n\n";

    foreach ($upcoming_payments as $payment) {
        printf(
            /* translators: 1: installment number, 2: formatted amount, 3: formatted date, 4: status */
            esc_html__('Installment #%1$d: %2$s (Due: %3$s) - %4$s', 'wc-flex-pay') . "\n",
            $payment['number'],
            wc_price($payment['amount']),
            date_i18n(get_option('date_format'), strtotime($payment['due_date'])),
            ucfirst($payment['status'])
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

// Payment Link
if (!empty($link_data['url'])) {
    echo "=================\n";
    esc_html_e('Payment Link', 'wc-flex-pay');
    echo "\n=================\n\n";

    echo esc_html__('Click here to make your payment:', 'wc-flex-pay') . "\n";
    echo esc_url($link_data['url']) . "\n\n";
}

// Payment Notice
if (!empty($payment_data['current_installment'])) {
    echo "=================\n";
    esc_html_e('Payment Notice', 'wc-flex-pay');
    echo "\n=================\n\n";

    printf(
        /* translators: 1: formatted amount, 2: formatted date */
        esc_html__('Your payment of %1$s is due on %2$s. Please ensure to make the payment before the due date to avoid any late fees.', 'wc-flex-pay') . "\n",
        wc_price($payment_data['current_installment']['amount']),
        date_i18n(get_option('date_format'), strtotime($payment_data['current_installment']['due_date']))
    );

    if (!empty($link_data['expires_at'])) {
        echo "\n";
        printf(
            /* translators: %s: formatted date */
            esc_html__('This payment link will expire on %s.', 'wc-flex-pay') . "\n",
            date_i18n(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($link_data['expires_at'])
            )
        );
    }
}

// Additional content
if ($additional_content) {
    echo "\n----------\n\n";
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
    echo "\n----------\n\n";
}

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
