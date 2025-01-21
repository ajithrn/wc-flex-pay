<?php
/**
 * Payment Failed email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/payment-failed.php.
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

$total_remaining = array_sum(array_column($remaining_payments, 'amount'));
$grace_period = absint(get_option('wcfp_overdue_grace_period', 3));
$retry_url = add_query_arg(array(
    'retry_payment' => $payment['id'],
    'order_key' => $order->get_order_key(),
), $order->get_checkout_payment_url());

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="margin-bottom: 40px;">
    <p style="margin-bottom: 20px;">
        <?php printf(esc_html__('Hi %s,', 'wc-flex-pay'), esc_html($order->get_billing_first_name())); ?>
    </p>

    <div style="background: #fff1f0; border: 1px solid #d63638; padding: 20px; margin-bottom: 30px;">
        <p style="margin: 0; color: #d63638; font-size: 16px;">
            <?php esc_html_e('Unfortunately, your scheduled payment for your order has failed to process.', 'wc-flex-pay'); ?>
        </p>
    </div>

    <div style="background: #f8f8f8; border: 1px solid #ddd; padding: 20px; margin-bottom: 30px;">
        <h2 style="color: #d63638; margin: 0 0 20px; font-size: 18px;">
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
                    <?php esc_html_e('Due Date:', 'wc-flex-pay'); ?>
                </th>
                <td style="text-align: left; padding: 12px; border-bottom: 1px solid #eee;">
                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($payment['due_date']))); ?>
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
            <?php if (!empty($payment['error_message'])) : ?>
                <tr>
                    <th scope="row" style="text-align: left; padding: 12px; border-bottom: 1px solid #eee;">
                        <?php esc_html_e('Error Details:', 'wc-flex-pay'); ?>
                    </th>
                    <td style="text-align: left; padding: 12px; border-bottom: 1px solid #eee; color: #d63638;">
                        <?php echo esc_html($payment['error_message']); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if (!empty($remaining_payments)) : ?>
        <div style="background: #fff8e5; border: 1px solid #ffba00; padding: 20px; margin-bottom: 30px;">
            <h3 style="color: #ffba00; margin: 0 0 15px; font-size: 16px;">
                <?php esc_html_e('Payment Schedule Overview', 'wc-flex-pay'); ?>
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

            <p style="margin: 0; color: #666;">
                <?php
                printf(
                    esc_html__('Please ensure your payment method is up to date to avoid any interruption in your payment schedule.', 'wc-flex-pay')
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div style="margin-bottom: 30px;">
        <h2 style="color: #2ea2cc; margin: 0 0 20px; font-size: 18px;">
            <?php esc_html_e('What to do next', 'wc-flex-pay'); ?>
        </h2>

        <div style="background: #f0f8ff; border: 1px solid #2ea2cc; padding: 20px; margin-bottom: 20px;">
            <p style="margin: 0 0 15px;">
                <?php esc_html_e('To ensure your order remains active, please take one of the following actions:', 'wc-flex-pay'); ?>
            </p>

            <ol style="margin: 0; padding-left: 20px;">
                <li style="margin-bottom: 10px;">
                    <strong><?php esc_html_e('Retry Payment:', 'wc-flex-pay'); ?></strong><br>
                    <a href="<?php echo esc_url($retry_url); ?>" 
                       style="display: inline-block; padding: 10px 20px; background: #2ea2cc; color: #fff; text-decoration: none; border-radius: 3px; margin: 10px 0;">
                        <?php esc_html_e('Click here to retry payment', 'wc-flex-pay'); ?>
                    </a>
                </li>
                <li style="margin-bottom: 10px;">
                    <strong><?php esc_html_e('Update Payment Method:', 'wc-flex-pay'); ?></strong><br>
                    <a href="<?php echo esc_url($order->get_view_order_url()); ?>" 
                       style="color: #2ea2cc; text-decoration: none;">
                        <?php esc_html_e('Visit your account dashboard', 'wc-flex-pay'); ?>
                    </a>
                </li>
                <li>
                    <strong><?php esc_html_e('Contact Support:', 'wc-flex-pay'); ?></strong><br>
                    <a href="mailto:<?php echo esc_attr(get_option('woocommerce_email_from_address')); ?>" 
                       style="color: #2ea2cc; text-decoration: none;">
                        <?php esc_html_e('Email our support team', 'wc-flex-pay'); ?>
                    </a>
                </li>
            </ol>
        </div>

        <div style="background: #fff1f0; border: 1px solid #d63638; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #d63638; font-size: 0.9em;">
                <?php
                printf(
                    esc_html__('Important: If the payment is not processed within %d days, your order may be suspended.', 'wc-flex-pay'),
                    $grace_period
                );
                ?>
            </p>
        </div>

        <p style="margin: 0; color: #666;">
            <?php esc_html_e('We appreciate your prompt attention to this matter.', 'wc-flex-pay'); ?>
        </p>
    </div>
</div>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
