<?php
/**
 * Payment Reminder Email Template (Plain Text)
 *
 * @package WC_Flex_Pay\Templates\Emails\Plain
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading));
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Prepare payment data
$payment_data = array(
    'total_amount' => 0,
    'paid_amount' => 0,
    'pending_amount' => 0,
    'current_installment' => null,
    'sub_order_id' => $link_data['sub_order_id'] ?? null,
    'payment_method' => $order->get_payment_method_title(),
    'expiry_date' => $link_data['expires_at'] ?? null
);

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

// Greeting
printf(
    /* translators: %1$s: customer first name, %2$s: order number */
    esc_html__('Hi %1$s, this is a reminder about your upcoming payment for order #%2$s.', 'wc-flex-pay') . "\n\n",
    esc_html($order->get_billing_first_name()),
    esc_html($order->get_order_number())
);

// Payment Summary
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('Payment Summary', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__('Total Amount:', 'wc-flex-pay') . ' ' . wc_price($payment_data['total_amount']) . "\n";
echo esc_html__('Amount Paid:', 'wc-flex-pay') . ' ' . wc_price($payment_data['paid_amount']) . "\n";
echo esc_html__('Pending Amount:', 'wc-flex-pay') . ' ' . wc_price($payment_data['pending_amount']) . "\n\n";

if (!empty($payment_data['current_installment'])) {
    echo esc_html__('Current Installment Details:', 'wc-flex-pay') . "\n";
    printf(
        esc_html__('Installment #%d:', 'wc-flex-pay') . ' %s' . "\n",
        $payment_data['current_installment']['number'],
        wc_price($payment_data['current_installment']['amount'])
    );
    echo esc_html__('Due Date:', 'wc-flex-pay') . ' ' . 
         date_i18n(get_option('date_format'), strtotime($payment_data['current_installment']['due_date'])) . "\n\n";
}

// Order Details
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('Order Details', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__('Order:', 'wc-flex-pay') . ' #' . $order->get_order_number() . "\n";

foreach ($order->get_items() as $item) {
    if ('yes' === $item->get_meta('_wcfp_enabled')) {
        echo esc_html__('Product:', 'wc-flex-pay') . ' ' . $item->get_name();
        if ($item->get_variation_id()) {
            echo ' - ' . wc_get_formatted_variation($item->get_product(), true);
        }
        echo "\n";
        break;
    }
}

if (!empty($payment_data['sub_order_id'])) {
    echo esc_html__('Sub Order:', 'wc-flex-pay') . ' #' . $payment_data['sub_order_id'] . "\n";
}

if (!empty($payment_data['payment_method'])) {
    echo esc_html__('Payment Method:', 'wc-flex-pay') . ' ' . $payment_data['payment_method'] . "\n";
}

echo "\n";

// Due Date Notice
if (!empty($payment_data['current_installment']['due_date'])) {
    printf(
        /* translators: %s: due date */
        esc_html__('Please note that this payment is due on %s.', 'wc-flex-pay') . "\n\n",
        date_i18n(get_option('date_format'), strtotime($payment_data['current_installment']['due_date']))
    );
}

// Payment Link
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('Payment Link', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__('Click or copy this link to pay:', 'wc-flex-pay') . "\n";
echo esc_url($link_data['url']) . "\n\n";

if (!empty($payment_data['expiry_date'])) {
    printf(
        /* translators: %s: expiry date */
        esc_html__('This payment link will expire on %s.', 'wc-flex-pay') . "\n\n",
        date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime($payment_data['expiry_date'])
        )
    );
}

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ($additional_content) {
    echo "\n----------------------------------------\n\n";
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
}

echo "\n\n----------------------------------------\n\n";

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
