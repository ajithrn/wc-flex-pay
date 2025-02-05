<?php
/**
 * Payment Link Email Template (HTML)
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
    'payment_method' => '',
    'expires_at' => null,
    'url' => '',
), $payment_data);

// Ensure link data is available
if (!isset($link_data) || !is_array($link_data)) {
    $link_data = array();
}

// Initialize link data with defaults
$link_data = array_merge(array(
    'sub_order_id' => null,
    'expires_at' => null,
    'url' => '',
), $link_data);

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

<div class="wcfp-success-notice" style="margin-bottom: 20px; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
    <?php
    printf(
        /* translators: %1$s: customer first name, %2$s: order number */
        esc_html__('Hi %1$s, here\'s your payment link for order #%2$s.', 'wc-flex-pay'),
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

<?php if (!empty($link_data['url'])) : ?>
    <div class="wcfp-summary-box" style="margin-bottom: 30px;">
        <h3 class="wcfp-heading"><?php esc_html_e('Payment Link', 'wc-flex-pay'); ?></h3>
        <div class="wcfp-installment-details">
            <?php if (!empty($payment_data['current_installment'])) : ?>
                <p>
                    <?php
                    printf(
                        /* translators: 1: installment number, 2: formatted amount */
                        esc_html__('This payment link is for installment #%1$d in the amount of %2$s.', 'wc-flex-pay'),
                        $payment_data['current_installment']['number'],
                        wc_price($payment_data['current_installment']['amount'])
                    );
                    ?>
                </p>
            <?php endif; ?>

            <p><?php esc_html_e('Please use the button below to make your payment:', 'wc-flex-pay'); ?></p>

            <?php
            $actions = array(
                'pay' => array(
                    'url' => $link_data['url'],
                    'text' => __('Pay Now', 'wc-flex-pay')
                )
            );
            $primary_action = 'pay';

            include WCFP_PLUGIN_DIR . 'templates/emails/partials/action-buttons.php';

            if (!empty($link_data['expires_at'])) : ?>
                <p class="wcfp-text-small" style="margin-top: 15px;">
                    <?php
                    printf(
                        /* translators: %s: formatted date */
                        esc_html__('This payment link will expire on %s.', 'wc-flex-pay'),
                        date_i18n(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            strtotime($link_data['expires_at'])
                        )
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
<?php endif;

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if (isset($additional_content) && !empty($additional_content)) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
