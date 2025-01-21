<?php
/**
 * Payment Reminder email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/payment-reminder.php.
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
$days_until = floor((strtotime($payment['due_date']) - current_time('timestamp')) / (60 * 60 * 24));
$completed_payments = $payment_manager->get_order_payments($order->get_id());
$completed_payments = array_filter($completed_payments, function($p) {
    return $p['status'] === 'completed';
});

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<div style="margin-bottom: 40px;">
    <p style="margin-bottom: 20px;">
        <?php printf(esc_html__('Hi %s,', 'wc-flex-pay'), esc_html($order->get_billing_first_name())); ?>
    </p>

    <div style="background: #fff8e5; border: 1px solid #ffba00; padding: 20px; margin-bottom: 30px;">
        <p style="margin: 0; color: #94660c; font-size: 16px;">
            <?php
            printf(
                esc_html__('This is a friendly reminder that your next scheduled payment for order #%s is due in %d days.', 'wc-flex-pay'),
                esc_html($order->get_order_number()),
                $days_until
            );
            ?>
        </p>
    </div>

    <div style="background: #f8f8f8; border: 1px solid #ddd; padding: 20px; margin-bottom: 30px;">
        <h2 style="color: #2ea2cc; margin: 0 0 20px; font-size: 18px;">
            <?php esc_html_e('Upcoming Payment Details', 'wc-flex-pay'); ?>
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
                    <?php
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($payment['due_date'])));
                    echo ' <span style="color: #94660c;">(' . sprintf(
                        /* translators: %d: number of days */
                        _n('%d day remaining', '%d days remaining', $days_until, 'wc-flex-pay'),
                        $days_until
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
        </table>
    </div>

    <div style="background: #f0f8ff; border: 1px solid #2ea2cc; padding: 20px; margin-bottom: 30px;">
        <h3 style="color: #2ea2cc; margin: 0 0 15px; font-size: 16px;">
            <?php esc_html_e('Payment Schedule Overview', 'wc-flex-pay'); ?>
        </h3>

        <table cellspacing="0" cellpadding="6" style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
            <tr>
                <th scope="row" style="text-align: left; padding: 8px 0;">
                    <?php esc_html_e('Completed Payments:', 'wc-flex-pay'); ?>
                </th>
                <td style="text-align: right; padding: 8px 0; color: #2ea2cc;">
                    <?php echo count($completed_payments); ?>
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
                    <?php esc_html_e('Remaining Balance:', 'wc-flex-pay'); ?>
                </th>
                <td style="text-align: right; padding: 8px 0;">
                    <?php echo wc_price($total_remaining); ?>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 30px;">
        <h2 style="color: #2ea2cc; margin: 0 0 20px; font-size: 18px;">
            <?php esc_html_e('Important Information', 'wc-flex-pay'); ?>
        </h2>

        <ul style="margin: 0 0 20px; padding-left: 20px;">
            <li style="margin-bottom: 10px;">
                <?php
                esc_html_e('The payment will be automatically processed using your saved payment method on the due date.', 'wc-flex-pay');
                ?>
            </li>
            <li style="margin-bottom: 10px;">
                <?php
                esc_html_e('Please ensure your payment method is up to date and has sufficient funds available.', 'wc-flex-pay');
                ?>
            </li>
            <li>
                <?php
                printf(
                    esc_html__('You can review and manage your payment details in your %saccount dashboard%s.', 'wc-flex-pay'),
                    '<a href="' . esc_url($order->get_view_order_url()) . '" style="color: #2ea2cc; text-decoration: none;">',
                    '</a>'
                );
                ?>
            </li>
        </ul>

        <div style="text-align: center;">
            <a href="<?php echo esc_url($order->get_view_order_url()); ?>" 
               style="display: inline-block; padding: 12px 25px; background: #2ea2cc; color: #fff; text-decoration: none; border-radius: 3px;">
                <?php esc_html_e('View Payment Schedule', 'wc-flex-pay'); ?>
            </a>
        </div>
    </div>

    <p style="margin: 0; text-align: center; color: #666;">
        <?php esc_html_e('Thank you for your business!', 'wc-flex-pay'); ?>
    </p>
</div>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);
