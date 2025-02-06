<?php
/**
 * Order Action Handler Class
 *
 * @package WC_Flex_Pay\Handlers
 */

namespace WCFP\Handlers;

use WCFP\Services\Notification_Manager;
use WCFP\Services\Payment_Link_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order Action Handler Class
 * 
 * Handles order actions and bulk actions
 */
class Order_Action_Handler {
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
        // Order Actions
        add_filter('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_wcfp_send_payment_reminder', array($this, 'process_order_action_payment_reminder'));
        add_action('woocommerce_order_action_wcfp_send_payment_complete', array($this, 'process_order_action_payment_complete'));
        add_action('woocommerce_order_action_wcfp_send_payment_overdue', array($this, 'process_order_action_payment_overdue'));
        add_action('woocommerce_order_action_wcfp_send_order_details', array($this, 'process_order_action_order_details'));
        add_action('woocommerce_order_action_wcfp_send_payment_link', array($this, 'process_order_action_payment_link'));

        // Bulk Actions
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);
        add_action('admin_notices', array($this, 'bulk_action_admin_notice'));
    }

    /**
     * Add order actions
     *
     * @param array $actions Existing actions
     * @return array Modified actions
     */
    public function add_order_actions($actions) {
        global $theorder;

        if (!$theorder || !$this->is_flex_pay_order($theorder)) {
            return $actions;
        }

        $new_actions = array(
            'wcfp_send_payment_reminder' => __('Send Flex Pay Payment Reminder', 'wc-flex-pay'),
            'wcfp_send_payment_complete' => __('Send Flex Pay Payment Complete', 'wc-flex-pay'),
            'wcfp_send_payment_overdue' => __('Send Flex Pay Payment Overdue Notice', 'wc-flex-pay'),
            'wcfp_send_order_details' => __('Send Flex Pay Order Details', 'wc-flex-pay'),
            'wcfp_send_payment_link' => __('Send Flex Pay Payment Link', 'wc-flex-pay')
        );

        return array_merge($actions, $new_actions);
    }

    /**
     * Process payment reminder action
     *
     * @param WC_Order $order Order object
     */
    public function process_order_action_payment_reminder($order) {
        if (!$this->is_flex_pay_order($order)) {
            return;
        }

        $next_payment = $this->get_next_pending_payment($order);
        if ($next_payment) {
            $this->notification_manager->handle_payment_reminder(
                $order->get_id(),
                $next_payment['number']
            );
        }
    }

    /**
     * Process payment complete action
     *
     * @param WC_Order $order Order object
     */
    public function process_order_action_payment_complete($order) {
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
     * Process payment overdue action
     *
     * @param WC_Order $order Order object
     */
    public function process_order_action_payment_overdue($order) {
        if (!$this->is_flex_pay_order($order)) {
            return;
        }

        $next_payment = $this->get_next_pending_payment($order);
        if ($next_payment) {
            $this->notification_manager->handle_payment_overdue(
                $order->get_id(),
                $next_payment['number']
            );
        }
    }

    /**
     * Process order details action
     *
     * @param WC_Order $order Order object
     */
    public function process_order_action_order_details($order) {
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
     * Process payment link action
     *
     * @param WC_Order $order Order object
     */
    public function process_order_action_payment_link($order) {
        if (!$this->is_flex_pay_order($order)) {
            return;
        }

        $next_payment = $this->get_next_pending_payment($order);
        if ($next_payment) {
            try {
                $payment_link_manager = \WCFP\Services\Payment_Link_Manager::instance();
                $link_data = $payment_link_manager->generate_payment_link(
                    $order,
                    $next_payment['number'],
                    $next_payment,
                    true // is_overdue = true for regeneration with extended expiry
                );

                $this->notification_manager->handle_payment_link(
                    $order->get_id(),
                    $next_payment['number'],
                    $link_data
                );

                $order->add_order_note(
                    sprintf(
                        __('Payment link sent for installment #%d', 'wc-flex-pay'),
                        $next_payment['number']
                    )
                );
            } catch (\Exception $e) {
                $order->add_order_note(
                    sprintf(
                        __('Failed to send payment link: %s', 'wc-flex-pay'),
                        $e->getMessage()
                    )
                );
            }
        }
    }

    /**
     * Add bulk actions
     *
     * @param array $actions Existing actions
     * @return array Modified actions
     */
    public function add_bulk_actions($actions) {
        $actions['wcfp_bulk_send_payment_reminder'] = __('Send Flex Pay Payment Reminder', 'wc-flex-pay');
        $actions['wcfp_bulk_send_payment_complete'] = __('Send Flex Pay Payment Complete', 'wc-flex-pay');
        $actions['wcfp_bulk_send_payment_overdue'] = __('Send Flex Pay Payment Overdue Notice', 'wc-flex-pay');
        $actions['wcfp_bulk_send_order_details'] = __('Send Flex Pay Order Details', 'wc-flex-pay');
        $actions['wcfp_bulk_send_payment_link'] = __('Send Flex Pay Payment Link', 'wc-flex-pay');
        return $actions;
    }

    /**
     * Handle bulk actions
     *
     * @param string $redirect_to Redirect URL
     * @param string $action Action name
     * @param array  $post_ids Selected post IDs
     * @return string Modified redirect URL
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        $processed_orders = 0;

        switch ($action) {
            case 'wcfp_bulk_send_payment_reminder':
                foreach ($post_ids as $post_id) {
                    $order = wc_get_order($post_id);
                    if ($this->is_flex_pay_order($order)) {
                        $next_payment = $this->get_next_pending_payment($order);
                        if ($next_payment) {
                            $this->notification_manager->handle_payment_reminder($post_id, $next_payment['number']);
                            $processed_orders++;
                        }
                    }
                }
                break;

            case 'wcfp_bulk_send_payment_complete':
                foreach ($post_ids as $post_id) {
                    $order = wc_get_order($post_id);
                    if ($this->is_flex_pay_order($order)) {
                        $installment_number = $order->get_meta('_wcfp_installment_number') ?: 1;
                        $this->notification_manager->handle_payment_complete($post_id, $installment_number);
                        $processed_orders++;
                    }
                }
                break;

            case 'wcfp_bulk_send_payment_overdue':
                foreach ($post_ids as $post_id) {
                    $order = wc_get_order($post_id);
                    if ($this->is_flex_pay_order($order)) {
                        $next_payment = $this->get_next_pending_payment($order);
                        if ($next_payment) {
                            $this->notification_manager->handle_payment_overdue($post_id, $next_payment['number']);
                            $processed_orders++;
                        }
                    }
                }
                break;

            case 'wcfp_bulk_send_order_details':
                foreach ($post_ids as $post_id) {
                    $order = wc_get_order($post_id);
                    if ($this->is_flex_pay_order($order)) {
                        $installment_number = $order->get_meta('_wcfp_installment_number') ?: 1;
                        $this->notification_manager->handle_order_details($post_id, $installment_number);
                        $processed_orders++;
                    }
                }
                break;

            case 'wcfp_bulk_send_payment_link':
                foreach ($post_ids as $post_id) {
                    $order = wc_get_order($post_id);
                    if ($this->is_flex_pay_order($order)) {
                        $next_payment = $this->get_next_pending_payment($order);
                        if ($next_payment) {
                            try {
                                $order = wc_get_order($post_id);
                                $payment_link_manager = \WCFP\Services\Payment_Link_Manager::instance();
                                $link_data = $payment_link_manager->generate_payment_link(
                                    $order,
                                    $next_payment['number'],
                                    $next_payment,
                                    true // is_overdue = true for regeneration with extended expiry
                                );
                                $this->notification_manager->handle_payment_link($post_id, $next_payment['number'], $link_data);
                                $processed_orders++;
                            } catch (\Exception $e) {
                                continue;
                            }
                        }
                    }
                }
                break;
        }

        return add_query_arg(array(
            'wcfp_bulk_processed' => $processed_orders,
            'wcfp_bulk_action' => str_replace('wcfp_bulk_', '', $action)
        ), $redirect_to);
    }

    /**
     * Show bulk action notices
     */
    public function bulk_action_admin_notice() {
        if (empty($_REQUEST['wcfp_bulk_processed'])) {
            return;
        }

        $count = intval($_REQUEST['wcfp_bulk_processed']);
        $action = sanitize_text_field($_REQUEST['wcfp_bulk_action']);

        $messages = array(
            'send_payment_reminder' => _n(
                'Flex Pay payment reminder sent to %d order.',
                'Flex Pay payment reminders sent to %d orders.',
                $count,
                'wc-flex-pay'
            ),
            'send_payment_complete' => _n(
                'Flex Pay payment complete notification sent to %d order.',
                'Flex Pay payment complete notifications sent to %d orders.',
                $count,
                'wc-flex-pay'
            ),
            'send_payment_overdue' => _n(
                'Flex Pay payment overdue notice sent to %d order.',
                'Flex Pay payment overdue notices sent to %d orders.',
                $count,
                'wc-flex-pay'
            ),
            'send_order_details' => _n(
                'Flex Pay order details email sent to %d order.',
                'Flex Pay order details emails sent to %d orders.',
                $count,
                'wc-flex-pay'
            ),
            'send_payment_link' => _n(
                'Flex Pay payment link sent to %d order.',
                'Flex Pay payment links sent to %d orders.',
                $count,
                'wc-flex-pay'
            )
        );

        if (isset($messages[$action])) {
            $message = sprintf($messages[$action], $count);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
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
