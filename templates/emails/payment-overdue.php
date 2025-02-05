<?php
/**
 * Payment Overdue Email Template (HTML)
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

<div class="wcfp-error-notice" style="margin-bottom: 20px; padding: 15px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
    <?php
    printf(
        /* translators: %1$s: customer first name, %2$s: order number */
        esc_html__('Hi %1$s, your payment for order #%2$s is overdue.', 'wc-flex-pay'),
        esc_html($order->get_billing_first_name()),
        esc_html($order->get_order_number())
    );
    ?>
</div>

<div class="wcfp-summary-box" style="margin-bottom: 30px;">
    <h3 class="wcfp-heading"><?php esc_html_e('Payment Summary', 'wc-flex-pay'); ?></h3>
    <?php include WCFP_PLUGIN_DIR . 'templates/emails/partials/payment-summary.php'; ?>
</div>

<div class="wcfp-summary-box" style="margin-bottom: 30px;">
    <h3 class="wcfp-heading"><?php esc_html_e('Order Details', 'wc-flex-pay'); ?></h3>
    <?php include WCFP_PLUGIN_DIR . 'templates/emails/partials/order-details.php'; ?>
</div>

<?php
// Prepare action buttons
$actions = array(
    'pay' => array(
        'url' => $link_data['url'],
        'text' => __('Pay Now', 'wc-flex-pay')
    )
);
$primary_action = 'pay';

// Include action buttons
include WCFP_PLUGIN_DIR . 'templates/emails/partials/action-buttons.php';

// Add extended expiry notice
if (!empty($payment_data['expiry_date'])) : ?>
    <div class="wcfp-installment-details">
        <h3 class="wcfp-heading"><?php esc_html_e('Payment Link Expiry', 'wc-flex-pay'); ?></h3>
        <?php
        printf(
            /* translators: %s: expiry date */
            esc_html__('We\'ve extended your payment link validity until %s. Please complete your payment before this date.', 'wc-flex-pay'),
            date_i18n(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($payment_data['expiry_date'])
            )
        );
        ?>
    </div>
<?php endif; ?>

<?php
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
