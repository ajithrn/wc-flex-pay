<?php
/**
 * Order meta box template
 *
 * @package WC_Flex_Pay\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if this is a sub-order
$is_sub_order = !empty($order->get_meta('_wcfp_parent_order'));
$parent_order_id = $order->get_meta('_wcfp_parent_order');
$installment_number = $order->get_meta('_wcfp_installment_number');

if ($is_sub_order) {
    $parent_order = wc_get_order($parent_order_id);
    ?>
    <div class="wcfp-sub-order-info">
        <p>
            <strong><?php esc_html_e('Parent Order:', 'wc-flex-pay'); ?></strong>
            <a href="<?php echo esc_url(get_edit_post_link($parent_order_id)); ?>">
                #<?php echo esc_html($parent_order->get_order_number()); ?>
            </a>
        </p>
        <p>
            <strong><?php esc_html_e('Installment:', 'wc-flex-pay'); ?></strong>
            <?php echo esc_html($installment_number); ?>
        </p>
    </div>
    <?php
}

// Display payment schedule using shared template
$is_admin = true;
include WCFP_PLUGIN_DIR . 'templates/order/payment-schedule.php';
