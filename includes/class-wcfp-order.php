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
     * Flag to prevent recursive status updates
     *
     * @var bool
     */
    private static $updating_status = false;

    /**
     * Order note icons
     *
     * @var array
     */
    private $note_icons = array(
        'payment' => '💰',
        'email' => '📧',
        'order' => '📦',
        'system' => 'ℹ️',
        'warning' => '⚠️',
        'error' => '❌',
        'success' => '✅'
    );

    /**
     * Sub-order status map
     *
     * @var array
     */
    private $sub_order_status_map = array(
        'parent' => array(
            'pending' => 'pending',
            'processing' => 'flex-pay-partial',
            'completed' => 'completed',
            'failed' => 'failed',
            'overdue' => 'on-hold'
        ),
        'sub' => array(
            'pending' => 'pending',
            'processing' => 'processing',
            'completed' => 'completed',
            'failed' => 'failed',
            'cancelled' => 'cancelled'
        )
    );

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
        
        // Sub-order Handling
        add_action('woocommerce_order_status_changed', array($this, 'sync_sub_order_status'), 10, 4);
        add_action('woocommerce_payment_complete', array($this, 'handle_sub_order_payment'), 10);
        add_filter('woocommerce_order_number', array($this, 'modify_sub_order_number'), 10, 2);
    }

    /**
     * Add Flex Pay data to order items
     */
    public function add_flex_pay_data_to_order_items($item, $cart_item_key, $values, $order) {
        if (!isset($values['wcfp_payment_type']) || !isset($values['wcfp_payments'])) {
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

        $payments = $values['wcfp_payments'];
        if (empty($payments) || empty($payments['installments'])) {
            return;
        }

        // Store payment data
        $item->add_meta_data('_wcfp_enabled', 'yes');
        $item->add_meta_data('_wcfp_payment_type', $values['wcfp_payment_type']);
        $item->add_meta_data('_wcfp_payments', $payments);
        $item->add_meta_data('_wcfp_initial_payment', $values['wcfp_initial_payment']);
        $item->add_meta_data('_wcfp_total_price', $values['wcfp_total_price']);

        // Add payment status tracking
        if ($values['wcfp_payment_type'] === 'installment') {
            $current_date = current_time('Y-m-d');
            $payment_status = array();
            
            foreach ($payments['installments'] as $installment) {
                $status = strtotime($installment['due_date']) <= strtotime($current_date) ? 'completed' : 'pending';
                $payment_status[] = array(
                    'number' => $installment['number'],
                    'amount' => $installment['amount'],
                    'due_date' => $installment['due_date'],
                    'status' => $status,
                    'payment_date' => $status === 'completed' ? $current_date : null,
                    'transaction_id' => null
                );
            }
            
            $item->add_meta_data('_wcfp_payment_status', $payment_status);
        }
    }

    /**
     * Process Flex Pay order
     */
    public function process_flex_pay_order($order_id, $posted_data, $order) {
        $has_installments = false;
        $total_pending = 0;
        $future_payments = array();

        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $has_installments = true;
                $payments = $item->get_meta('_wcfp_payments');
                $payment_status = $item->get_meta('_wcfp_payment_status');
                $quantity = $item->get_quantity();

                if (!empty($payment_status)) {
                    foreach ($payment_status as $status) {
                        if ($status['status'] === 'pending') {
                            $date = date_i18n(get_option('date_format'), strtotime($status['due_date']));
                            
                            if (!isset($future_payments[$date])) {
                                $future_payments[$date] = array(
                                    'amount' => 0,
                                    'installments' => array()
                                );
                            }
                            
                            $amount = $status['amount'] * $quantity;
                            $future_payments[$date]['amount'] += $amount;
                            $total_pending += $amount;
                            
                            $future_payments[$date]['installments'][] = array(
                                'number' => $status['number'],
                                'product_name' => $item->get_name(),
                                'amount' => $amount
                            );
                        }
                    }
                }
            }
        }

        if ($has_installments) {
            // Add future payments note
            $note = sprintf(
                /* translators: %s: total pending amount */
                __('Future Payments Schedule (Total Pending: %s):', 'wc-flex-pay'),
                wc_price($total_pending)
            ) . "\n\n";

            ksort($future_payments);
            foreach ($future_payments as $date => $payment) {
                $note .= sprintf(
                    /* translators: %1$s: date, %2$s: amount */
                    __('%1$s - Total: %2$s', 'wc-flex-pay') . "\n",
                    $date,
                    wc_price($payment['amount'])
                );

                foreach ($payment['installments'] as $installment) {
                    $note .= sprintf(
                        /* translators: 1: product name, 2: installment number, 3: amount */
                        __('  • %1$s (Installment %2$d) - %3$s', 'wc-flex-pay') . "\n",
                        $installment['product_name'],
                        $installment['number'],
                        wc_price($installment['amount'])
                    );
                }
                $note .= "\n";
            }

            // Add payment instructions
            $note .= __('Payment Instructions:', 'wc-flex-pay') . "\n";
            $note .= __('• You will receive payment links for each installment via email', 'wc-flex-pay') . "\n";
            $note .= __('• Each payment must be completed by its due date', 'wc-flex-pay') . "\n";
            $note .= __('• Payment links can also be found in your account dashboard', 'wc-flex-pay');

            $order->add_order_note($note);

            // Set order status to pending
            $order->update_status('pending', __('Order has pending Flex Pay installments.', 'wc-flex-pay'));
        }
    }

    /**
     * Handle order status change
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        // Check if this is a flex pay order that was just completed
        if ($new_status === 'completed') {
            $has_flex_pay = false;
            foreach ($order->get_items() as $item) {
                if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                    $has_flex_pay = true;
                    break;
                }
            }
            
            if ($has_flex_pay) {
                // Handle any additional tasks when flex pay is completed
                do_action('wcfp_order_flex_pay_completed', $order_id, $old_status, $new_status, $order);
            }
        }
    }

    /**
     * Get order payments data
     *
     * @param WC_Order $order Order object
     * @return array Payment data including installments, totals, etc.
     */
    public function get_order_payments($order) {
        $has_installments = false;
        $total_pending = 0;
        $future_payments = array();
        $completed_payments = array();
        $all_installments = array();

        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $has_installments = true;
                $payment_status = $item->get_meta('_wcfp_payment_status');
                $quantity = $item->get_quantity();

                if (!empty($payment_status)) {
                    foreach ($payment_status as $status) {
                        $amount = $status['amount'] * $quantity;
                        $installment_data = array(
                            'number' => $status['number'],
                            'product_name' => $item->get_name(),
                            'amount' => $amount,
                            'due_date' => $status['due_date'],
                            'status' => $status['status']
                        );

                        if ($status['status'] === 'pending') {
                            $total_pending += $amount;
                            $all_installments[] = $installment_data;
                        } else {
                            $installment_data['payment_date'] = $status['payment_date'];
                            $installment_data['transaction_id'] = $status['transaction_id'];
                            $completed_payments[] = $installment_data;
                        }
                    }
                }
            }
        }

        // Sort all pending installments by number
        usort($all_installments, function($a, $b) {
            return $a['number'] - $b['number'];
        });

        // Sort completed payments by number
        usort($completed_payments, function($a, $b) {
            return $a['number'] - $b['number'];
        });

        // Group pending installments by date while maintaining number order
        foreach ($all_installments as $installment) {
            $date = date_i18n(get_option('date_format'), strtotime($installment['due_date']));
            
            if (!isset($future_payments[$date])) {
                $future_payments[$date] = array(
                    'amount' => 0,
                    'installments' => array()
                );
            }
            
            $future_payments[$date]['amount'] += $installment['amount'];
            $future_payments[$date]['installments'][] = $installment;
        }

        // Sort dates for display
        ksort($future_payments);

        return array(
            'has_installments' => $has_installments,
            'total_pending' => $total_pending,
            'future_payments' => $future_payments,
            'completed_payments' => $completed_payments
        );
    }

    /**
     * Display payment schedule on order details
     */
    public function display_payment_schedule($order) {
        $is_admin = false;
        include WCFP_PLUGIN_DIR . 'templates/order/payment-schedule.php';
    }

    /**
     * Display admin payment schedule
     */
    public function display_admin_payment_schedule($order) {
        $is_admin = true;
        include WCFP_PLUGIN_DIR . 'templates/order/payment-schedule.php';
    }

    /**
     * Register custom order statuses
     */
    public function register_custom_order_statuses() {
        register_post_status('wc-flex-pay-partial', array(
            'label' => _x('Flex Pay Partial', 'Order status', 'wc-flex-pay'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Flex Pay Partial <span class="count">(%s)</span>', 'Flex Pay Partial <span class="count">(%s)</span>')
        ));
    }

    /**
     * Add custom order statuses
     */
    public function add_custom_order_statuses($order_statuses) {
        $new_statuses = array(
            'wc-flex-pay-partial' => _x('Flex Pay Partial', 'Order status', 'wc-flex-pay')
        );

        return array_merge($order_statuses, $new_statuses);
    }

    /**
     * Add custom order status for payment
     */
    public function add_custom_order_status_for_payment($statuses) {
        $new_statuses = array(
            'flex-pay-partial'
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
        $next_payment = null;

        foreach ($theorder->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $payment_status = $item->get_meta('_wcfp_payment_status');
                if (!empty($payment_status)) {
                    foreach ($payment_status as $status) {
                        if ($status['status'] === 'pending') {
                            $has_pending = true;
                            if (!$next_payment || strtotime($status['due_date']) < strtotime($next_payment['due_date'])) {
                                $next_payment = $status;
                            }
                        }
                    }
                }
            }
        }

        if ($has_pending && $next_payment) {
            $actions['process_flex_payment'] = sprintf(
                /* translators: 1: installment number, 2: formatted date */
                __('Process Flex Pay Payment (Installment %1$d - Due: %2$s)', 'wc-flex-pay'),
                $next_payment['number'],
                date_i18n(get_option('date_format'), strtotime($next_payment['due_date']))
            );
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
                $payment_status = $item->get_meta('_wcfp_payment_status');
                if (!empty($payment_status)) {
                    foreach ($payment_status as $index => $status) {
                        if ($status['status'] === 'pending') {
                            $amount = $status['amount'] * $item->get_quantity();
                            
                            // Process payment using order's payment method
                            try {
                                // Update payment status
                                $payment_status[$index]['status'] = 'completed';
                                $payment_status[$index]['payment_date'] = current_time('Y-m-d');
                                $payment_status[$index]['transaction_id'] = uniqid('wcfp_'); // Replace with actual transaction ID
                                
                                $item->update_meta_data('_wcfp_payment_status', $payment_status);
                                $item->save();

                                // Add payment note
                                $order->add_order_note(sprintf(
                                    /* translators: 1: installment number, 2: amount */
                                    __('Processed Flex Pay payment for installment %1$d: %2$s', 'wc-flex-pay'),
                                    $status['number'],
                                    wc_price($amount)
                                ));

                                // Check if all payments are completed
                                $this->check_payment_completion($order->get_id(), '', '');
                                break;
                            } catch (\Exception $e) {
                                $order->add_order_note(sprintf(
                                    /* translators: 1: installment number, 2: error message */
                                    __('Failed to process Flex Pay payment for installment %1$d: %2$s', 'wc-flex-pay'),
                                    $status['number'],
                                    $e->getMessage()
                                ));
                            }
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Add order note with icon
     *
     * @param WC_Order $order Order object
     * @param string   $message Note message
     * @param string   $type Note type (payment, email, order, system, warning, error, success)
     * @param bool     $is_customer_note Whether this is a note for the customer
     * @return int Note ID
     */
    public function add_order_note_with_icon($order, $message, $type = 'system', $is_customer_note = false) {
        $icon = isset($this->note_icons[$type]) ? $this->note_icons[$type] : $this->note_icons['system'];
        return $order->add_order_note(
            sprintf('%s %s', $icon, $message),
            $is_customer_note
        );
    }

    /**
     * Sync sub-order status with parent order
     *
     * @param int      $order_id Order ID
     * @param string   $old_status Old status
     * @param string   $new_status New status
     * @param WC_Order $order Order object
     */
    public function sync_sub_order_status($order_id, $old_status, $new_status, $order) {
        // Check if this is a sub-order
        $parent_order_id = $order->get_meta('_wcfp_parent_order');
        if ($parent_order_id) {
            $parent_order = wc_get_order($parent_order_id);
            if (!$parent_order) {
                return;
            }

            // Map sub-order status to parent order status
            $parent_status = $this->get_parent_order_status($new_status);
            if ($parent_status) {
                $parent_order->update_status(
                    $parent_status,
                    sprintf(
                        /* translators: 1: sub-order number, 2: status */
                        __('Sub-order #%1$s status changed to %2$s', 'wc-flex-pay'),
                        $order->get_order_number(),
                        wc_get_order_status_name($new_status)
                    )
                );
            }
        }
    }

    /**
     * Handle sub-order payment completion
     *
     * @param int $order_id Order ID
     */
    public function handle_sub_order_payment($order_id) {
        if (self::$updating_status) {
            return;
        }

        self::$updating_status = true;

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }

            // Check if this is a sub-order
            $parent_order_id = $order->get_meta('_wcfp_parent_order');
            $installment_number = $order->get_meta('_wcfp_installment_number');
            if (!$parent_order_id || !$installment_number) {
                return;
            }

            $parent_order = wc_get_order($parent_order_id);
            if (!$parent_order) {
                return;
            }

            // Update sub-order status
            $order->update_status('completed', __('Payment completed successfully.', 'wc-flex-pay'));

            // Get parent order payments data
            $payments = get_post_meta($parent_order_id, '_wcfp_payments', true) ?: array();
            
            if (!empty($payments['installments'][$installment_number - 1])) {
                // Update installment data
                $payments['installments'][$installment_number - 1]['status'] = 'completed';
                $payments['installments'][$installment_number - 1]['payment_date'] = current_time('Y-m-d');
                $payments['installments'][$installment_number - 1]['transaction_id'] = $order->get_transaction_id();
                $payments['installments'][$installment_number - 1]['sub_order_id'] = $order_id;

                // Update payment summary
                $total_installments = count($payments['installments']);
                $paid_installments = 0;
                $total_amount = 0;
                $paid_amount = 0;
                $next_due_date = null;

                foreach ($payments['installments'] as $installment) {
                    $total_amount += $installment['amount'];
                    
                    if ($installment['status'] === 'completed') {
                        $paid_installments++;
                        $paid_amount += $installment['amount'];
                    } elseif ($installment['status'] === 'pending' && (!$next_due_date || strtotime($installment['due_date']) < strtotime($next_due_date))) {
                        $next_due_date = $installment['due_date'];
                    }
                }

                $payments['summary'] = array(
                    'total_installments' => $total_installments,
                    'paid_installments' => $paid_installments,
                    'total_amount' => $total_amount,
                    'paid_amount' => $paid_amount,
                    'next_due_date' => $next_due_date
                );

                // Update parent order meta
                update_post_meta($parent_order_id, '_wcfp_payments', $payments);

                // Update parent order items meta
                foreach ($parent_order->get_items() as $item) {
                    if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                        $payment_status = $item->get_meta('_wcfp_payment_status');
                        if (!empty($payment_status[$installment_number - 1])) {
                            $payment_status[$installment_number - 1]['status'] = 'completed';
                            $payment_status[$installment_number - 1]['payment_date'] = current_time('Y-m-d');
                            $payment_status[$installment_number - 1]['transaction_id'] = $order->get_transaction_id();
                            $item->update_meta_data('_wcfp_payment_status', $payment_status);
                            $item->save();
                            break;
                        }
                    }
                }

                // Add note to parent order
                $this->add_order_note_with_icon(
                    $parent_order,
                    sprintf(
                        /* translators: 1: installment number, 2: sub-order number, 3: amount */
                        __('Installment %1$d completed via sub-order #%2$s - Amount: %3$s', 'wc-flex-pay'),
                        $installment_number,
                        $order->get_order_number(),
                        wc_price($payments['installments'][$installment_number - 1]['amount'])
                    ),
                    'payment'
                );

                // Update payment link status if exists
                $links = get_post_meta($parent_order_id, '_wcfp_payment_links', true) ?: array();
                $link_key = "{$installment_number}";
                if (!empty($links[$link_key])) {
                    $links[$link_key]['status'] = 'completed';
                    update_post_meta($parent_order_id, '_wcfp_payment_links', $links);
                }

                // Check if all payments are completed
                $this->check_payment_completion($parent_order_id, '', '');
            }

        } finally {
            self::$updating_status = false;
        }
    }

    /**
     * Modify sub-order number display
     *
     * @param string   $order_number Original order number
     * @param WC_Order $order Order object
     * @return string Modified order number
     */
    public function modify_sub_order_number($order_number, $order) {
        $parent_order_id = $order->get_meta('_wcfp_parent_order');
        $installment_number = $order->get_meta('_wcfp_installment_number');
        
        if ($parent_order_id && $installment_number) {
            return sprintf(
                /* translators: 1: parent order number, 2: installment number */
                __('%1$s-I%2$d', 'wc-flex-pay'),
                $order_number,
                $installment_number
            );
        }

        return $order_number;
    }

    /**
     * Get parent order status based on sub-order status
     *
     * @param string $sub_status Sub-order status
     * @return string|null Parent order status or null if no mapping exists
     */
    private function get_parent_order_status($sub_status) {
        foreach ($this->sub_order_status_map['parent'] as $parent_status => $mapped_status) {
            if ($this->sub_order_status_map['sub'][$parent_status] === $sub_status) {
                return $mapped_status;
            }
        }
        return null;
    }

    /**
     * Check payment completion
     */
    public function check_payment_completion($order_id, $old_status, $new_status) {
        if (self::$updating_status) {
            return;
        }

        self::$updating_status = true;

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }

        $all_completed = true;
        $total_installments = 0;
        $completed_installments = 0;

        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $payment_status = $item->get_meta('_wcfp_payment_status');
                if (!empty($payment_status)) {
                    foreach ($payment_status as $status) {
                        $total_installments++;
                        if ($status['status'] === 'completed') {
                            $completed_installments++;
                        } else {
                            $all_completed = false;
                        }
                    }
                }
            }
        }

        if ($all_completed) {
            $order->update_status('completed', __('All Flex Pay installments completed.', 'wc-flex-pay'));
        } elseif ($completed_installments > 0) {
            $order->update_status('flex-pay-partial', sprintf(
                /* translators: 1: completed installments, 2: total installments */
                __('Flex Pay partially completed (%1$d of %2$d installments)', 'wc-flex-pay'),
                $completed_installments,
                $total_installments
            ));
        } else {
            // If no payments completed, ensure it's in pending status
            $order->update_status('pending', __('Order has pending Flex Pay installments.', 'wc-flex-pay'));
        }

        } finally {
            self::$updating_status = false;
        }
    }
}
