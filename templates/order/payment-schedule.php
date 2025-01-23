<?php
/**
 * Payment Schedule Template
 *
 * This template can be used for both frontend and admin displays.
 *
 * @package WC_Flex_Pay\Templates
 * @var WC_Order $order Order object
 * @var bool     $is_admin Whether this is being displayed in admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Set default value for $is_admin if not provided
if (!isset($is_admin)) {
    $is_admin = false;
}

$order_manager = new \WCFP\Order();
$payments = $order_manager->get_order_payments($order);

if (!$payments['has_installments']) {
    return;
}

$total_pending = $payments['total_pending'];
$future_payments = $payments['future_payments'];
$completed_payments = $payments['completed_payments'];

// Get payment links
$payment_links = array();
$links = get_post_meta($order->get_id(), '_wcfp_payment_links', true) ?: array();
foreach ($links as $installment_number => $link) {
    $payment_links[$installment_number] = $link;
}
?>

<div class="wcfp-order-payments">
    <div class="wcfp-payment-summary">
        <div class="wcfp-summary-grid">
            <div class="wcfp-summary-item">
                <span class="wcfp-label"><?php esc_html_e('Total Paid:', 'wc-flex-pay'); ?></span>
                <span class="wcfp-value wcfp-success">
                    <?php echo wc_price(array_sum(array_column($completed_payments, 'amount'))); ?>
                </span>
            </div>
            <div class="wcfp-summary-item">
                <span class="wcfp-label"><?php esc_html_e('Remaining Balance:', 'wc-flex-pay'); ?></span>
                <span class="wcfp-value wcfp-warning"><?php echo wc_price($total_pending); ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($completed_payments)) : ?>
        <h3><?php esc_html_e('Completed Payments', 'wc-flex-pay'); ?></h3>
        <table class="woocommerce-table wcfp-payment-schedule completed">
            <thead>
                <tr>
                    <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Product', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Payment Date', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Transaction ID', 'wc-flex-pay'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($completed_payments as $payment) : ?>
                    <tr>
                        <td><?php printf(__('Installment %d', 'wc-flex-pay'), $payment['number']); ?></td>
                        <td><?php echo esc_html($payment['product_name']); ?></td>
                        <td><?php echo wc_price($payment['amount']); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($payment['payment_date']))); ?></td>
                        <td><?php echo esc_html($payment['transaction_id'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($future_payments)) : ?>
        <h3>
            <?php 
            printf(
                /* translators: %s: total pending amount */
                esc_html__('Upcoming Payments (Total: %s)', 'wc-flex-pay'),
                wc_price($total_pending)
            );
            ?>
        </h3>
        <table class="woocommerce-table wcfp-payment-schedule upcoming">
            <thead>
                <tr>
                    <th><?php esc_html_e('Due Date', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Product', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                    <?php if ($is_admin) : ?>
                        <th><?php esc_html_e('Payment Link', 'wc-flex-pay'); ?></th>
                        <th><?php esc_html_e('Actions', 'wc-flex-pay'); ?></th>
                    <?php else : ?>
                        <th colspan="2"><?php esc_html_e('Payment', 'wc-flex-pay'); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // Collect all installments from future payments
                $all_installments = array();
                foreach ($future_payments as $date => $payment) {
                    foreach ($payment['installments'] as $installment) {
                        $all_installments[] = array_merge($installment, array('date' => $date));
                    }
                }

                // Sort installments by number
                usort($all_installments, function($a, $b) {
                    return $a['number'] - $b['number'];
                });

                // Display sorted installments
                foreach ($all_installments as $installment) :
                    $is_overdue = strtotime($installment['due_date']) < current_time('timestamp');
                    $link = isset($payment_links[$installment['number']]) ? $payment_links[$installment['number']] : null;
                    $link_active = $link && $link['status'] === 'active' && (!empty($link['expires_at']) && strtotime($link['expires_at']) > current_time('timestamp'));
                ?>
                    <tr class="<?php echo $is_overdue ? 'wcfp-overdue' : ''; ?>">
                        <td>
                            <?php 
                            echo esc_html($installment['date']);
                            if ($is_overdue) {
                                echo ' <span class="wcfp-overdue-label">' . esc_html__('(Overdue)', 'wc-flex-pay') . '</span>';
                            }
                            ?>
                        </td>
                        <td><?php printf(__('Installment %d', 'wc-flex-pay'), $installment['number']); ?></td>
                        <td><?php echo esc_html($installment['product_name']); ?></td>
                        <td><?php echo wc_price($installment['amount']); ?></td>
                        <?php if ($is_admin) : ?>
                            <td>
                                <?php if ($link_active) : ?>
                                    <div class="wcfp-payment-link">
                                        <input type="text" 
                                               class="wcfp-link-input" 
                                               value="<?php echo esc_attr($link['url']); ?>" 
                                               readonly>
                                        <button type="button" 
                                                class="button copy-link" 
                                                data-clipboard-text="<?php echo esc_attr($link['url']); ?>">
                                            <?php esc_html_e('Copy', 'wc-flex-pay'); ?>
                                        </button>
                                        <button type="button"
                                                class="button send-link"
                                                data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                                                data-installment="<?php echo esc_attr($installment['number']); ?>">
                                            <?php esc_html_e('Send Email', 'wc-flex-pay'); ?>
                                        </button>
                                        <p class="wcfp-link-expiry">
                                            <?php
                                            printf(
                                                /* translators: %s: expiry date */
                                                esc_html__('Expires: %s', 'wc-flex-pay'),
                                                date_i18n(
                                                    get_option('date_format') . ' ' . get_option('time_format'),
                                                    strtotime($link['expires_at'])
                                                )
                                            );
                                            ?>
                                        </p>
                                    </div>
                                <?php else : ?>
                                    <button type="button"
                                            class="button generate-link"
                                            data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                                            data-installment="<?php echo esc_attr($installment['number']); ?>">
                                        <?php 
                                        if ($link && $link['status'] === 'expired') {
                                            esc_html_e('Regenerate Link', 'wc-flex-pay');
                                        } else {
                                            esc_html_e('Generate Link', 'wc-flex-pay');
                                        }
                                        ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" 
                                        class="button process-payment" 
                                        data-payment-id="<?php echo esc_attr($installment['number']); ?>">
                                    <?php esc_html_e('Process Payment', 'wc-flex-pay'); ?>
                                </button>
                            </td>
                        <?php else : ?>
                            <td colspan="2">
                                <?php if ($link_active) : ?>
                                    <a href="<?php echo esc_url($link['url']); ?>" class="button">
                                        <?php esc_html_e('Pay Now', 'wc-flex-pay'); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="wcfp-link-status">
                                        <?php 
                                        if ($link && $link['status'] === 'expired') {
                                            esc_html_e('Link Expired', 'wc-flex-pay');
                                        } else {
                                            esc_html_e('Not Available Yet', 'wc-flex-pay');
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!$is_admin) : ?>
            <p class="wcfp-payment-notice">
                <?php esc_html_e('You will receive payment links for each installment via email before the due date.', 'wc-flex-pay'); ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
