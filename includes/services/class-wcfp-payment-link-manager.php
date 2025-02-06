<?php
/**
 * Payment Link Manager Class
 *
 * @package WC_Flex_Pay\Services
 */

namespace WCFP\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Link Manager Class
 * 
 * Handles payment link generation and management
 */
class Payment_Link_Manager {
    /**
     * Instance of this class
     *
     * @var Payment_Link_Manager
     */
    private static $instance = null;

    /**
     * Get class instance
     *
     * @return Payment_Link_Manager
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate payment link
     *
     * @param WC_Order $order Order object
     * @param int      $payment_id Payment ID
     * @param array    $payment Payment data
     * @param bool     $is_overdue Whether the payment is overdue
     * @return array Link data
     */
    public function generate_payment_link($order, $payment_id, $payment, $is_overdue = false) {
        // Generate unique token
        $token = wp_generate_password(32, false);
        
        // Calculate expiry based on due date and grace period
        $grace_period = absint(get_option('wcfp_overdue_grace_period', 3));
        $extended_period = absint(get_option('wcfp_extended_grace_period', 7));
        $current_date = current_time('timestamp');
        $due_date = strtotime($payment['due_date']);

        if ($is_overdue) {
            // Extended expiry for overdue payments
            $expiry = strtotime("+{$extended_period} days", $current_date);
        } else {
            // For upcoming payments, link is valid until grace period after due date
            $expiry = strtotime("+{$grace_period} days", $due_date);
        }

        // Create link data
        $link_data = array(
            'token' => $token,
            'order_id' => $order->get_id(),
            'payment_id' => $payment_id,
            'amount' => $payment['amount'],
            'expires_at' => date('Y-m-d H:i:s', $expiry),
            'url' => add_query_arg(array(
                'wcfp-pay' => $token,
                'order' => $order->get_id(),
                'payment' => $payment_id
            ), wc_get_checkout_url())
        );

        // Store link data
        update_post_meta($order->get_id(), '_wcfp_payment_link_' . $payment_id, $link_data);

        return $link_data;
    }

    /**
     * Validate payment link
     *
     * @param int    $order_id Order ID
     * @param int    $payment_id Payment ID
     * @param string $token Token to validate
     * @return bool Whether link is valid
     */
    public function validate_payment_link($order_id, $payment_id, $token) {
        $link_data = get_post_meta($order_id, '_wcfp_payment_link_' . $payment_id, true);
        if (empty($link_data)) {
            return false;
        }

        // Check token
        if ($link_data['token'] !== $token) {
            return false;
        }

        // Check expiry
        if (!empty($link_data['expires_at']) && strtotime($link_data['expires_at']) <= current_time('timestamp')) {
            return false;
        }

        return true;
    }

    /**
     * Get payment link data
     *
     * @param int $order_id Order ID
     * @param int $payment_id Payment ID
     * @return array|false Link data or false if not found
     */
    public function get_payment_link_data($order_id, $payment_id) {
        $link_data = get_post_meta($order_id, '_wcfp_payment_link_' . $payment_id, true);
        return !empty($link_data) ? $link_data : false;
    }

    /**
     * Delete payment link
     *
     * @param int $order_id Order ID
     * @param int $payment_id Payment ID
     * @return bool Whether link was deleted
     */
    public function delete_payment_link($order_id, $payment_id) {
        return delete_post_meta($order_id, '_wcfp_payment_link_' . $payment_id);
    }
}
