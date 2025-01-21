<?php
/**
 * Payment Complete email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/payment-complete.php.
 *
 * @package WC_Flex_Pay\Templates\Emails
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!isset($order) || !isset($payment) || !isset($email)) {
    return;
}

$payment_manager = new \WCFP\Payment();
$remaining_payments = $payment_manager->get_order_payments($order->get_id());
$remaining_payments = array_filter($remaining_payments, function($p) {
    return $p['status'] === 'pending';
});

$next_payment = reset($remaining_payments);
$total_remaining = array_sum(array_column($remaining_payments, 'amount'));

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="margin-bottom: 40px;">
    <p style="margin-bottom: 20px;"><?php printf(esc_html__('Hi %s,', 'wc-flex-pay'), esc_html($order->get_billing_first_name())); ?></p>

    <p style="margin-bottom: 30px; font-size: 16px;">
        <?php esc_html_e('Great news! Your scheduled payment for your order has been successfully processed.', 'wc-flex-pay'); ?>
    </p>

    <div style="background: #f8f8f8; border: 1px solid #ddd; padding: 20px; margin-bottom: 30px;">
        <h2 style="color: #2ea2cc; margin: 0 0 20px; font-size: 18px;">
            <?php
            printf(
                esc_html__('Payment Details (Order #%s)', 'wc-flex-pay'),
                esc_html($order->get_order_number())
            );
            ?>
        </h2>

        <table cellspacing="0" cellpadding="6" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <tr>
                <th scope="row" style="text-align: left; padding: 12px; border-bottom: 1px solid #eee;">
                    <?php esc_html_e('Payment Amount:', 'wc-flex-pay'); ?>
                </th>
                <td style="text-align: left; padding: 12px; border-bottom: 1px solid #eee;">
                    <?php echo wp_kses_post(wc_price($payment['amount'])); ?>
                </td>
            </tr>
            <tr>
                <th scope="row" style="text-align: left; padding: 12px; border-bottom: 1px solid #eee;">
                    <?php esc_html_e('Payment Date:', 'wc-flex-pay'); ?>
                </th>
                <td style="text-align: left; padding: 12px; border-bottom: 1px solid #eee;">
                    <?php echo esc_html(date_i18n(get_option('date_format'), current_time('timestamp'))); ?>
                </td>
            </tr>
            <tr>
                <th scope="row" style="text-align: left; padding: 12px; border-bottom: 1px solid #eee;">
                    <?php esc_html_e('Payment Method:', 'wc-flex-pay'); ?>
                </th>
                <td style="text-align: left; padding: 12px; border-bottom: 1px solid #eee;">
                    <?php echo wp_kses_post($order->get_payment_method_title()); ?>
                </td>
            </tr>
            <?php if (!empty($payment['transaction_id'])) : ?>
                <tr>
                    <th scope="row" style="text-align: left; padding: 12px; border-bottom: 1px solid #eee;">
                        <?php esc_html_e('Transaction ID:', 'wc-flex-pay'); ?>
                    </th>
                    <td style="text-align: left; padding: 12px; border-bottom: 1px solid #eee;">
                        <?php echo esc_html($payment['transaction_id']); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if (!empty($remaining_payments)) : ?>
        <div style="background: #fff8e5; border: 1px solid #ffba00; padding: 20px; margin-bottom: 30px;">
            <h3 style="color: #ffba00; margin: 0 0 15px; font-size: 16px;">
                <?php esc_html_e('Upcoming Payments', 'wc-flex-pay'); ?>
            </h3>

            <p style="margin: 0 0 15px;">
                <?php
                printf(
                    esc_html__('You have %1$s in remaining payments (%2$d installment(s)).', 'wc-flex-pay'),
                    wc_price($total_remaining),
                    count($remaining_payments)
                );
                ?>
            </p>

            <?php if ($next_payment) : ?>
                <p style="margin: 0; color: #666;">
                    <?php
                    printf(
                        esc_html__('Your next payment of %1$s is scheduled for %2$s.', 'wc-flex-pay'),
                        wc_price($next_payment['amount']),
                        date_i18n(get_option('date_format'), strtotime($next_payment['due_date']))
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>

        <div style="margin-bottom: 30px;">
            <p style="margin: 0 0 15px;">
                <?php
                printf(
                    esc_html__('You can view your complete payment schedule and manage your payments in your account dashboard: %s', 'wc-flex-pay'),
                    '<a href="' . esc_url($order->get_view_order_url()) . '" style="color: #2ea2cc; text-decoration: none;">' . 
                    esc_html__('View Order', 'wc-flex-pay') . 
                    '</a>'
                );
                ?>
            </p>

            <p style="margin: 0; color: #666; font-size: 0.9em;">
                <?php esc_html_e('Please ensure your payment method is up to date to avoid any interruption in service.', 'wc-flex-pay'); ?>
            </p>
        </div>
    <?php else : ?>
        <div style="background: #f0f8ff; border: 1px solid #2ea2cc; padding: 20px; margin-bottom: 30px;">
            <p style="margin: 0; color: #2ea2cc; font-size: 16px;">
                <?php esc_html_e('This was your final payment. Thank you for completing all payments for this order!', 'wc-flex-pay'); ?>
            </p>
        </div>
    <?php endif; ?>

    <p style="margin: 0; text-align: center; color: #666;">
        <?php esc_html_e('Thank you for your business!', 'wc-flex-pay'); ?>
    </p>
</div>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
