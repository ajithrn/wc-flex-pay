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
     * Check if required tables exist
     *
     * @return bool
     */
    private function tables_exist() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'wcfp_order_payments',
            $wpdb->prefix . 'wcfp_payment_logs'
        );

        foreach ($required_tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process scheduled payment
     *
     * @param float    $amount_to_charge
     * @param WC_Order $order
     */
    public function process_scheduled_payment($amount_to_charge, $order) {
        if (!$this->tables_exist()) {
            $this->log_error('Database tables not found');
            return;
        }

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

            // Start transaction
            $wpdb->query('START TRANSACTION');

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

                $wpdb->query('COMMIT');
            } else {
                throw new \Exception($result['messages'] ?? __('Payment processing failed.', 'wc-flex-pay'));
            }
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
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
        if (!$this->tables_exist()) {
            $this->log_error('Database tables not found');
            return;
        }

        global $wpdb;

        $grace_period = absint(get_option('wcfp_overdue_grace_period', 3));
        $overdue_date = date('Y-m-d H:i:s', strtotime("-{$grace_period} days"));

        $overdue_payments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcfp_order_payments 
                WHERE status = %s 
                AND due_date < %s 
                AND 1=%d",
                'pending',
                $overdue_date,
                1
            ),
            ARRAY_A
        ) ?: array();

        foreach ($overdue_payments as $payment) {
            try {
                // Start transaction
                $wpdb->query('START TRANSACTION');

                $this->update_payment_status($payment['id'], 'overdue');
                $this->log_payment($payment['id'], __('Payment marked as overdue.', 'wc-flex-pay'), 'warning');
                
                $order = wc_get_order($payment['order_id']);
                if ($order) {
                    $order->add_order_note(
                        sprintf(
                            __('Flex Pay payment of %s is overdue (due date: %s).', 'wc-flex-pay'),
                            wc_price($payment['amount']),
                            date_i18n(get_option('date_format'), strtotime($payment['due_date']))
                        )
                    );

                    // Send overdue notification
                    do_action('wcfp_payment_overdue', $payment['id'], $order);
                }

                $wpdb->query('COMMIT');
            } catch (\Exception $e) {
                $wpdb->query('ROLLBACK');
                $this->log_error($e->getMessage(), array(
                    'payment_id' => $payment['id'],
                    'order_id' => $payment['order_id']
                ));
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
        if (!$this->tables_exist()) {
            throw new \Exception(__('Database tables not found.', 'wc-flex-pay'));
        }

        global $wpdb;

        $payment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcfp_order_payments WHERE id = %d AND 1=%d",
                $payment_id,
                1
            ),
            ARRAY_A
        );

        if (!$payment) {
            throw new \Exception(__('Payment not found.', 'wc-flex-pay'));
        }

        $order = wc_get_order($payment['order_id']);
        if (!$order) {
            throw new \Exception(__('Order not found.', 'wc-flex-pay'));
        }

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

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

                $wpdb->query('COMMIT');
            } else {
                throw new \Exception($result['messages'] ?? __('Payment processing failed.', 'wc-flex-pay'));
            }
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
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
        if (!$this->tables_exist()) {
            return false;
        }

        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wcfp_order_payments 
                WHERE order_id = %d 
                AND status = %s 
                AND 1=%d
                ORDER BY due_date ASC 
                LIMIT 1",
                $order_id,
                'pending',
                1
            )
        );
    }

    /**
     * Check if all payments are completed
     *
     * @param int $order_id
     * @return bool
     */
    public function are_all_payments_completed($order_id) {
        if (!$this->tables_exist()) {
            return false;
        }

        global $wpdb;

        $pending_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wcfp_order_payments 
                WHERE order_id = %d 
                AND status != %s 
                AND 1=%d",
                $order_id,
                'completed',
                1
            )
        );

        return $pending_count === '0';
    }

    /**
     * Update payment status
     *
     * @param int    $payment_id
     * @param string $status
     * @throws \Exception If status update fails
     */
    public function update_payment_status($payment_id, $status) {
        if (!$this->tables_exist()) {
            throw new \Exception(__('Database tables not found.', 'wc-flex-pay'));
        }

        if (!array_key_exists($status, $this->statuses)) {
            throw new \Exception(__('Invalid payment status.', 'wc-flex-pay'));
        }

        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'wcfp_order_payments',
            array('status' => $status),
            array('id' => $payment_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            throw new \Exception($wpdb->last_error ?: __('Failed to update payment status.', 'wc-flex-pay'));
        }

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
        if (!$this->tables_exist()) {
            throw new \Exception(__('Database tables not found.', 'wc-flex-pay'));
        }

        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'wcfp_payment_logs',
            array(
                'payment_id' => $payment_id,
                'type'       => $type,
                'message'    => $message,
            ),
            array('%d', '%s', '%s')
        );

        if ($result === false) {
            throw new \Exception($wpdb->last_error ?: __('Failed to log payment.', 'wc-flex-pay'));
        }
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
        if (!$this->tables_exist()) {
            return array();
        }

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcfp_payment_logs 
                WHERE payment_id = %d 
                AND 1=%d
                ORDER BY created_at DESC",
                $payment_id,
                1
            ),
            ARRAY_A
        ) ?: array();
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
        if (!$this->tables_exist()) {
            return array();
        }

        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcfp_order_payments 
                WHERE order_id = %d 
                AND 1=%d
                ORDER BY due_date ASC",
                $order_id,
                1
            ),
            ARRAY_A
        ) ?: array();
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
