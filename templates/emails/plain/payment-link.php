<?php
/**
 * Payment Link Email Template (Plain Text)
 *
 * @package WC_Flex_Pay\Templates\Emails\Plain
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading));
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

printf(
    /* translators: %1$s: customer first name, %2$s: order number */
    esc_html__('Hi %1$s, here\'s your payment link for order #%2$s.', 'wc-flex-pay') . "\n\n",
    esc_html($order->get_billing_first_name()),
    esc_html($order->get_order_number())
);

// Get payment data
$total_amount = 0;
$paid_amount = 0;
$pending_amount = 0;
$installment = null;

foreach ($order->get_items() as $item) {
    if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
        $payment_status = $item->get_meta('_wcfp_payment_status');
        if (!empty($payment_status)) {
            foreach ($payment_status as $status) {
                $amount = $status['amount'] * $item->get_quantity();
                $total_amount += $amount;
                if ($status['status'] === 'completed') {
                    $paid_amount += $amount;
                } else {
                    $pending_amount += $amount;
                }
            }
            if (!empty($payment_status[$installment_number - 1])) {
                $installment = $payment_status[$installment_number - 1];
            }
        }
        break;
    }
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html__('Payment Summary', 'wc-flex-pay') . "\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo esc_html__('Total Amount:', 'wc-flex-pay') . ' ' . wc_price($total_amount) . "\n";
echo esc_html__('Amount Paid:', 'wc-flex-pay') . ' ' . wc_price($paid_amount) . "\n";
echo esc_html__('Pending Amount:', 'wc-flex-pay') . ' ' . wc_price($pending_amount) . "\n\n";

if ($installment) {
    echo esc_html__('Current Installment Details:', 'wc-flex-pay') . "\n";
    printf(
        esc_html__('Installment #%d:', 'wc-flex-pay') . ' %s' . "\n",
        $installment_number,
        wc_price($installment['amount'])
    );
    echo esc_html__('Due Date:', 'wc-flex-pay') . ' ' . 
         date_i18n(get_option('date_format'), strtotime($installment['due_date'])) . "\n\n";
}

if (!empty($link_data['sub_order_id'])) {
    $sub_order = wc_get_order($link_data['sub_order_id']);
    if ($sub_order) {
        printf(
            esc_html__('Sub-order: #%s', 'wc-flex-pay') . "\n\n",
            esc_html($sub_order->get_order_number())
        );
    }
}

printf(
    /* translators: %s: expiry date */
    esc_html__('Please note that this payment link will expire on %s.', 'wc-flex-pay') . "\n\n",
    date_i18n(
        get_option('date_format') . ' ' . get_option('time_format'),
        strtotime($link_data['expires_at'])
    )
);

echo "\n----------------------------------------\n\n";

echo esc_html__('Click or copy this link to pay:', 'wc-flex-pay') . "\n";
echo esc_html($link_data['url']) . "\n\n";

echo "\n----------------------------------------\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ($additional_content) {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
