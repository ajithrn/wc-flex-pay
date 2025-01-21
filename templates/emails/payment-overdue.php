<?php
/**
 * Payment Overdue email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/payment-overdue.php.
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
$days_overdue = floor((current_time('timestamp') - strtotime($payment['due_date'])) / (60 * 60 * 24));
$grace_period = absint(get_option('wcfp_overdue_grace_period', 3));
$days_remaining = $grace_period - $days_overdue;
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
            <?php
            printf(
                esc_html__('This is an urgent notice regarding your payment for order #%s which is now %d days overdue.', 'wc-flex-pay'),
                esc_html($order->get_order_number()),
                $days_overdue
            );
            ?>
        </p>
    </div>

    <div style="background: #f8f8f8; border: 1px solid #ddd; padding: 20px; margin-bottom: 30px;">
        <h2 style="color: #d63638; margin: 0 0 20px; font-size: 18px;">
            <?php esc_html_e('Overdue Payment Details', 'wc-flex-pay'); ?>
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
                <td style="text-align: left; padding: 12px; border-bottom: 1px solid #eee; color: #d63638;">
                    <?php
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($payment['due_date'])));
                    echo ' <span style="color: #d63638;">(' . sprintf(
                        /* translators: %d: number of days */
                        _n('%d day overdue', '%d days overdue', $days_overdue, 'wc-flex-pay'),
                        $days_overdue
                    ) . ')</span>';
                    ?>
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

    <div style="background: #fff8e5; border: 1px solid #ffba00; padding: 20px; margin-bottom: 30px;">
        <h3 style="color: #94660c; margin: 0 0 15px; font-size: 16px;">
            <?php esc_html_e('Payment Schedule Overview', 'wc-flex-pay'); ?>
        </h3>

        <table cellspacing="0" cellpadding="6" style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
            <tr>
                <th scope="row" style="text-align: left; padding: 8px 0;">
                    <?php esc_html_e('Total Remaining Balance:', 'wc-flex-pay'); ?>
                </th>
                <td style="text-align: right; padding: 8px 0;">
                    <?php echo wc_price($total_remaining); ?>
                </td>
            </tr>
            <tr>
                <th scope="row" style="text-align: left; padding: 8px 0;">
                    <?php esc_html_e('Remaining Payments:', 'wc-flex-pay'); ?>
                </th>
                <td style="text-align: right; padding: 8px 0;">
                    <?php echo count($remaining_payments); ?>
                </td>
            </tr>
            <tr>
                <th scope="row" style="text-align: left; padding: 8px 0;">
                    <?php esc_html_e('Grace Period Remaining:', 'wc-flex-pay'); ?>
                </th>
                <td style="text-align: right; padding: 8px 0; color: #d63638;">
                    <?php
                    printf(
                        /* translators: %d: number of days */
                        _n('%d day', '%d days', $days_remaining, 'wc-flex-pay'),
                        $days_remaining
                    );
                    ?>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 30px;">
        <h2 style="color: #d63638; margin: 0 0 20px; font-size: 18px;">
            <?php esc_html_e('Required Action', 'wc-flex-pay'); ?>
        </h2>

        <div style="background: #fff1f0; border: 1px solid #d63638; padding: 20px; margin-bottom: 20px;">
            <p style="margin: 0 0 15px; color: #d63638;">
                <?php
                printf(
                    esc_html__('To avoid order suspension, please process this overdue payment within the next %d days.', 'wc-flex-pay'),
                    $days_remaining
                );
                ?>
            </p>

            <div style="text-align: center;">
                <a href="<?php echo esc_url($retry_url); ?>" 
                   style="display: inline-block; padding: 12px 25px; background: #d63638; color: #fff; text-decoration: none; border-radius: 3px; margin: 10px 0;">
                    <?php esc_html_e('Process Payment Now', 'wc-flex-pay'); ?>
                </a>
            </div>
        </div>

        <h3 style="color: #2ea2cc; margin: 0 0 15px; font-size: 16px;">
            <?php esc_html_e('How to Resolve This', 'wc-flex-pay'); ?>
        </h3>

        <ol style="margin: 0 0 20px; padding-left: 20px;">
            <li style="margin-bottom: 10px;">
                <?php
                printf(
                    esc_html__('Visit your %saccount dashboard%s to review your payment details', 'wc-flex-pay'),
                    '<a href="' . esc_url($order->get_view_order_url()) . '" style="color: #2ea2cc; text-decoration: none;">',
                    '</a>'
                );
                ?>
            </li>
            <li style="margin-bottom: 10px;">
                <?php esc_html_e('Ensure your payment method is up to date and has sufficient funds', 'wc-flex-pay'); ?>
            </li>
            <li style="margin-bottom: 10px;">
                <?php esc_html_e('Click the "Process Payment Now" button above to complete the payment', 'wc-flex-pay'); ?>
            </li>
            <li>
                <?php
                printf(
                    esc_html__('If you need assistance, please %scontact our support team%s', 'wc-flex-pay'),
                    '<a href="mailto:' . esc_attr(get_option('woocommerce_email_from_address')) . '" style="color: #2ea2cc; text-decoration: none;">',
                    '</a>'
                );
                ?>
            </li>
        </ol>
    </div>

    <p style="margin: 0; text-align: center; color: #666;">
        <?php esc_html_e('Thank you for your immediate attention to this matter.', 'wc-flex-pay'); ?>
    </p>
</div>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
