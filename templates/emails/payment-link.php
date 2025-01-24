<?php
/**
 * Payment Link Email Template (HTML)
 *
 * @package WC_Flex_Pay\Templates\Emails
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email); ?>

<p><?php
printf(
    /* translators: %1$s: customer first name, %2$s: order number */
    esc_html__('Hi %1$s, here\'s your payment link for order #%2$s.', 'wc-flex-pay'),
    esc_html($order->get_billing_first_name()),
    esc_html($order->get_order_number())
);
?></p>

<?php
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
?>

<div style="margin-bottom: 40px; padding: 20px; background-color: #f8f8f8; border-radius: 3px;">
    <h2 style="margin: 0 0 20px; color: #7f54b3;"><?php esc_html_e('Payment Summary', 'wc-flex-pay'); ?></h2>
    <table cellspacing="0" cellpadding="6" style="width: 100%; border: none;">
        <tr>
            <td style="text-align: left; padding: 6px 0;"><?php esc_html_e('Total Amount:', 'wc-flex-pay'); ?></td>
            <td style="text-align: right; padding: 6px 0;"><?php echo wc_price($total_amount); ?></td>
        </tr>
        <tr>
            <td style="text-align: left; padding: 6px 0;"><?php esc_html_e('Amount Paid:', 'wc-flex-pay'); ?></td>
            <td style="text-align: right; padding: 6px 0;"><?php echo wc_price($paid_amount); ?></td>
        </tr>
        <tr>
            <td style="text-align: left; padding: 6px 0;"><?php esc_html_e('Pending Amount:', 'wc-flex-pay'); ?></td>
            <td style="text-align: right; padding: 6px 0;"><?php echo wc_price($pending_amount); ?></td>
        </tr>
        <?php if ($installment) : ?>
            <tr>
                <td colspan="2" style="padding: 20px 0 10px;">
                    <strong><?php esc_html_e('Current Installment Details:', 'wc-flex-pay'); ?></strong>
                </td>
            </tr>
            <tr>
                <td style="text-align: left; padding: 6px 0;">
                    <?php 
                    printf(
                        /* translators: %d: installment number */
                        esc_html__('Installment #%d:', 'wc-flex-pay'),
                        $installment_number
                    ); 
                    ?>
                </td>
                <td style="text-align: right; padding: 6px 0;"><?php echo wc_price($installment['amount']); ?></td>
            </tr>
            <tr>
                <td style="text-align: left; padding: 6px 0;"><?php esc_html_e('Due Date:', 'wc-flex-pay'); ?></td>
                <td style="text-align: right; padding: 6px 0;">
                    <?php echo date_i18n(get_option('date_format'), strtotime($installment['due_date'])); ?>
                </td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<?php if (!empty($link_data['sub_order_id'])) : ?>
    <?php
    $sub_order = wc_get_order($link_data['sub_order_id']);
    if ($sub_order) {
        printf(
            '<p>' . esc_html__('Sub-order: #%s', 'wc-flex-pay') . '</p>',
            esc_html($sub_order->get_order_number())
        );
    }
    ?>
<?php endif; ?>

<p><?php
printf(
    /* translators: %s: expiry date */
    esc_html__('Please note that this payment link will expire on %s.', 'wc-flex-pay'),
    date_i18n(
        get_option('date_format') . ' ' . get_option('time_format'),
        strtotime($link_data['expires_at'])
    )
);
?></p>

<div style="margin: 30px auto; text-align: center;">
    <a href="<?php echo esc_url($link_data['url']); ?>" style="
        background-color: #7f54b3;
        border-radius: 3px;
        color: #ffffff;
        display: inline-block;
        font-size: 16px;
        font-weight: bold;
        line-height: 1.4;
        padding: 12px 24px;
        text-decoration: none;
        text-align: center;
        -webkit-text-size-adjust: none;
    "><?php esc_html_e('Pay Now', 'wc-flex-pay'); ?></a>
</div>

<p style="font-size: 12px; color: #666; text-align: center;">
    <?php esc_html_e('Or copy and paste this link in your browser:', 'wc-flex-pay'); ?><br>
    <span style="color: #444; word-break: break-all;"><?php echo esc_url($link_data['url']); ?></span>
</p>

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
