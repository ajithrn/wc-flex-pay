<?php
/**
 * Admin payment history template
 *
 * @package WC_Flex_Pay\Admin\Templates
 */

if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('manage_woocommerce')) {
    return;
}

$payment_manager = new \WCFP\Payment();
?>

<div class="wcfp-payment-history">
    <h3><?php esc_html_e('Payment History', 'wc-flex-pay'); ?></h3>

    <?php if (!empty($payments['summary'])) : ?>
        <div class="wcfp-payment-summary">
            <h4><?php esc_html_e('Payment Summary', 'wc-flex-pay'); ?></h4>
            <div class="wcfp-summary-grid">
                <div class="wcfp-summary-item">
                    <span class="wcfp-label"><?php esc_html_e('Total Amount:', 'wc-flex-pay'); ?></span>
                    <span class="wcfp-value"><?php echo wc_price($payments['summary']['total_amount']); ?></span>
                </div>
                <div class="wcfp-summary-item">
                    <span class="wcfp-label"><?php esc_html_e('Paid Amount:', 'wc-flex-pay'); ?></span>
                    <span class="wcfp-value wcfp-success"><?php echo wc_price($payments['summary']['paid_amount']); ?></span>
                </div>
                <div class="wcfp-summary-item">
                    <span class="wcfp-label"><?php esc_html_e('Remaining Amount:', 'wc-flex-pay'); ?></span>
                    <span class="wcfp-value wcfp-warning"><?php echo wc_price($payments['summary']['total_amount'] - $payments['summary']['paid_amount']); ?></span>
                </div>
                <div class="wcfp-summary-item">
                    <span class="wcfp-label"><?php esc_html_e('Installments:', 'wc-flex-pay'); ?></span>
                    <span class="wcfp-value">
                        <?php
                        printf(
                            /* translators: %1$d: completed installments, %2$d: total installments */
                            esc_html__('%1$d of %2$d completed', 'wc-flex-pay'),
                            $payments['summary']['paid_installments'],
                            $payments['summary']['total_installments']
                        );
                        ?>
                    </span>
                </div>
                <?php if (!empty($payments['summary']['next_due_date'])) : ?>
                    <div class="wcfp-summary-item">
                        <span class="wcfp-label"><?php esc_html_e('Next Due Date:', 'wc-flex-pay'); ?></span>
                        <span class="wcfp-value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($payments['summary']['next_due_date']))); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($payments['installments'])) : ?>
        <table class="wcfp-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Installment', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Due Date', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Status', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Payment Date', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Transaction ID', 'wc-flex-pay'); ?></th>
                    <th><?php esc_html_e('Actions', 'wc-flex-pay'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments['installments'] as $installment) : ?>
                    <tr class="wcfp-payment-row <?php echo esc_attr($installment['status']); ?>">
                        <td data-label="<?php esc_attr_e('Installment', 'wc-flex-pay'); ?>">
                            <?php 
                            printf(
                                /* translators: %d: installment number */
                                esc_html__('#%d', 'wc-flex-pay'),
                                $installment['number']
                            ); 
                            ?>
                        </td>
                        <td data-label="<?php esc_attr_e('Amount', 'wc-flex-pay'); ?>">
                            <?php echo wc_price($installment['amount']); ?>
                        </td>
                        <td data-label="<?php esc_attr_e('Due Date', 'wc-flex-pay'); ?>">
                            <?php 
                            echo esc_html(date_i18n(get_option('date_format'), strtotime($installment['due_date'])));
                            if ($installment['status'] === 'overdue') {
                                $days_overdue = floor((current_time('timestamp') - strtotime($installment['due_date'])) / (60 * 60 * 24));
                                echo ' <span class="wcfp-overdue">(' . sprintf(
                                    /* translators: %d: number of days */
                                    _n('%d day overdue', '%d days overdue', $days_overdue, 'wc-flex-pay'),
                                    $days_overdue
                                ) . ')</span>';
                            }
                            ?>
                        </td>
                        <td data-label="<?php esc_attr_e('Status', 'wc-flex-pay'); ?>">
                            <span class="wcfp-status-badge status-<?php echo esc_attr($installment['status']); ?>">
                                <?php echo esc_html(ucfirst($installment['status'])); ?>
                            </span>
                        </td>
                        <td data-label="<?php esc_attr_e('Payment Date', 'wc-flex-pay'); ?>">
                            <?php 
                            echo !empty($installment['payment_date']) 
                                ? esc_html(date_i18n(get_option('date_format'), strtotime($installment['payment_date'])))
                                : '—';
                            ?>
                        </td>
                        <td data-label="<?php esc_attr_e('Transaction ID', 'wc-flex-pay'); ?>">
                            <?php echo !empty($installment['transaction_id']) ? esc_html($installment['transaction_id']) : '—'; ?>
                        </td>
                        <td data-label="<?php esc_attr_e('Actions', 'wc-flex-pay'); ?>">
                            <?php if ($installment['status'] === 'pending' || $installment['status'] === 'failed' || $installment['status'] === 'overdue') : ?>
                                <?php if (empty($installment['payment_suborder'])) : ?>
                                    <a href="<?php echo esc_url($payment_manager->get_payment_url($order->get_id(), $installment['number'])); ?>" 
                                       class="button" 
                                       target="_blank">
                                        <?php esc_html_e('Payment Link', 'wc-flex-pay'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($installment['payment_suborder'])) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($installment['payment_suborder'])); ?>" 
                                   class="button">
                                    <?php 
                                    printf(
                                        /* translators: %s: order number */
                                        esc_html__('Order #%s', 'wc-flex-pay'),
                                        esc_html($installment['payment_suborder'])
                                    );
                                    ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
