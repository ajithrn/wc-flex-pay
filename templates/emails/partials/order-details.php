<?php
/**
 * Order details partial template
 *
 * @package WC_Flex_Pay\Templates\Emails
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Variables available in this template:
 * 
 * @var WC_Order $order Order object
 * @var array $payment_data Payment data array
 */
?>

<div class="wcfp-order-details">
    <table class="wcfp-summary-table">
        <tr>
            <th><?php esc_html_e('Order:', 'wc-flex-pay'); ?></th>
            <td class="amount">
                <a href="<?php echo esc_url($order->get_view_order_url()); ?>">
                    <?php echo sprintf(esc_html__('#%s', 'wc-flex-pay'), $order->get_order_number()); ?>
                </a>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e('Product:', 'wc-flex-pay'); ?></th>
            <td class="amount">
                <?php 
                foreach ($order->get_items() as $item) {
                    if ('yes' === $item->get_meta('_wcfp_enabled')) {
                        echo esc_html($item->get_name());
                        if ($item->get_variation_id()) {
                            echo ' - ' . esc_html(wc_get_formatted_variation($item->get_product(), true));
                        }
                        break;
                    }
                }
                ?>
            </td>
        </tr>
        <?php if (!empty($payment_data['sub_order_id'])) : 
            $sub_order = wc_get_order($payment_data['sub_order_id']);
            if ($sub_order) : ?>
                <tr>
                    <th><?php esc_html_e('Sub Order:', 'wc-flex-pay'); ?></th>
                    <td class="amount">
                        <a href="<?php echo esc_url($sub_order->get_view_order_url()); ?>">
                            <?php echo sprintf(esc_html__('#%s', 'wc-flex-pay'), $sub_order->get_order_number()); ?>
                        </a>
                    </td>
                </tr>
            <?php endif; 
        endif; ?>
        <?php if (!empty($payment_data['payment_method'])) : ?>
            <tr>
                <th><?php esc_html_e('Payment Method:', 'wc-flex-pay'); ?></th>
                <td class="amount"><?php echo esc_html($payment_data['payment_method']); ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($payment_data['expires_at'])) : ?>
        <div class="wcfp-text-small wcfp-text-center" style="margin-top: 15px;">
            <?php
            printf(
                /* translators: %s: expiry date */
                esc_html__('Please note that this payment link will expire on %s.', 'wc-flex-pay'),
                date_i18n(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    strtotime($payment_data['expires_at'])
                )
            );
            ?>
        </div>
    <?php endif; ?>
</div>
