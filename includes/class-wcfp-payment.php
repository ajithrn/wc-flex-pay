<?php
/**
 * Payment related functions and actions
 *
 * @package WC_Flex_Pay
 */

namespace WCFP;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Class
 */
class Payment {

    /**
     * Payment statuses
     *
     * @var array
     */
    private $statuses = array(
        'pending'    => 'Pending',
        'processing' => 'Processing',
        'completed'  => 'Completed',
        'failed'     => 'Failed',
        'cancelled'  => 'Cancelled',
        'overdue'    => 'Overdue',
    );

    /**
     * Event types for logging
     *
     * @var array
     */
    private $event_types = array(
        'payment'    => '💰',
        'email'      => '📧',
        'order'      => '📦',
        'system'     => 'ℹ️',
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        add_action('init', array($this, 'init_payment_url_handler'));
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Payment Processing
        add_action('woocommerce_scheduled_subscription_payment', array($this, 'process_scheduled_payment'), 10, 2);
        
        // Payment Status Management
        add_action('init', array($this, 'check_overdue_payments'));
        add_action('wcfp_process_payment', array($this, 'process_payment'));
        
        // Admin
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_payment_history'));
    }

    /**
     * Initialize payment URL handler
     */
    public function init_payment_url_handler() {
        if (!empty($_GET['wcfp-pay']) && !empty($_GET['installment'])) {
            add_action('template_redirect', array($this, 'handle_payment_url'));
        }
    }

    /**
     * Handle payment URL
     */
    public function handle_payment_url() {
        $order_id = absint($_GET['wcfp-pay']);
        $installment_number = absint($_GET['installment']);

        try {
            // Verify order exists
            $parent_order = wc_get_order($order_id);
            if (!$parent_order) {
                throw new \Exception(__('Invalid order.', 'wc-flex-pay'));
            }

            // Verify customer
            if ($parent_order->get_customer_id() !== get_current_user_id()) {
                throw new \Exception(__('Unauthorized access.', 'wc-flex-pay'));
            }

            // Get installment details
            $payments = $this->get_order_payments($order_id);
            if (empty($payments['installments'][$installment_number - 1])) {
                throw new \Exception(__('Invalid installment.', 'wc-flex-pay'));
            }

            $installment = $payments['installments'][$installment_number - 1];
            if ($installment['status'] === 'completed') {
                throw new \Exception(__('This installment has already been paid.', 'wc-flex-pay'));
            }

            // Create sub-order
            $sub_order = $this->create_sub_order($parent_order, $installment);
            
            // Log sub-order creation
            $this->log_event(
                $order_id,
                sprintf(
                    __('Created sub-order #%s for installment %d', 'wc-flex-pay'),
                    $sub_order->get_order_number(),
                    $installment_number
                ),
                'order'
            );

            // Redirect to sub-order checkout
            wp_redirect($sub_order->get_checkout_payment_url());
            exit;

        } catch (\Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            wp_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }
    }

    /**
     * Create sub-order for installment payment
     *
     * @param WC_Order $parent_order Parent order
     * @param array    $installment  Installment data
     * @return WC_Order
     */
    private function create_sub_order($parent_order, $installment) {
        $sub_order = wc_create_order(array(
            'status' => 'pending',
            'customer_id' => $parent_order->get_customer_id(),
            'created_via' => 'wcfp'
        ));

        // Copy parent order billing and shipping info
        $sub_order->set_address($parent_order->get_address('billing'), 'billing');
        $sub_order->set_address($parent_order->get_address('shipping'), 'shipping');

        // Add product
        $product = $parent_order->get_items()[0]->get_product();
        $sub_order->add_product($product, 1, array(
            'subtotal' => $installment['amount'],
            'total' => $installment['amount']
        ));

        // Add reference meta
        $sub_order->add_meta_data('_wcfp_parent_order', $parent_order->get_id());
        $sub_order->add_meta_data('_wcfp_installment_number', $installment['number']);
        
        $sub_order->calculate_totals();
        $sub_order->save();

        return $sub_order;
    }

    /**
     * Get payment URL for installment
     *
     * @param int $order_id
     * @param int $installment_number
     * @return string
     */
    public function get_payment_url($order_id, $installment_number) {
        return add_query_arg(array(
            'wcfp-pay' => $order_id,
            'installment' => $installment_number
        ), wc_get_checkout_url());
    }

    /**
     * Log event with icon
     *
     * @param int    $order_id Order ID
     * @param string $message  Event message
     * @param string $type     Event type (payment, email, order, system)
     */
    public function log_event($order_id, $message, $type = 'system') {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $icon = isset($this->event_types[$type]) ? $this->event_types[$type] : $this->event_types['system'];
        $timestamp = current_time('mysql');
        
        $order->add_order_note(sprintf(
            '%s [%s] %s',
            $icon,
            $timestamp,
            $message
        ));
    }

    /**
     * Get payment meta key
     *
     * @param int $payment_id
     * @return string
     */
    private function get_payment_meta_key($payment_id) {
        return sprintf('_wcfp_payment_%d', $payment_id);
    }

    /**
     * Get next payment ID for order
     *
     * @param int $order_id
     * @return int
     */
    private function get_next_payment_id($order_id) {
        $payments = get_post_meta($order_id, '_wcfp_payments', true);
        return !empty($payments) ? max(array_keys($payments)) + 1 : 1;
    }

    /**
     * Process scheduled payment
     *
     * @param float    $amount_to_charge
     * @param WC_Order $order
     */
    public function process_scheduled_payment($amount_to_charge, $order) {
        $payment_id = $this->get_next_pending_payment($order->get_id());
        if (!$payment_id) {
            return;
        }

        try {
            $payment_method = $order->get_payment_method();
            if (!$payment_method) {
                throw new \Exception(__('No payment method found.', 'wc-flex-pay'));
            }

            $gateway = WC()->payment_gateways()->payment_gateways()[$payment_method];
            if (!$gateway) {
                throw new \Exception(__('Payment gateway not found.', 'wc-flex-pay'));
            }

            if (!$gateway->supports('subscriptions')) {
                throw new \Exception(__('Payment gateway does not support scheduled payments.', 'wc-flex-pay'));
            }

            // Update payment status to processing
            $this->update_payment_status($payment_id, 'processing');

            // Process payment through gateway
            $result = $gateway->process_payment($order->get_id());

            if ($result['result'] === 'success') {
                $this->update_payment_status($payment_id, 'completed');
                $this->log_payment($payment_id, __('Scheduled payment processed successfully.', 'wc-flex-pay'));
                
                // Check if all payments are completed
                if ($this->are_all_payments_completed($order->get_id())) {
                    $order->update_status('completed', __('All Flex Pay payments completed.', 'wc-flex-pay'));
                }
            } else {
                throw new \Exception($result['messages'] ?? __('Payment processing failed.', 'wc-flex-pay'));
            }
        } catch (\Exception $e) {
            $this->update_payment_status($payment_id, 'failed');
            $this->log_payment($payment_id, sprintf(__('Payment failed: %s', 'wc-flex-pay'), $e->getMessage()), 'error');
            $order->add_order_note(sprintf(__('Flex Pay scheduled payment failed: %s', 'wc-flex-pay'), $e->getMessage()));
            $this->log_error($e->getMessage(), array(
                'payment_id' => $payment_id,
                'order_id' => $order->get_id(),
                'amount' => $amount_to_charge
            ));
        }
    }

    /**
     * Check for overdue payments
     */
    public function check_overdue_payments() {
        $grace_period = absint(get_option('wcfp_overdue_grace_period', 3));
        $overdue_date = date('Y-m-d H:i:s', strtotime("-{$grace_period} days"));

        // Get all orders with flex pay payments
        $orders = wc_get_orders(array(
            'meta_key' => '_wcfp_payments',
            'limit' => -1,
        ));

        foreach ($orders as $order) {
            $payments = get_post_meta($order->get_id(), '_wcfp_payments', true);
            if (empty($payments) || empty($payments['installments'])) continue;

            foreach ($payments['installments'] as $index => $installment) {
                if ($installment['status'] === 'pending' && strtotime($installment['due_date']) < strtotime($overdue_date)) {
                    try {
                        // Update installment status
                        $payments['installments'][$index]['status'] = 'overdue';
                        update_post_meta($order->get_id(), '_wcfp_payments', $payments);

                        // Log event
                        $this->log_event(
                            $order->get_id(),
                            sprintf(
                                __('Installment %d payment of %s is overdue (due date: %s).', 'wc-flex-pay'),
                                $installment['number'],
                                wc_price($installment['amount']),
                                date_i18n(get_option('date_format'), strtotime($installment['due_date']))
                            ),
                            'payment'
                        );

                        // Send overdue notification
                        do_action('wcfp_payment_overdue', $order->get_id(), $installment['number']);

                        // Send email notification
                        Emails::send_payment_overdue($order->get_id(), $installment['number']);
                        $this->log_event(
                            $order->get_id(),
                            sprintf(
                                __('Sent overdue payment notification for installment %d.', 'wc-flex-pay'),
                                $installment['number']
                            ),
                            'email'
                        );

                    } catch (\Exception $e) {
                        $this->log_error($e->getMessage(), array(
                            'order_id' => $order->get_id(),
                            'installment' => $installment['number']
                        ));
                    }
                }
            }
        }
    }

    /**
     * Process payment
     *
     * @param int $payment_id
     * @throws \Exception If payment processing fails
     */
    public function process_payment($payment_id) {
        $payments = $this->get_order_payments_by_payment_id($payment_id);
        if (empty($payments)) {
            throw new \Exception(__('Payment not found.', 'wc-flex-pay'));
        }

        $payment = $payments[$payment_id];
        $order = wc_get_order($payment['order_id']);
        if (!$order) {
            throw new \Exception(__('Order not found.', 'wc-flex-pay'));
        }

        try {
            // Check if this is a sub-order payment
            $parent_order_id = $order->get_meta('_wcfp_parent_order');
            $installment_number = $order->get_meta('_wcfp_installment_number');
            
            if ($parent_order_id && $installment_number) {
                return $this->process_sub_order_payment($order, $parent_order_id, $installment_number);
            }

            // Update payment status to processing
            $this->update_payment_status($payment_id, 'processing');
            
            // Get payment gateway
            $payment_method = $order->get_payment_method();
            if (!$payment_method) {
                throw new \Exception(__('No payment method found.', 'wc-flex-pay'));
            }

            $gateway = WC()->payment_gateways()->payment_gateways()[$payment_method];
            if (!$gateway) {
                throw new \Exception(__('Payment gateway not found.', 'wc-flex-pay'));
            }

            // Process payment
            $result = $gateway->process_payment($order->get_id());

            if ($result['result'] === 'success') {
                $this->update_payment_status($payment_id, 'completed');
                $this->log_event($order->get_id(), __('Payment processed successfully.', 'wc-flex-pay'), 'payment');
                
                // Store transaction details
                $this->update_payment_details($order->get_id(), $payment_id, array(
                    'transaction_id' => $order->get_transaction_id(),
                    'payment_method' => $payment_method,
                    'payment_date' => current_time('mysql')
                ));
                
                // Check if all payments are completed
                if ($this->are_all_payments_completed($order->get_id())) {
                    $order->update_status('completed', __('All Flex Pay payments completed.', 'wc-flex-pay'));
                    $this->log_event($order->get_id(), __('All installments completed.', 'wc-flex-pay'), 'system');
                }
            } else {
                throw new \Exception($result['messages'] ?? __('Payment processing failed.', 'wc-flex-pay'));
            }

            return $result;
        } catch (\Exception $e) {
            $this->update_payment_status($payment_id, 'failed');
            $this->log_event($order->get_id(), sprintf(__('Payment failed: %s', 'wc-flex-pay'), $e->getMessage()), 'payment');
            throw $e;
        }
    }

    /**
     * Process sub-order payment
     *
     * @param WC_Order $sub_order         Sub order
     * @param int      $parent_order_id   Parent order ID
     * @param int      $installment_number Installment number
     * @return array
     * @throws \Exception If payment processing fails
     */
    private function process_sub_order_payment($sub_order, $parent_order_id, $installment_number) {
        try {
            // Get payment gateway
            $payment_method = $sub_order->get_payment_method();
            if (!$payment_method) {
                throw new \Exception(__('No payment method found.', 'wc-flex-pay'));
            }

            $gateway = WC()->payment_gateways()->payment_gateways()[$payment_method];
            if (!$gateway) {
                throw new \Exception(__('Payment gateway not found.', 'wc-flex-pay'));
            }

            // Process payment
            $result = $gateway->process_payment($sub_order->get_id());

            if ($result['result'] === 'success') {
                // Update parent order installment
                $payments = $this->get_order_payments($parent_order_id);
                $payments['installments'][$installment_number - 1]['status'] = 'completed';
                $payments['installments'][$installment_number - 1]['payment_date'] = current_time('mysql');
                $payments['installments'][$installment_number - 1]['payment_method'] = $payment_method;
                $payments['installments'][$installment_number - 1]['transaction_id'] = $sub_order->get_transaction_id();
                $payments['installments'][$installment_number - 1]['payment_suborder'] = $sub_order->get_id();

                // Update summary
                $payments['summary']['paid_installments']++;
                $payments['summary']['paid_amount'] += $sub_order->get_total();
                
                // Find next due date
                $next_installment = null;
                foreach ($payments['installments'] as $installment) {
                    if ($installment['status'] === 'pending') {
                        $next_installment = $installment;
                        break;
                    }
                }
                $payments['summary']['next_due_date'] = $next_installment ? $next_installment['due_date'] : null;

                update_post_meta($parent_order_id, '_wcfp_payments', $payments);

                // Log events
                $this->log_event(
                    $parent_order_id,
                    sprintf(
                        __('Installment %d payment completed via sub-order #%s', 'wc-flex-pay'),
                        $installment_number,
                        $sub_order->get_order_number()
                    ),
                    'payment'
                );

                // Check if all payments are completed
                if ($this->are_all_payments_completed($parent_order_id)) {
                    $parent_order = wc_get_order($parent_order_id);
                    $parent_order->update_status('completed', __('All Flex Pay payments completed.', 'wc-flex-pay'));
                    $this->log_event($parent_order_id, __('All installments completed.', 'wc-flex-pay'), 'system');
                }
            }

            return $result;
        } catch (\Exception $e) {
            $this->log_event($parent_order_id, sprintf(__('Payment failed for installment %d: %s', 'wc-flex-pay'), $installment_number, $e->getMessage()), 'payment');
            throw $e;
        }
    }

    /**
     * Update payment details
     *
     * @param int   $order_id Order ID
     * @param int   $payment_id Payment ID
     * @param array $details Payment details
     */
    private function update_payment_details($order_id, $payment_id, $details) {
        $payments = $this->get_order_payments($order_id);
        
        if (!empty($payments[$payment_id])) {
            $payments[$payment_id] = array_merge($payments[$payment_id], $details);
            update_post_meta($order_id, '_wcfp_payments', $payments);
        }
    }

    /**
     * Get next pending payment for order
     *
     * @param int $order_id
     * @return array|false Returns array with installment number and details, or false if none found
     */
    public function get_next_pending_payment($order_id) {
        $payments = get_post_meta($order_id, '_wcfp_payments', true);
        if (empty($payments) || empty($payments['installments'])) {
            return false;
        }

        $pending_installments = array_filter($payments['installments'], function($installment) {
            return $installment['status'] === 'pending';
        });

        if (empty($pending_installments)) {
            return false;
        }

        // Sort by due date
        usort($pending_installments, function($a, $b) {
            return strtotime($a['due_date']) - strtotime($b['due_date']);
        });

        // Return first pending installment
        return reset($pending_installments);
    }

    /**
     * Check if all payments are completed
     *
     * @param int $order_id
     * @return bool
     */
    public function are_all_payments_completed($order_id) {
        $payments = get_post_meta($order_id, '_wcfp_payments', true);
        if (empty($payments) || empty($payments['installments'])) {
            return false;
        }

        foreach ($payments['installments'] as $installment) {
            if ($installment['status'] !== 'completed') {
                return false;
            }
        }

        return true;
    }

    /**
     * Update payment status
     *
     * @param int    $order_id Order ID
     * @param int    $installment_number Installment number
     * @param string $status New status
     * @throws \Exception If status update fails
     */
    public function update_payment_status($order_id, $installment_number, $status) {
        if (!array_key_exists($status, $this->statuses)) {
            throw new \Exception(__('Invalid payment status.', 'wc-flex-pay'));
        }

        $payments = $this->get_order_payments($order_id);
        if (empty($payments) || empty($payments['installments'][$installment_number - 1])) {
            throw new \Exception(__('Installment not found.', 'wc-flex-pay'));
        }

        // Update installment status
        $payments['installments'][$installment_number - 1]['status'] = $status;
        
        // Update summary
        $this->update_payment_summary($order_id, $payments);

        // Save changes
        update_post_meta($order_id, '_wcfp_payments', $payments);

        // Trigger actions
        do_action('wcfp_payment_status_' . $status, $order_id, $installment_number);
        do_action('wcfp_payment_status_changed', $order_id, $installment_number, $status);

        // Log event
        $this->log_event(
            $order_id,
            sprintf(
                __('Installment %d status changed to %s', 'wc-flex-pay'),
                $installment_number,
                $this->statuses[$status]
            ),
            'system'
        );
    }

    /**
     * Update payment summary
     *
     * @param int   $order_id Order ID
     * @param array $payments Payments data
     */
    private function update_payment_summary($order_id, &$payments) {
        if (empty($payments['installments'])) {
            return;
        }

        // Calculate summary
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

        // Update summary
        $payments['summary'] = array(
            'total_installments' => $total_installments,
            'paid_installments' => $paid_installments,
            'total_amount' => $total_amount,
            'paid_amount' => $paid_amount,
            'next_due_date' => $next_due_date
        );
    }

    /**
     * Log payment
     *
     * @param int    $payment_id
     * @param string $message
     * @param string $type
     * @throws \Exception If logging fails
     */
    public function log_payment($payment_id, $message, $type = 'info') {
        $payments = $this->get_order_payments_by_payment_id($payment_id);
        if (empty($payments) || !isset($payments[$payment_id])) {
            throw new \Exception(__('Payment not found.', 'wc-flex-pay'));
        }

        $order_id = $payments[$payment_id]['order_id'];
        $logs = get_post_meta($order_id, '_wcfp_payment_logs', true);
        
        if (!is_array($logs)) {
            $logs = array();
        }

        $logs[] = array(
            'payment_id' => $payment_id,
            'type' => $type,
            'message' => $message,
            'created_at' => current_time('mysql')
        );

        update_post_meta($order_id, '_wcfp_payment_logs', $logs);
    }

    /**
     * Log error
     *
     * @param string $message
     * @param array  $context
     */
    private function log_error($message, $context = array()) {
        if (defined('WCFP_DEBUG') && WCFP_DEBUG) {
            error_log(sprintf(
                '[WC Flex Pay] %s | Context: %s',
                $message,
                json_encode($context)
            ));
        }
    }

    /**
     * Get payment history
     *
     * @param int $payment_id
     * @return array
     */
    public function get_payment_history($payment_id) {
        $payments = $this->get_order_payments_by_payment_id($payment_id);
        if (empty($payments) || !isset($payments[$payment_id])) {
            return array();
        }

        $order_id = $payments[$payment_id]['order_id'];
        $logs = get_post_meta($order_id, '_wcfp_payment_logs', true);
        
        if (!is_array($logs)) {
            return array();
        }

        // Filter logs for specific payment ID and sort by created_at
        $payment_logs = array_filter($logs, function($log) use ($payment_id) {
            return $log['payment_id'] === $payment_id;
        });

        usort($payment_logs, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $payment_logs;
    }

    /**
     * Display payment history in admin
     *
     * @param WC_Order $order
     */
    public function display_payment_history($order) {
        $payments = $this->get_order_payments($order->get_id());
        if (empty($payments)) {
            return;
        }

        include WCFP_PLUGIN_DIR . 'templates/admin/payment-history.php';
    }

    /**
     * Get order payments
     *
     * @param int $order_id
     * @return array
     */
    public function get_order_payments($order_id) {
        $payments = get_post_meta($order_id, '_wcfp_payments', true);
        return !empty($payments) ? $payments : array();
    }

    /**
     * Get order payments by payment ID
     *
     * @param int $payment_id
     * @return array
     */
    private function get_order_payments_by_payment_id($payment_id) {
        $orders = wc_get_orders(array(
            'meta_key' => '_wcfp_payments',
            'limit' => -1,
        ));

        foreach ($orders as $order) {
            $payments = get_post_meta($order->get_id(), '_wcfp_payments', true);
            if (!empty($payments) && isset($payments[$payment_id])) {
                return array($payment_id => array_merge(
                    $payments[$payment_id],
                    array('order_id' => $order->get_id())
                ));
            }
        }

        return array();
    }

    /**
     * Get available payment statuses
     *
     * @return array
     */
    public function get_payment_statuses() {
        return $this->statuses;
    }
}
