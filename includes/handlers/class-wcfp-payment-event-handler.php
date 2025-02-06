<?php
/**
 * Payment Event Handler Class
 *
 * @package WC_Flex_Pay\Handlers
 */

namespace WCFP\Handlers;

use WCFP\Services\Notification_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Event Handler Class
 * 
 * Handles payment-related events and triggers notifications
 */
class Payment_Event_Handler {
    /**
     * Notification manager instance
     *
     * @var Notification_Manager
     */
    private $notification_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->notification_manager = new Notification_Manager();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Payment Complete
        add_action('woocommerce_payment_complete', array($this, 'on_payment_complete'));
        add_action('wcfp_installment_complete', array($this, 'on_installment_complete'), 10, 2);
        
        // Payment Failed
        add_action('woocommerce_order_status_failed', array($this, 'on_payment_failed'));
        
        // Payment Due/Overdue
        add_action('wcfp_payment_due', array($this, 'on_payment_due'), 10, 2);
        add_action('wcfp_payment_overdue', array($this, 'on_payment_overdue'), 10, 2);
        
        // Order Processing
        add_action('woocommerce_checkout_order_processed', array($this, 'on_order_created'), 10, 3);
        
        // Admin Actions
        add_action('woocommerce_order_action_wcfp_send_payment_reminder', array($this, 'on_manual_payment_reminder'));
        add_action('woocommerce_order_action_wcfp_send_payment_complete', array($this, 'on_manual_payment_complete'));
        add_action('woocommerce_order_action_wcfp_send_payment_overdue', array($this, 'on_manual_payment_overdue'));
        add_action('woocommerce_order_action_wcfp_send_order_details', array($this, 'on_manual_order_details'));
    }

    /**
     * Handle payment complete
     *
     * @param int $order_id Order ID
     */
    public function on_payment_complete($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || !$this->is_flex_pay_order($order)) {
            return;
        }

        $this->notification_manager->handle_payment_complete($order_id, 1);
    }

    /**
     * Handle installment complete
     *
     * @param int $order_id Order ID
     * @param int $installment_number Installment number
     */
    public function on_installment_complete($order_id, $installment_number) {
        $this->notification_manager->handle_payment_complete($order_id, $installment_number);
    }

    /**
     * Handle payment failed
     *
     * @param int $order_id Order ID
     */
    public function on_payment_failed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || !$this->is_flex_pay_order($order)) {
            return;
        }

        // Get current installment number
        $installment_number = $order->get_meta('_wcfp_installment_number') ?: 1;
        $this->notification_manager->handle_payment_failed($order_id, $installment_number);
    }

    /**
     * Handle payment due
     *
     * @param int $order_id Order ID
     * @param int $installment_number Installment number
     */
    public function on_payment_due($order_id, $installment_number) {
        $this->notification_manager->handle_payment_reminder($order_id, $installment_number);
    }

    /**
     * Handle payment overdue
     *
     * @param int $order_id Order ID
     * @param int $installment_number Installment number
     */
    public function on_payment_overdue($order_id, $installment_number) {
        $this->notification_manager->handle_payment_overdue($order_id, $installment_number);
    }

    /**
     * Handle order created
     *
     * @param int   $order_id Order ID
     * @param array $posted_data Posted data
     * @param WC_Order $order Order object
     */
    public function on_order_created($order_id, $posted_data, $order) {
        if (!$this->is_flex_pay_order($order)) {
            return;
        }

        $this->notification_manager->handle_order_details($order_id, 1);
    }

    /**
     * Handle manual payment reminder
     *
     * @param WC_Order $order Order object
     */
    public function on_manual_payment_reminder($order) {
        if (!$this->is_flex_pay_order($order)) {
            return;
        }

        // Get next pending payment
        $next_payment = $this->get_next_pending_payment($order);
        if ($next_payment) {
            $this->notification_manager->handle_payment_reminder(
                $order->get_id(),
                $next_payment['number']
            );
        }
    }

    /**
     * Handle manual payment complete
     *
     * @param WC_Order $order Order object
     */
    public function on_manual_payment_complete($order) {
        if (!$this->is_flex_pay_order($order)) {
            return;
        }

        $installment_number = $order->get_meta('_wcfp_installment_number') ?: 1;
        $this->notification_manager->handle_payment_complete(
            $order->get_id(),
            $installment_number
        );
    }

    /**
     * Handle manual payment overdue
     *
     * @param WC_Order $order Order object
     */
    public function on_manual_payment_overdue($order) {
        if (!$this->is_flex_pay_order($order)) {
            return;
        }

        // Get next pending payment
        $next_payment = $this->get_next_pending_payment($order);
        if ($next_payment) {
            $this->notification_manager->handle_payment_overdue(
                $order->get_id(),
                $next_payment['number']
            );
        }
    }

    /**
     * Handle manual order details
     *
     * @param WC_Order $order Order object
     */
    public function on_manual_order_details($order) {
        if (!$this->is_flex_pay_order($order)) {
            return;
        }

        $installment_number = $order->get_meta('_wcfp_installment_number') ?: 1;
        $this->notification_manager->handle_order_details(
            $order->get_id(),
            $installment_number
        );
    }

    /**
     * Check if order is a Flex Pay order
     *
     * @param WC_Order $order Order object
     * @return bool
     */
    private function is_flex_pay_order($order) {
        if (!$order) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get next pending payment
     *
     * @param WC_Order $order Order object
     * @return array|null Payment data or null if not found
     */
    private function get_next_pending_payment($order) {
        foreach ($order->get_items() as $item) {
            if ('yes' !== $item->get_meta('_wcfp_enabled') || 'installment' !== $item->get_meta('_wcfp_payment_type')) {
                continue;
            }

            $payment_status = $item->get_meta('_wcfp_payment_status');
            if (empty($payment_status)) continue;

            foreach ($payment_status as $status) {
                if ($status['status'] === 'pending') {
                    return $status;
                }
            }
        }

        return null;
    }
}
