<?php
/**
 * Order Details Email Template (HTML)
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
    'payment_method' => $order->get_payment_method_title(),
    'expires_at' => null,
), $payment_data);

// Get payment status for all installments
$installments = array(
    'upcoming' => array(),
    'completed' => array()
);

foreach ($order->get_items() as $item) {
    if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
        $payment_status = $item->get_meta('_wcfp_payment_status');
        if (!empty($payment_status)) {
            foreach ($payment_status as $payment_id => $payment) {
                $amount = $payment['amount'] * $item->get_quantity();
                $payment_data['total_amount'] += $amount;
                
                if ($payment['status'] === 'completed') {
                    $payment_data['paid_amount'] += $amount;
                    $installments['completed'][] = array_merge($payment, array('number' => $payment_id + 1));
                } else {
                    $payment_data['pending_amount'] += $amount;
                    $installments['upcoming'][] = array_merge($payment, array('number' => $payment_id + 1));
                }
            }
        }
        break;
    }
}

// Sort upcoming installments by due date
usort($installments['upcoming'], function($a, $b) {
    return strtotime($a['due_date']) - strtotime($b['due_date']);
});

// Sort completed installments by payment date
usort($installments['completed'], function($a, $b) {
    return strtotime($b['payment_date'] ?? '0') - strtotime($a['payment_date'] ?? '0');
});
?>

<div class="wcfp-greeting">
    <?php
    printf(
        /* translators: %1$s: customer first name, %2$s: order number */
        esc_html__('Hi %1$s, here are your payment details for order #%2$s.', 'wc-flex-pay'),
        esc_html($order->get_billing_first_name()),
        esc_html($order->get_order_number())
    );
    ?>
</div>

<div class="wcfp-section">
    <h3 class="wcfp-heading"><?php esc_html_e('Payment Summary', 'wc-flex-pay'); ?></h3>
    <div class="wcfp-summary-box">
        <?php
        // Include payment summary
        include WCFP_PLUGIN_DIR . 'templates/emails/partials/payment-summary.php';
        ?>
    </div>
</div>

<?php if (!empty($installments['upcoming'])) : ?>
    <div class="wcfp-divider"></div>
    <div class="wcfp-section">
        <h3 class="wcfp-heading"><?php esc_html_e('Upcoming Payments', 'wc-flex-pay'); ?></h3>
        <div class="wcfp-summary-box">
            <table class="wcfp-summary-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                        <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                        <th><?php esc_html_e('Due Date', 'wc-flex-pay'); ?></th>
                        <th><?php esc_html_e('Status', 'wc-flex-pay'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($installments['upcoming'] as $installment) : ?>
                        <tr>
                            <td>
                                <?php 
                                printf(
                                    /* translators: %d: installment number */
                                    esc_html__('#%d', 'wc-flex-pay'),
                                    $installment['number']
                                ); 
                                ?>
                            </td>
                            <td class="amount"><?php echo wc_price($installment['amount']); ?></td>
                            <td>
                                <?php 
                                echo date_i18n(
                                    get_option('date_format'),
                                    strtotime($installment['due_date'])
                                ); 
                                ?>
                            </td>
                            <td>
                                <span class="wcfp-status <?php echo esc_attr($installment['status']); ?>">
                                    <?php echo esc_html(ucfirst($installment['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($installments['completed'])) : ?>
    <div class="wcfp-divider"></div>
    <div class="wcfp-section">
        <h3 class="wcfp-heading"><?php esc_html_e('Completed Payments', 'wc-flex-pay'); ?></h3>
        <div class="wcfp-summary-box">
            <table class="wcfp-summary-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                        <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                        <th><?php esc_html_e('Payment Date', 'wc-flex-pay'); ?></th>
                        <th><?php esc_html_e('Transaction ID', 'wc-flex-pay'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($installments['completed'] as $installment) : ?>
                        <tr>
                            <td>
                                <?php 
                                printf(
                                    /* translators: %d: installment number */
                                    esc_html__('#%d', 'wc-flex-pay'),
                                    $installment['number']
                                ); 
                                ?>
                            </td>
                            <td class="amount"><?php echo wc_price($installment['amount']); ?></td>
                            <td>
                                <?php 
                                echo date_i18n(
                                    get_option('date_format'),
                                    strtotime($installment['payment_date'])
                                ); 
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html($installment['transaction_id'] ?? '-'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="wcfp-divider"></div>

<div class="wcfp-section">
    <h3 class="wcfp-heading"><?php esc_html_e('Order Details', 'wc-flex-pay'); ?></h3>
    <div class="wcfp-summary-box">
        <?php
        // Include order details
        include WCFP_PLUGIN_DIR . 'templates/emails/partials/order-details.php';
        ?>
    </div>
</div>

<?php
// Show next payment notice if there are pending payments
if ($payment_data['pending_amount'] > 0 && !empty($installments['upcoming'])) : ?>
    <div class="wcfp-divider"></div>
    <div class="wcfp-installment-details">
        <?php
        $next_payment = reset($installments['upcoming']);
        printf(
            /* translators: 1: formatted amount, 2: formatted date */
            esc_html__('Your next payment of %1$s is due on %2$s. You\'ll receive a payment reminder email before the due date.', 'wc-flex-pay'),
            wc_price($next_payment['amount']),
            date_i18n(get_option('date_format'), strtotime($next_payment['due_date']))
        );
        ?>
    </div>
<?php endif; ?>

<?php
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
