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

printf(
    /* translators: %1$d: installment number, %2$s: payment amount, %3$s: due date */
    esc_html__('This link is for installment %1$d, with an amount of %2$s, due on %3$s.', 'wc-flex-pay') . "\n\n",
    esc_html($installment_number),
    wc_price($payments['installments'][$installment_number - 1]['amount']),
    date_i18n(get_option('date_format'), strtotime($payments['installments'][$installment_number - 1]['due_date']))
);

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
