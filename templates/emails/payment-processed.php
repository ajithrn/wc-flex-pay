<?php
/**
 * Payment processed email
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
do_action('woocommerce_email_header', $subject, $email);
?>

<div class="wcfp-success-notice" style="margin-bottom: 20px; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
    <?php
    printf(
        /* translators: %1$s: Customer first name, %2$s: Order number, %3$s: Payment amount */
        esc_html__('Hi %1$s, your payment of %3$s for order #%2$s has been processed successfully.', 'wc-flex-pay'),
        esc_html($order->get_billing_first_name()),
        esc_html($order->get_order_number()),
        wc_price($payment['amount'])
    );
    ?>
</div>

<div class="wcfp-summary-box" style="margin-bottom: 30px;">
    <h3 class="wcfp-heading"><?php esc_html_e('Payment Details', 'wc-flex-pay'); ?></h3>
    <table class="wcfp-summary-table">
        <tbody>
            <tr>
                <th><?php esc_html_e('Payment Amount', 'wc-flex-pay'); ?></th>
                <td class="amount"><?php echo wc_price($payment['amount']); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Payment Date', 'wc-flex-pay'); ?></th>
                <td><?php echo date_i18n(get_option('date_format'), current_time('timestamp')); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Transaction ID', 'wc-flex-pay'); ?></th>
                <td><?php echo esc_html($payment['transaction_id']); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="wcfp-installment-details">
    <?php
    printf(
        /* translators: %s: Order link */
        wp_kses(__('You can view your order details by clicking <a href="%s">here</a>.', 'wc-flex-pay'), array('a' => array('href' => array()))),
        esc_url($order->get_view_order_url())
    );
    ?>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
