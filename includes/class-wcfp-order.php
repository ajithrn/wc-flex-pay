<?php
/**
 * Order related functions and actions
 *
 * @package WC_Flex_Pay
 */

namespace WCFP;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order Class
 */
class Order {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Order Processing
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_flex_pay_data_to_order_items'), 10, 4);
        add_action('woocommerce_checkout_order_processed', array($this, 'process_flex_pay_order'), 10, 3);
        add_filter('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // Order Display
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_payment_schedule'));
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_admin_payment_schedule'));
        
        // Custom Order Status
        add_action('init', array($this, 'register_custom_order_statuses'));
        add_filter('wc_order_statuses', array($this, 'add_custom_order_statuses'));
        add_filter('woocommerce_valid_order_statuses_for_payment', array($this, 'add_custom_order_status_for_payment'));
        
        // Order Actions
        add_action('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_process_flex_payment', array($this, 'process_flex_payment'));

        // Payment Status Check
        add_action('woocommerce_order_status_changed', array($this, 'check_payment_completion'), 10, 3);
    }

    /**
     * Add Flex Pay data to order items
     */
    public function add_flex_pay_data_to_order_items($item, $cart_item_key, $values, $order) {
        if (!isset($values['wcfp_payment_type'])) {
            return;
        }

        $product = $item->get_product();
        if (!$product) {
            return;
        }

        $wcfp_product = new Product();
        if (!$wcfp_product->is_flex_pay_enabled($product)) {
            return;
        }

        $schedules = $wcfp_product->get_payment_schedules($product->get_id());
        if (empty($schedules)) {
            return;
        }

        $item->add_meta_data('_wcfp_enabled', 'yes');
        $item->add_meta_data('_wcfp_payment_type', $values['wcfp_payment_type']);
        $item->add_meta_data('_wcfp_schedules', $schedules);
        $item->add_meta_data('_wcfp_total', $wcfp_product->calculate_total_price($schedules));
    }

    /**
     * Process Flex Pay order
     */
    public function process_flex_pay_order($order_id, $posted_data, $order) {
        $has_installments = false;
        $future_payments = array();

        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $has_installments = true;
                $schedules = $item->get_meta('_wcfp_schedules');
                if (!empty($schedules)) {
                    // Skip first payment as it's paid at checkout
                    array_shift($schedules);
                    foreach ($schedules as $schedule) {
                        $date = date_i18n(get_option('date_format'), strtotime($schedule['due_date']));
                        if (!isset($future_payments[$date])) {
                            $future_payments[$date] = 0;
                        }
                        $future_payments[$date] += $schedule['amount'] * $item->get_quantity();
                    }
                }
            }
        }

        if ($has_installments) {
            // Add future payments note
            $note = __('Future Payments Schedule:', 'wc-flex-pay') . "\n";
            foreach ($future_payments as $date => $amount) {
                $note .= sprintf(
                    '%s: %s' . "\n",
                    $date,
                    wc_price($amount)
                );
            }
            $order->add_order_note($note);

            // Set custom order status
            $order->update_status('flex-pay-pending', __('Order has pending Flex Pay installments.', 'wc-flex-pay'));
        }
    }

    /**
     * Handle order status change
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        if ($new_status === 'flex-pay-completed') {
            // Handle any additional tasks when flex pay is completed
            do_action('wcfp_order_flex_pay_completed', $order_id, $old_status, $new_status, $order);
        }
    }

    /**
     * Display payment schedule on order details
     */
    public function display_payment_schedule($order) {
        $has_installments = false;
        $future_payments = array();

        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $has_installments = true;
                $schedules = $item->get_meta('_wcfp_schedules');
                if (!empty($schedules)) {
                    // Skip first payment as it's paid at checkout
                    array_shift($schedules);
                    foreach ($schedules as $schedule) {
                        $date = date_i18n(get_option('date_format'), strtotime($schedule['due_date']));
                        if (!isset($future_payments[$date])) {
                            $future_payments[$date] = 0;
                        }
                        $future_payments[$date] += $schedule['amount'] * $item->get_quantity();
                    }
                }
            }
        }

        if ($has_installments) {
            ?>
            <h2><?php esc_html_e('Future Payments', 'wc-flex-pay'); ?></h2>
            <table class="woocommerce-table wcfp-payment-schedule">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Due Date', 'wc-flex-pay'); ?></th>
                        <th><?php esc_html_e('Amount', 'wc-flex-pay'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($future_payments as $date => $amount) : ?>
                        <tr>
                            <td><?php echo esc_html($date); ?></td>
                            <td><?php echo wc_price($amount); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="wcfp-payment-notice">
                <?php esc_html_e('These payments will be automatically processed on their scheduled dates using your payment method.', 'wc-flex-pay'); ?>
            </p>
            <?php
        }
    }

    /**
     * Display admin payment schedule
     */
    public function display_admin_payment_schedule($order) {
        $this->display_payment_schedule($order);
    }

    /**
     * Register custom order statuses
     */
    public function register_custom_order_statuses() {
        register_post_status('wc-flex-pay-pending', array(
            'label' => _x('Flex Pay Pending', 'Order status', 'wc-flex-pay'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Flex Pay Pending <span class="count">(%s)</span>', 'Flex Pay Pending <span class="count">(%s)</span>')
        ));

        register_post_status('wc-flex-pay-partial', array(
            'label' => _x('Flex Pay Partial', 'Order status', 'wc-flex-pay'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Flex Pay Partial <span class="count">(%s)</span>', 'Flex Pay Partial <span class="count">(%s)</span>')
        ));

        register_post_status('wc-flex-pay-overdue', array(
            'label' => _x('Flex Pay Overdue', 'Order status', 'wc-flex-pay'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Flex Pay Overdue <span class="count">(%s)</span>', 'Flex Pay Overdue <span class="count">(%s)</span>')
        ));

        register_post_status('wc-flex-pay-completed', array(
            'label' => _x('Flex Pay Completed', 'Order status', 'wc-flex-pay'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Flex Pay Completed <span class="count">(%s)</span>', 'Flex Pay Completed <span class="count">(%s)</span>')
        ));

        register_post_status('wc-flex-pay-failed', array(
            'label' => _x('Flex Pay Failed', 'Order status', 'wc-flex-pay'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Flex Pay Failed <span class="count">(%s)</span>', 'Flex Pay Failed <span class="count">(%s)</span>')
        ));
    }

    /**
     * Add custom order statuses
     */
    public function add_custom_order_statuses($order_statuses) {
        $new_statuses = array(
            'wc-flex-pay-pending' => _x('Flex Pay Pending', 'Order status', 'wc-flex-pay'),
            'wc-flex-pay-partial' => _x('Flex Pay Partial', 'Order status', 'wc-flex-pay'),
            'wc-flex-pay-overdue' => _x('Flex Pay Overdue', 'Order status', 'wc-flex-pay'),
            'wc-flex-pay-completed' => _x('Flex Pay Completed', 'Order status', 'wc-flex-pay'),
            'wc-flex-pay-failed' => _x('Flex Pay Failed', 'Order status', 'wc-flex-pay')
        );

        return array_merge($order_statuses, $new_statuses);
    }

    /**
     * Add custom order status for payment
     */
    public function add_custom_order_status_for_payment($statuses) {
        $new_statuses = array(
            'flex-pay-pending',
            'flex-pay-partial',
            'flex-pay-overdue'
        );
        return array_merge($statuses, $new_statuses);
    }

    /**
     * Add order actions
     */
    public function add_order_actions($actions) {
        global $theorder;

        if (!$theorder) {
            return $actions;
        }

        $has_pending = false;
        foreach ($theorder->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $schedules = $item->get_meta('_wcfp_schedules');
                if (!empty($schedules)) {
                    array_shift($schedules); // Skip first payment
                    if (!empty($schedules)) {
                        $has_pending = true;
                        break;
                    }
                }
            }
        }

        if ($has_pending) {
            $actions['process_flex_payment'] = __('Process Flex Pay Payment', 'wc-flex-pay');
        }

        return $actions;
    }

    /**
     * Process flex payment action
     */
    public function process_flex_payment($order) {
        // Process next pending payment
        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $schedules = $item->get_meta('_wcfp_schedules');
                if (!empty($schedules)) {
                    array_shift($schedules); // Skip first payment
                    if (!empty($schedules)) {
                        $next_payment = reset($schedules);
                        $amount = $next_payment['amount'] * $item->get_quantity();
                        
                        // Process payment using order's payment method
                        try {
                            // Add payment note
                            $order->add_order_note(sprintf(
                                __('Processed Flex Pay payment of %s', 'wc-flex-pay'),
                                wc_price($amount)
                            ));

                            // Update schedules
                            array_shift($schedules);
                            $item->update_meta_data('_wcfp_schedules', $schedules);
                            $item->save();

                            // Check if all payments are completed
                            if (empty($schedules)) {
                                $this->check_payment_completion($order->get_id(), '', '');
                            }
                        } catch (\Exception $e) {
                            $order->add_order_note(sprintf(
                                __('Failed to process Flex Pay payment: %s', 'wc-flex-pay'),
                                $e->getMessage()
                            ));
                        }
                        break;
                    }
                }
            }
        }
    }

    /**
     * Check payment completion
     */
    public function check_payment_completion($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $all_completed = true;
        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $schedules = $item->get_meta('_wcfp_schedules');
                if (!empty($schedules)) {
                    array_shift($schedules); // Skip first payment
                    if (!empty($schedules)) {
                        $all_completed = false;
                        break;
                    }
                }
            }
        }

        if ($all_completed) {
            $order->update_status('flex-pay-completed', __('All Flex Pay installments completed.', 'wc-flex-pay'));
        }
    }
}
