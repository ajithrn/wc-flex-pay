<?php
/**
 * Payment processed email
 *
 * @package WC_Flex_Pay\Templates\Emails
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $subject, $email);
?>

<p>
    <?php
    printf(
        /* translators: %s: Customer first name */
        esc_html__('Hi %s,', 'wc-flex-pay'),
        esc_html($order->get_billing_first_name())
    );
    ?>
</p>

<p>
    <?php
    printf(
        /* translators: %1$s: Order number, %2$s: Payment amount */
        esc_html__('Your payment of %2$s for order #%1$s has been processed successfully.', 'wc-flex-pay'),
        esc_html($order->get_order_number()),
        wc_price($payment['amount'])
    );
    ?>
</p>

<h2><?php esc_html_e('Payment Details', 'wc-flex-pay'); ?></h2>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 20px;">
    <tbody>
        <tr>
            <td class="td" scope="row" style="text-align: left; border: 1px solid #ddd; padding: 12px;">
                <strong><?php esc_html_e('Payment Amount:', 'wc-flex-pay'); ?></strong>
            </td>
            <td class="td" style="text-align: left; border: 1px solid #ddd; padding: 12px;">
                <?php echo wc_price($payment['amount']); ?>
            </td>
        </tr>
        <tr>
            <td class="td" scope="row" style="text-align: left; border: 1px solid #ddd; padding: 12px;">
                <strong><?php esc_html_e('Payment Date:', 'wc-flex-pay'); ?></strong>
            </td>
            <td class="td" style="text-align: left; border: 1px solid #ddd; padding: 12px;">
                <?php echo date_i18n(get_option('date_format'), current_time('timestamp')); ?>
            </td>
        </tr>
        <tr>
            <td class="td" scope="row" style="text-align: left; border: 1px solid #ddd; padding: 12px;">
                <strong><?php esc_html_e('Transaction ID:', 'wc-flex-pay'); ?></strong>
            </td>
            <td class="td" style="text-align: left; border: 1px solid #ddd; padding: 12px;">
                <?php echo esc_html($payment['transaction_id']); ?>
            </td>
        </tr>
    </tbody>
</table>

<p>
    <?php
    printf(
        /* translators: %s: Order link */
        wp_kses(__('You can view your order details by clicking <a href="%s">here</a>.', 'wc-flex-pay'), array('a' => array('href' => array()))),
        esc_url($order->get_view_order_url())
    );
    ?>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
