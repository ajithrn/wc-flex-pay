<?php
/**
 * Payment Reminder Email Template (HTML)
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
    'sub_order_id' => $link_data['sub_order_id'] ?? null,
    'payment_method' => $order->get_payment_method_title(),
    'expiry_date' => $link_data['expires_at'] ?? null
), $payment_data);

// Get payment status for upcoming installments only
$upcoming_payments = array();
foreach ($order->get_items() as $item) {
    if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
        $payment_status = $item->get_meta('_wcfp_payment_status');
        if (!empty($payment_status)) {
            foreach ($payment_status as $payment_id => $payment) {
                $amount = $payment['amount'] * $item->get_quantity();
                $payment_data['total_amount'] += $amount;
                
                if ($payment['status'] === 'completed') {
                    $payment_data['paid_amount'] += $amount;
                } else {
                    $payment_data['pending_amount'] += $amount;
                    if (strtotime($payment['due_date']) > current_time('timestamp')) {
                        $upcoming_payments[] = array_merge($payment, array(
                            'number' => $payment_id + 1
                        ));
                    }
                }
            }
            
            // Set current installment
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

// Sort upcoming payments by due date
usort($upcoming_payments, function($a, $b) {
    return strtotime($a['due_date']) - strtotime($b['due_date']);
});
?>

<div class="wcfp-success-notice" style="margin-bottom: 20px; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
    <?php
    printf(
        /* translators: %1$s: customer first name, %2$s: order number */
        esc_html__('Hi %1$s, this is a reminder about your upcoming payment for order #%2$s.', 'wc-flex-pay'),
        esc_html($order->get_billing_first_name()),
        esc_html($order->get_order_number())
    );
    ?>
</div>

<div class="wcfp-summary-box" style="margin-bottom: 30px;">
    <h3 class="wcfp-heading"><?php esc_html_e('Payment Summary', 'wc-flex-pay'); ?></h3>
    <?php
    // Include payment summary
    include WCFP_PLUGIN_DIR . 'templates/emails/partials/payment-summary.php';
    ?>
</div>

<?php if (!empty($payment_data['current_installment'])) : ?>
    <div class="wcfp-summary-box" style="margin-bottom: 30px;">
        <h3 class="wcfp-heading"><?php esc_html_e('Current Payment', 'wc-flex-pay'); ?></h3>
        <table class="wcfp-summary-table">
                <tr>
                    <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                    <td class="amount">
                        <?php 
                        printf(
                            /* translators: %d: installment number */
                            esc_html__('#%d', 'wc-flex-pay'),
                            $payment_data['current_installment']['number']
                        ); 
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                    <td class="amount"><?php echo wc_price($payment_data['current_installment']['amount']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Due Date', 'wc-flex-pay'); ?></th>
                    <td class="amount">
                        <?php 
                        echo date_i18n(
                            get_option('date_format'),
                            strtotime($payment_data['current_installment']['due_date'])
                        ); 
                        ?>
                    </td>
                </tr>
            </table>
    </div>
<?php endif; ?>

<div class="wcfp-summary-box" style="margin-bottom: 30px;">
    <h3 class="wcfp-heading"><?php esc_html_e('Order Details', 'wc-flex-pay'); ?></h3>
    <?php
    // Include order details
    include WCFP_PLUGIN_DIR . 'templates/emails/partials/order-details.php';
    ?>
</div>

<?php
// Prepare action buttons if URL is available
if (!empty($link_data['url'])) {
    $actions = array(
        'pay' => array(
            'url' => $link_data['url'],
            'text' => __('Pay Now', 'wc-flex-pay')
        )
    );
    $primary_action = 'pay';

    // Include action buttons
    include WCFP_PLUGIN_DIR . 'templates/emails/partials/action-buttons.php';
}

// Show payment notice
if (!empty($payment_data['current_installment'])) : ?>
    <div class="wcfp-divider"></div>
    <div class="wcfp-installment-details">
        <?php
        printf(
            /* translators: 1: formatted amount, 2: formatted date */
            esc_html__('Your payment of %1$s is due on %2$s. Please ensure to make the payment before the due date to avoid any late fees.', 'wc-flex-pay'),
            wc_price($payment_data['current_installment']['amount']),
            date_i18n(get_option('date_format'), strtotime($payment_data['current_installment']['due_date']))
        );

        if (!empty($link_data['expires_at'])) {
            echo '<br><br>';
            printf(
                /* translators: %s: formatted date */
                esc_html__('This payment link will expire on %s.', 'wc-flex-pay'),
                date_i18n(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    strtotime($link_data['expires_at'])
                )
            );
        }
        ?>
    </div>
<?php endif; ?>

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
