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
        'overdue'    => 'On Hold',
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

        // Cart and Checkout
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 10, 2);
        add_action('woocommerce_before_calculate_totals', array($this, 'before_calculate_totals'), 10, 1);

        // Order Display
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_payment_schedule'));
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_admin_payment_schedule'));
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
     * Generate payment link for installment
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $args Optional arguments
     * @return array Link data including URL and expiry
     */
    public function generate_payment_link($order_id, $installment_number, $args = array()) {
        $defaults = array(
            'expires_in' => 72, // Hours until link expires
            'regenerate' => false // Whether to regenerate if link exists
        );
        $args = wp_parse_args($args, $defaults);

        // Get existing link
        $links = get_post_meta($order_id, '_wcfp_payment_links', true) ?: array();
        $link_key = "{$installment_number}";

        // Check if link exists and is still valid
        if (!empty($links[$link_key]) && !$args['regenerate']) {
            if (empty($links[$link_key]['expires_at']) || strtotime($links[$link_key]['expires_at']) > current_time('timestamp')) {
                return $links[$link_key];
            }
        }

        // Get parent order and verify installment
        $parent_order = wc_get_order($order_id);
        if (!$parent_order) {
            throw new \Exception(__('Invalid order.', 'wc-flex-pay'));
        }

        // Get installment data from order items
        $installment = null;
        foreach ($parent_order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $payment_status = $item->get_meta('_wcfp_payment_status');
                if (!empty($payment_status[$installment_number - 1])) {
                    $installment = $payment_status[$installment_number - 1];
                    break;
                }
            }
        }

        if (!$installment) {
            throw new \Exception(__('Invalid installment.', 'wc-flex-pay'));
        }

        if ($installment['status'] === 'completed') {
            throw new \Exception(__('This installment has already been paid.', 'wc-flex-pay'));
        }

        // Create or get existing sub-order
        $sub_order_id = $installment['sub_order_id'] ?? null;
        $sub_order = $sub_order_id ? wc_get_order($sub_order_id) : null;

        if (!$sub_order || $args['regenerate']) {
            // Create new sub-order
            $sub_order = wc_create_order(array(
                'status' => 'pending',
                'customer_id' => $parent_order->get_customer_id(),
                'created_via' => 'wcfp'
            ));

            // Copy parent order billing and shipping info
            $sub_order->set_address($parent_order->get_address('billing'), 'billing');
            $sub_order->set_address($parent_order->get_address('shipping'), 'shipping');

            // Add product
            $items = $parent_order->get_items();
            $item = reset($items);
            if (!$item) {
                throw new \Exception(__('No product found in parent order.', 'wc-flex-pay'));
            }

            $product = $item->get_product();
            if (!$product) {
                throw new \Exception(__('Product not found.', 'wc-flex-pay'));
            }

            $sub_order->add_product($product, 1, array(
                'subtotal' => $installment['amount'],
                'total' => $installment['amount']
            ));

            // Add reference meta
            $sub_order->add_meta_data('_wcfp_parent_order', $parent_order->get_id());
            $sub_order->add_meta_data('_wcfp_installment_number', $installment_number);
            
            $sub_order->calculate_totals();
            $sub_order->save();

            // Update installment with sub-order reference
            $payments['installments'][$installment_number - 1]['sub_order_id'] = $sub_order->get_id();
            update_post_meta($order_id, '_wcfp_payments', $payments);
        }

        // Generate new link
        $token = wp_generate_password(32, false);
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$args['expires_in']} hours"));

        $link_data = array(
            'token' => $token,
            'url' => $sub_order->get_checkout_payment_url(),
            'created_at' => current_time('mysql'),
            'expires_at' => $expires_at,
            'status' => 'active',
            'sub_order_id' => $sub_order->get_id()
        );

        // Save link data
        $links[$link_key] = $link_data;
        update_post_meta($order_id, '_wcfp_payment_links', $links);

        // Log link generation
        $this->log_event(
            $order_id,
            sprintf(
                __('Generated payment link for installment %d (expires: %s) - Sub-order #%s', 'wc-flex-pay'),
                $installment_number,
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($expires_at)),
                $sub_order->get_order_number()
            ),
            'system'
        );

        return $link_data;
    }

    /**
     * Validate payment link
     *
     * @param int    $order_id Order ID
     * @param int    $installment_number Installment number
     * @param string $token Token to validate
     * @return bool Whether link is valid
     */
    public function validate_payment_link($order_id, $installment_number, $token) {
        $links = get_post_meta($order_id, '_wcfp_payment_links', true) ?: array();
        $link_key = "{$installment_number}";

        if (empty($links[$link_key])) {
            return false;
        }

        $link = $links[$link_key];

        // Check token
        if ($link['token'] !== $token) {
            return false;
        }

        // Check expiry
        if (!empty($link['expires_at']) && strtotime($link['expires_at']) <= current_time('timestamp')) {
            // Update link status to expired
            $links[$link_key]['status'] = 'expired';
            update_post_meta($order_id, '_wcfp_payment_links', $links);
            return false;
        }

        // Check if link is active
        if ($link['status'] !== 'active') {
            return false;
        }

        return true;
    }

    /**
     * Handle payment URL
     */
    public function handle_payment_url() {
        $order_id = absint($_GET['wcfp-pay']);
        $installment_number = absint($_GET['installment']);
        $token = sanitize_text_field($_GET['token'] ?? '');

        try {
            // Verify order exists
            $parent_order = wc_get_order($order_id);
            if (!$parent_order) {
                throw new \Exception(__('Invalid order.', 'wc-flex-pay'));
            }

            // Verify customer
            if ($parent_order->get_customer_id() !== get_current_user_id() && !current_user_can('manage_woocommerce')) {
                throw new \Exception(__('Unauthorized access.', 'wc-flex-pay'));
            }

            // Validate payment link if token provided
            if ($token && !$this->validate_payment_link($order_id, $installment_number, $token)) {
                throw new \Exception(__('Invalid or expired payment link.', 'wc-flex-pay'));
            }

            // Get installment details
            $payments = get_post_meta($order_id, '_wcfp_payments', true);
            if (empty($payments['installments'][$installment_number - 1])) {
                throw new \Exception(__('Invalid installment.', 'wc-flex-pay'));
            }

            $installment = $payments['installments'][$installment_number - 1];
            if ($installment['status'] === 'completed') {
                throw new \Exception(__('This installment has already been paid.', 'wc-flex-pay'));
            }

            // Get sub-order
            $sub_order_id = $installment['sub_order_id'] ?? null;
            $sub_order = $sub_order_id ? wc_get_order($sub_order_id) : null;
            if (!$sub_order) {
                throw new \Exception(__('Sub-order not found.', 'wc-flex-pay'));
            }

            // Log event
            $this->log_event(
                $order_id,
                sprintf(
                    __('Payment URL accessed for installment %d (Amount: %s) - Sub-order #%s', 'wc-flex-pay'),
                    $installment_number,
                    wc_price($installment['amount']),
                    $sub_order->get_order_number()
                ),
                'system'
            );

            // Redirect to sub-order payment page
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

        // Get all orders
        $orders = wc_get_orders(array(
            'limit' => -1,
        ));

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ('yes' !== $item->get_meta('_wcfp_enabled') || 'installment' !== $item->get_meta('_wcfp_payment_type')) {
                    continue;
                }

                $payment_status = $item->get_meta('_wcfp_payment_status');
                if (empty($payment_status)) continue;

                foreach ($payment_status as $index => $status) {
                    if ($status['status'] === 'pending' && strtotime($status['due_date']) < strtotime($overdue_date)) {
                        try {
                            // Update order status to on-hold
                            $order->update_status('on-hold', sprintf(
                                __('Installment %d payment of %s is overdue (due date: %s).', 'wc-flex-pay'),
                                $status['number'],
                                wc_price($status['amount']),
                                date_i18n(get_option('date_format'), strtotime($status['due_date']))
                            ));

                            // Update payment status
                            $payment_status[$index]['status'] = 'overdue';
                            $item->update_meta_data('_wcfp_payment_status', $payment_status);
                            $item->save();

                            // Send email notification
                            Emails::send_payment_overdue($order->get_id(), $status['number']);
                            $this->log_event(
                                $order->get_id(),
                                sprintf(
                                    __('Sent overdue payment notification for installment %d.', 'wc-flex-pay'),
                                    $status['number']
                                ),
                                'email'
                            );

                        } catch (\Exception $e) {
                            $this->log_error($e->getMessage(), array(
                                'order_id' => $order->get_id(),
                                'installment' => $status['number']
                            ));
                        }
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
                // Let the Order class handle status updates via woocommerce_payment_complete hook
                $sub_order->payment_complete();
                
                // Log event
                $this->log_event(
                    $parent_order_id,
                    sprintf(
                        __('Payment processed for installment %d via sub-order #%s', 'wc-flex-pay'),
                        $installment_number,
                        $sub_order->get_order_number()
                    ),
                    'payment'
                );
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
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $pending_installments = array();
        foreach ($order->get_items() as $item) {
            if ('yes' !== $item->get_meta('_wcfp_enabled') || 'installment' !== $item->get_meta('_wcfp_payment_type')) {
                continue;
            }

            $payment_status = $item->get_meta('_wcfp_payment_status');
            if (empty($payment_status)) continue;

            foreach ($payment_status as $status) {
                if ($status['status'] === 'pending') {
                    $pending_installments[] = array_merge($status, array(
                        'product_name' => $item->get_name()
                    ));
                }
            }
        }

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
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $has_installments = false;
        foreach ($order->get_items() as $item) {
            if ('yes' !== $item->get_meta('_wcfp_enabled') || 'installment' !== $item->get_meta('_wcfp_payment_type')) {
                continue;
            }

            $payment_status = $item->get_meta('_wcfp_payment_status');
            if (empty($payment_status)) continue;

            $has_installments = true;
            foreach ($payment_status as $status) {
                if ($status['status'] !== 'completed') {
                    return false;
                }
            }
        }

        return $has_installments;
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

        $order = wc_get_order($order_id);
        if (!$order) {
            throw new \Exception(__('Order not found.', 'wc-flex-pay'));
        }

        $installment_found = false;
        foreach ($order->get_items() as $item) {
            if ('yes' !== $item->get_meta('_wcfp_enabled') || 'installment' !== $item->get_meta('_wcfp_payment_type')) {
                continue;
            }

            $payment_status = $item->get_meta('_wcfp_payment_status');
            if (empty($payment_status)) continue;

            foreach ($payment_status as $index => $payment) {
                if ($payment['number'] === $installment_number) {
                    // Update payment status
                    $payment_status[$index]['status'] = $status;
                    $item->update_meta_data('_wcfp_payment_status', $payment_status);
                    $item->save();

                    $installment_found = true;
                    break 2;
                }
            }
        }

        if (!$installment_found) {
            throw new \Exception(__('Installment not found.', 'wc-flex-pay'));
        }

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

    /**
     * Add installment data to cart item
     *
     * @param array $cart_item_data
     * @param int   $product_id
     * @param int   $variation_id
     * @return array
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (isset($_GET['wcfp-pay']) && isset($_GET['installment'])) {
            $order_id = absint($_GET['wcfp-pay']);
            $installment_number = absint($_GET['installment']);
            
            $payments = get_post_meta($order_id, '_wcfp_payments', true);
            if (!empty($payments['installments'][$installment_number - 1])) {
                $installment = $payments['installments'][$installment_number - 1];
                $cart_item_data['wcfp_installment'] = array(
                    'parent_order_id' => $order_id,
                    'installment_number' => $installment_number,
                    'amount' => $installment['amount'],
                    'due_date' => $installment['due_date']
                );
            }
        }
        return $cart_item_data;
    }

    /**
     * Get cart item data from session
     *
     * @param array $cart_item
     * @param array $values
     * @return array
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['wcfp_installment'])) {
            $cart_item['wcfp_installment'] = $values['wcfp_installment'];
            $cart_item['data']->set_price($values['wcfp_installment']['amount']);
        }
        return $cart_item;
    }

    /**
     * Add installment info to cart item display
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function get_item_data($item_data, $cart_item) {
        if (isset($cart_item['wcfp_installment'])) {
            $item_data[] = array(
                'key' => __('Installment', 'wc-flex-pay'),
                'value' => sprintf(
                    /* translators: 1: installment number, 2: due date */
                    __('#%1$d (Due: %2$s)', 'wc-flex-pay'),
                    $cart_item['wcfp_installment']['installment_number'],
                    date_i18n(get_option('date_format'), strtotime($cart_item['wcfp_installment']['due_date']))
                )
            );
        }
        return $item_data;
    }

    /**
     * Update cart item prices before totals calculation
     *
     * @param WC_Cart $cart
     */
    public function before_calculate_totals($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['wcfp_installment'])) {
                $cart_item['data']->set_price($cart_item['wcfp_installment']['amount']);
            }
        }
    }

    /**
     * Display payment schedule on order details
     *
     * @param WC_Order $order Order object
     */
    public function display_payment_schedule($order) {
        $is_admin = false;
        include WCFP_PLUGIN_DIR . 'templates/order/payment-schedule.php';
    }

    /**
     * Display admin payment schedule
     *
     * @param WC_Order $order Order object
     */
    public function display_admin_payment_schedule($order) {
        $is_admin = true;
        include WCFP_PLUGIN_DIR . 'templates/order/payment-schedule.php';
    }
}
