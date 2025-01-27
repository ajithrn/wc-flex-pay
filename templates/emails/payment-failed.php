<?php
/**
 * Payment Failed Email Template (HTML)
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
?>

<div class="wcfp-failed-notice" style="margin-bottom: 20px; padding: 15px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
    <?php
    printf(
        /* translators: %1$s: customer first name, %2$s: order number */
        esc_html__('Hi %1$s, unfortunately your payment for order #%2$s has failed.', 'wc-flex-pay'),
        esc_html($order->get_billing_first_name()),
        esc_html($order->get_order_number())
    );
    ?>
</div>

<?php
// Include payment summary
include WCFP_PLUGIN_DIR . 'templates/emails/partials/payment-summary.php';

// Include order details
include WCFP_PLUGIN_DIR . 'templates/emails/partials/order-details.php';

// Add retry payment notice
if (!empty($link_data['url'])) : ?>
    <div class="wcfp-retry-notice" style="margin-top: 20px; padding: 15px; background-color: #fff3cd; border: 1px solid #ffeeba; border-radius: 4px; color: #856404;">
        <?php esc_html_e('Please use the button below to try the payment again:', 'wc-flex-pay'); ?>
    </div>

    <?php
    // Prepare action buttons
    $actions = array(
        'retry' => array(
            'url' => $link_data['url'],
            'text' => __('Retry Payment', 'wc-flex-pay')
        )
    );
    $primary_action = 'retry';

    // Include action buttons
    include WCFP_PLUGIN_DIR . 'templates/emails/partials/action-buttons.php';
endif;

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ($additional_content) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
