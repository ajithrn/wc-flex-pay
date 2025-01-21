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
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
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
            if (empty($payments)) continue;

            foreach ($payments as $payment_id => $payment) {
                if ($payment['status'] === 'pending' && strtotime($payment['due_date']) < strtotime($overdue_date)) {
                    try {
                        $this->update_payment_status($payment_id, 'overdue');
                        $this->log_payment($payment_id, __('Payment marked as overdue.', 'wc-flex-pay'), 'warning');
                        
                        $order->add_order_note(
                            sprintf(
                                __('Flex Pay payment of %s is overdue (due date: %s).', 'wc-flex-pay'),
                                wc_price($payment['amount']),
                                date_i18n(get_option('date_format'), strtotime($payment['due_date']))
                            )
                        );

                        // Send overdue notification
                        do_action('wcfp_payment_overdue', $payment_id, $order);
                    } catch (\Exception $e) {
                        $this->log_error($e->getMessage(), array(
                            'payment_id' => $payment_id,
                            'order_id' => $order->get_id()
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
                $this->log_payment($payment_id, __('Payment processed successfully.', 'wc-flex-pay'));
                
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
            $order->add_order_note(sprintf(__('Flex Pay payment failed: %s', 'wc-flex-pay'), $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Get next pending payment for order
     *
     * @param int $order_id
     * @return int|false
     */
    public function get_next_pending_payment($order_id) {
        $payments = get_post_meta($order_id, '_wcfp_payments', true);
        if (empty($payments)) {
            return false;
        }

        $pending_payments = array_filter($payments, function($payment) {
            return $payment['status'] === 'pending';
        });

        if (empty($pending_payments)) {
            return false;
        }

        // Sort by due date
        uasort($pending_payments, function($a, $b) {
            return strtotime($a['due_date']) - strtotime($b['due_date']);
        });

        // Return first payment ID
        reset($pending_payments);
        return key($pending_payments);
    }

    /**
     * Check if all payments are completed
     *
     * @param int $order_id
     * @return bool
     */
    public function are_all_payments_completed($order_id) {
        $payments = get_post_meta($order_id, '_wcfp_payments', true);
        if (empty($payments)) {
            return false;
        }

        foreach ($payments as $payment) {
            if ($payment['status'] !== 'completed') {
                return false;
            }
        }

        return true;
    }

    /**
     * Update payment status
     *
     * @param int    $payment_id
     * @param string $status
     * @throws \Exception If status update fails
     */
    public function update_payment_status($payment_id, $status) {
        if (!array_key_exists($status, $this->statuses)) {
            throw new \Exception(__('Invalid payment status.', 'wc-flex-pay'));
        }

        $payments = $this->get_order_payments_by_payment_id($payment_id);
        if (empty($payments) || !isset($payments[$payment_id])) {
            throw new \Exception(__('Payment not found.', 'wc-flex-pay'));
        }

        $order_id = $payments[$payment_id]['order_id'];
        $all_payments = get_post_meta($order_id, '_wcfp_payments', true);
        
        if (!is_array($all_payments)) {
            $all_payments = array();
        }

        $all_payments[$payment_id]['status'] = $status;
        update_post_meta($order_id, '_wcfp_payments', $all_payments);

        do_action('wcfp_payment_status_' . $status, $payment_id);
        do_action('wcfp_payment_status_changed', $payment_id, $status);
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
