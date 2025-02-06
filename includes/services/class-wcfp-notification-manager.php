<?php
/**
 * Notification Manager Class
 *
 * @package WC_Flex_Pay\Services
 */

namespace WCFP\Services;

use WCFP\Services\Email_Manager;
use WCFP\Services\Payment_Link_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Notification Manager Class
 * 
 * Handles notification logic and data preparation
 */
class Notification_Manager {
    /**
     * Email manager instance
     *
     * @var Email_Manager
     */
    private $email_manager;

    /**
     * Payment link manager instance
     *
     * @var Payment_Link_Manager
     */
    private $payment_link_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->email_manager = Email_Manager::instance();
        $this->payment_link_manager = Payment_Link_Manager::instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Schedule reminders
        add_action('init', array($this, 'schedule_reminders'));
        add_action('wcfp_payment_reminder', array($this, 'send_scheduled_reminders'));
    }

    /**
     * Schedule payment reminders
     */
    public function schedule_reminders() {
        if (!wp_next_scheduled('wcfp_payment_reminder')) {
            wp_schedule_event(time(), 'daily', 'wcfp_payment_reminder');
        }
    }

    /**
     * Send scheduled reminders
     */
    public function send_scheduled_reminders() {
        $reminder_days = absint(get_option('wcfp_reminder_days', 3));
        $current_date = current_time('mysql');
        $current_timestamp = strtotime($current_date);

        // Get all orders with flex pay payments
        $orders = wc_get_orders(array(
            'meta_key' => '_wcfp_payments',
            'limit' => -1,
        ));

        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order || !$this->is_flex_pay_order($order)) {
                continue;
            }

            foreach ($order->get_items() as $item) {
                if ('yes' !== $item->get_meta('_wcfp_enabled') || 'installment' !== $item->get_meta('_wcfp_payment_type')) {
                    continue;
                }

                $payment_status = $item->get_meta('_wcfp_payment_status');
                if (empty($payment_status)) continue;

                foreach ($payment_status as $payment) {
                    if ($payment['status'] === 'pending' && strtotime($payment['due_date']) > $current_timestamp) {
                        $days_until_due = floor((strtotime($payment['due_date']) - $current_timestamp) / DAY_IN_SECONDS);
                        
                        if ($days_until_due <= $reminder_days) {
                            $this->handle_payment_reminder($order->get_id(), $payment['number']);
                        }
                    }
                }
            }
        }
    }

    /**
     * Handle payment complete notification
     *
     * @param int $order_id Order ID
     * @param int $installment_number Installment number
     */
    public function handle_payment_complete($order_id, $installment_number) {
        $data = $this->prepare_payment_data($order_id, $installment_number);
        $this->email_manager->send_email(
            Email_Manager::PAYMENT_COMPLETE,
            $order_id,
            $installment_number,
            $data
        );
    }

    /**
     * Handle payment failed notification
     *
     * @param int $order_id Order ID
     * @param int $installment_number Installment number
     */
    public function handle_payment_failed($order_id, $installment_number) {
        $data = $this->prepare_payment_data($order_id, $installment_number);
        $this->email_manager->send_email(
            Email_Manager::PAYMENT_FAILED,
            $order_id,
            $installment_number,
            $data
        );
    }

    /**
     * Handle payment reminder notification
     *
     * @param int $order_id Order ID
     * @param int $installment_number Installment number
     */
    public function handle_payment_reminder($order_id, $installment_number) {
        $data = $this->prepare_payment_data($order_id, $installment_number);
        
        // Get order and payment data
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get payment details
        $payment_data = null;
        foreach ($order->get_items() as $item) {
            $payment_status = $item->get_meta('_wcfp_payment_status');
            if (!empty($payment_status) && isset($payment_status[$installment_number - 1])) {
                $payment_data = $payment_status[$installment_number - 1];
                break;
            }
        }

        if (!$payment_data) {
            return;
        }

        // Generate payment link for reminder
        $link_data = $this->payment_link_manager->generate_payment_link(
            $order,
            $installment_number,
            $payment_data,
            false
        );
        $data['link_data'] = $link_data;

        $this->email_manager->send_email(
            Email_Manager::PAYMENT_REMINDER,
            $order_id,
            $installment_number,
            $data
        );
    }

    /**
     * Handle payment overdue notification
     *
     * @param int $order_id Order ID
     * @param int $installment_number Installment number
     */
    public function handle_payment_overdue($order_id, $installment_number) {
        $data = $this->prepare_payment_data($order_id, $installment_number);
        
        // Get order and payment data
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get payment details
        $payment_data = null;
        foreach ($order->get_items() as $item) {
            $payment_status = $item->get_meta('_wcfp_payment_status');
            if (!empty($payment_status) && isset($payment_status[$installment_number - 1])) {
                $payment_data = $payment_status[$installment_number - 1];
                break;
            }
        }

        if (!$payment_data) {
            return;
        }

        // Generate payment link with extended expiry
        $link_data = $this->payment_link_manager->generate_payment_link(
            $order,
            $installment_number,
            $payment_data,
            true // is_overdue = true for extended expiry
        );
        $data['link_data'] = $link_data;

        $this->email_manager->send_email(
            Email_Manager::PAYMENT_OVERDUE,
            $order_id,
            $installment_number,
            $data
        );
    }

    /**
     * Handle order details notification
     *
     * @param int $order_id Order ID
     * @param int $installment_number Installment number
     */
    public function handle_order_details($order_id, $installment_number) {
        $data = $this->prepare_payment_data($order_id, $installment_number);
        $this->email_manager->send_email(
            Email_Manager::ORDER_DETAILS,
            $order_id,
            $installment_number,
            $data
        );
    }

    /**
     * Handle payment link notification
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $link_data Payment link data
     */
    public function handle_payment_link($order_id, $installment_number, $link_data) {
        $data = $this->prepare_payment_data($order_id, $installment_number);
        $data['link_data'] = $link_data;
        
        $this->email_manager->send_email(
            Email_Manager::PAYMENT_LINK,
            $order_id,
            $installment_number,
            $data
        );
    }

    /**
     * Prepare payment data
     *
     * @param int $order_id Order ID
     * @param int $installment_number Installment number
     * @return array Payment data
     */
    private function prepare_payment_data($order_id, $installment_number) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return [];
        }

        $total_amount = 0;
        $paid_amount = 0;
        $pending_amount = 0;
        $current_installment = null;
        $payment_schedule = [];

        foreach ($order->get_items() as $item) {
            if ('yes' !== $item->get_meta('_wcfp_enabled') || 'installment' !== $item->get_meta('_wcfp_payment_type')) {
                continue;
            }

            $payment_status = $item->get_meta('_wcfp_payment_status');
            if (empty($payment_status)) continue;

            $payment_schedule = $payment_status;
            foreach ($payment_status as $status) {
                $amount = $status['amount'] * $item->get_quantity();
                $total_amount += $amount;

                if ($status['status'] === 'completed') {
                    $paid_amount += $amount;
                } else {
                    $pending_amount += $amount;
                }

                if ($status['number'] === $installment_number) {
                    $current_installment = array_merge(
                        $status,
                        ['number' => $installment_number]
                    );
                }
            }
            break;
        }

        return [
            'total_amount' => $total_amount,
            'paid_amount' => $paid_amount,
            'pending_amount' => $pending_amount,
            'current_installment' => $current_installment,
            'payment_schedule' => $payment_schedule
        ];
    }
}
