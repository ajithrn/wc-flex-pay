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

<p class="wcfp-greeting">
    <?php
    printf(
        /* translators: %1$s: customer first name, %2$s: order number */
        esc_html__('Hi %1$s, here\'s your payment link for order #%2$s.', 'wc-flex-pay'),
        esc_html($order->get_billing_first_name()),
        esc_html($order->get_order_number())
    );
    ?>
</p>

<?php
// Include payment summary if file exists
$payment_summary_template = WCFP_PLUGIN_DIR . 'templates/emails/partials/payment-summary.php';
if (file_exists($payment_summary_template)) {
    include $payment_summary_template;
}

// Include order details if file exists
$order_details_template = WCFP_PLUGIN_DIR . 'templates/emails/partials/order-details.php';
if (file_exists($order_details_template)) {
    include $order_details_template;
}

// Prepare action buttons if URL is available
if (!empty($link_data['url'])) {
    $actions = array(
        'pay' => array(
            'url' => $link_data['url'],
            'text' => __('Pay Now', 'wc-flex-pay')
        )
    );
    $primary_action = 'pay';

    // Include action buttons if file exists
    $action_buttons_template = WCFP_PLUGIN_DIR . 'templates/emails/partials/action-buttons.php';
    if (file_exists($action_buttons_template)) {
        include $action_buttons_template;
    }
}

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
