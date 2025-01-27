<?php
/**
 * Notification related functions and actions
 *
 * @package WC_Flex_Pay
 */

namespace WCFP;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Notification Class
 */
class Notification {

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
        // Email Classes
        add_filter('woocommerce_email_classes', array($this, 'register_emails'));
        
        // Payment Status Notifications
        add_action('woocommerce_order_status_completed', array($this, 'send_payment_completed_notification'));
        add_action('woocommerce_order_status_failed', array($this, 'send_payment_failed_notification'));
        add_action('woocommerce_order_status_on-hold', array($this, 'send_payment_overdue_notification'));
        
        // Payment Reminders
        add_action('init', array($this, 'schedule_reminders'));
        add_action('wcfp_payment_reminder', array($this, 'send_payment_reminder'));
        
        // Admin Notifications
        add_action('woocommerce_order_status_failed', array($this, 'notify_admin_payment_failed'));
        add_action('woocommerce_order_status_on-hold', array($this, 'notify_admin_payment_overdue'));

        // Order Actions
        add_filter('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_wcfp_send_payment_reminder', array($this, 'process_order_action_payment_reminder'));
        add_action('woocommerce_order_action_wcfp_send_payment_complete', array($this, 'process_order_action_payment_complete'));
        add_action('woocommerce_order_action_wcfp_send_payment_overdue', array($this, 'process_order_action_payment_overdue'));

        // Bulk Actions
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);
        add_action('admin_notices', array($this, 'bulk_action_admin_notice'));
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
     * Register email classes
     *
     * @param array $email_classes
     * @return array
     */
    public function register_emails($email_classes) {
        // Include base email class first
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-base.php';

        // Include specific email classes
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-complete.php';
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-failed.php';
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-reminder.php';
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-overdue.php';
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-link.php';

        // Add email classes with proper initialization
        $email_classes['wcfp_payment_complete'] = new Emails\Payment_Complete();
        $email_classes['wcfp_payment_failed'] = new Emails\Payment_Failed();
        $email_classes['wcfp_payment_reminder'] = new Emails\Payment_Reminder();
        $email_classes['wcfp_payment_overdue'] = new Emails\Payment_Overdue();
        $email_classes['wcfp_payment_link'] = new Emails\Payment_Link();

        return $email_classes;
    }

    /**
     * Send payment completed notification
     *
     * @param int $order_id
     */
    public function send_payment_completed_notification($order_id) {
        if (!$this->is_notification_enabled('completed')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if this is a Flex Pay order
        $has_flex_pay = false;
        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $has_flex_pay = true;
                break;
            }
        }

        if (!$has_flex_pay) {
            return;
        }

        try {
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();
            if (isset($emails['wcfp_payment_complete'])) {
                $emails['wcfp_payment_complete']->trigger($order_id, 1, array()); // Default to first installment if not specified
            }
            $this->log_notification('completed_sent', $order_id);
        } catch (\Exception $e) {
            $this->log_error($e->getMessage(), array('order_id' => $order_id));
        }
    }

    /**
     * Send payment failed notification
     *
     * @param int $order_id
     */
    public function send_payment_failed_notification($order_id) {
        if (!$this->is_notification_enabled('failed')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if this is a Flex Pay order
        $has_flex_pay = false;
        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $has_flex_pay = true;
                break;
            }
        }

        if (!$has_flex_pay) {
            return;
        }

        try {
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();
            if (isset($emails['wcfp_payment_failed'])) {
                $emails['wcfp_payment_failed']->trigger($order_id, 1, array()); // Default to first installment if not specified
            }
            $this->log_notification('failed_sent', $order_id);
        } catch (\Exception $e) {
            $this->log_error($e->getMessage(), array('order_id' => $order_id));
        }
    }

    /**
     * Send payment overdue notification
     *
     * @param int $order_id
     */
    public function send_payment_overdue_notification($order_id) {
        if (!$this->is_notification_enabled('overdue')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if this is a Flex Pay order and find overdue payments
        $has_flex_pay = false;
        $overdue_payments = array();
        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $has_flex_pay = true;
                $payment_status = $item->get_meta('_wcfp_payment_status');
                if (!empty($payment_status)) {
                    foreach ($payment_status as $payment_id => $payment) {
                        if ($payment['status'] === 'pending' && 
                            strtotime($payment['due_date']) < current_time('timestamp')) {
                            $overdue_payments[$payment_id] = $payment;
                        }
                    }
                }
                break;
            }
        }

        if (!$has_flex_pay || empty($overdue_payments)) {
            return;
        }

        try {
            foreach ($overdue_payments as $payment_id => $payment) {
                // Generate payment link with extended expiry
                $link_data = $this->generate_payment_link($order, $payment_id, $payment, true);
                
                $mailer = WC()->mailer();
                $emails = $mailer->get_emails();
                if (isset($emails['wcfp_payment_overdue'])) {
                    $emails['wcfp_payment_overdue']->trigger($order->get_id(), $payment_id, $link_data);
                }

                // Add order note
                $order->add_order_note(
                    sprintf(
                        __('Payment overdue notice sent for installment #%d. Payment link generated with extended expiry on %s.', 'wc-flex-pay'),
                        $payment_id,
                        date_i18n(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            strtotime($link_data['expires_at'])
                        )
                    ),
                    false,
                    true
                );

                $this->log_notification('overdue_sent', $payment_id, array(
                    'due_date' => $payment['due_date'],
                    'amount' => $payment['amount'],
                    'link_expiry' => $link_data['expires_at']
                ));
            }
        } catch (\Exception $e) {
            $this->log_error($e->getMessage(), array('order_id' => $order_id));
        }
    }

    /**
     * Send payment reminder
     * 
     * @param int|null $order_id Optional order ID to send reminder for specific order
     */
    public function send_payment_reminder($order_id = null) {
        if (!$this->is_notification_enabled('reminder')) {
            return;
        }

        $reminder_days = absint(get_option('wcfp_reminder_days', 3));
        $reminder_date = date('Y-m-d H:i:s', strtotime("+{$reminder_days} days"));
        $current_date = current_time('mysql');

        if ($order_id) {
            $orders = array(wc_get_order($order_id));
        } else {
            // Get all orders with flex pay payments
            $orders = wc_get_orders(array(
                'meta_key' => '_wcfp_payments',
                'limit' => -1,
            ));
        }

        foreach ($orders as $order) {
            if (!$order) continue;
            $payments = get_post_meta($order->get_id(), '_wcfp_payments', true);
            if (empty($payments)) continue;

            foreach ($payments as $payment_id => $payment) {
                if ($payment['status'] === 'pending' && 
                    strtotime($payment['due_date']) <= strtotime($reminder_date) && 
                    strtotime($payment['due_date']) > strtotime($current_date)) {
                    try {
                        // Generate payment link
                        $link_data = $this->generate_payment_link($order, $payment_id, $payment);
                        
                        $mailer = WC()->mailer();
                        $emails = $mailer->get_emails();
                        if (isset($emails['wcfp_payment_reminder'])) {
                            $emails['wcfp_payment_reminder']->trigger($order->get_id(), $payment_id, $link_data);
                        }

                        // Add order note
                        $order->add_order_note(
                            sprintf(
                                __('Payment reminder sent for installment #%d. Payment link generated with expiry on %s.', 'wc-flex-pay'),
                                $payment_id,
                                date_i18n(
                                    get_option('date_format') . ' ' . get_option('time_format'),
                                    strtotime($link_data['expires_at'])
                                )
                            ),
                            false,
                            true
                        );

                        // Log reminder sent
                        $this->log_notification('reminder_sent', $payment_id, array(
                            'due_date' => $payment['due_date'],
                            'amount' => $payment['amount'],
                            'link_expiry' => $link_data['expires_at']
                        ));
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
     * Generate payment link with optional extended expiry for overdue payments
     *
     * @param WC_Order $order Order object
     * @param int      $payment_id Payment ID
     * @param array    $payment Payment data
     * @param bool     $is_overdue Whether the payment is overdue
     * @return array
     */
    private function generate_payment_link($order, $payment_id, $payment, $is_overdue = false) {
        // Generate unique token
        $token = wp_generate_password(32, false);
        
        // Calculate expiry based on payment status and settings
        $grace_period = absint(get_option('wcfp_overdue_grace_period', 3));
        $extended_period = absint(get_option('wcfp_extended_grace_period', 7));
        $current_date = current_time('timestamp');

        if ($is_overdue) {
            // Extended expiry for overdue payments
            $expiry = strtotime("+{$extended_period} days", $current_date);
        } else {
            $due_date = strtotime($payment['due_date']);
            if ($current_date > $due_date) {
                // Payment is overdue - set expiry to grace period from now
                $expiry = strtotime("+{$grace_period} days", $current_date);
            } else {
                // Payment is upcoming - set expiry to grace period after due date
                $expiry = strtotime("+{$grace_period} days", $due_date);
            }
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
     * Process order action - payment reminder
     *
     * @param WC_Order $order
     */
    public function process_order_action_payment_reminder($order) {
        if ($this->is_flex_pay_order($order)) {
            try {
                $this->send_payment_reminder($order->get_id());
                $order->add_order_note(
                    __('Payment reminder email sent manually.', 'wc-flex-pay'),
                    false,
                    true
                );
            } catch (\Exception $e) {
                $this->log_error($e->getMessage(), array('order_id' => $order->get_id()));
            }
        }
    }

    /**
     * Process order action - payment complete
     *
     * @param WC_Order $order
     */
    public function process_order_action_payment_complete($order) {
        if ($this->is_flex_pay_order($order)) {
            try {
                $this->send_payment_completed_notification($order->get_id());
                $order->add_order_note(
                    __('Payment complete email sent manually.', 'wc-flex-pay'),
                    false,
                    true
                );
            } catch (\Exception $e) {
                $this->log_error($e->getMessage(), array('order_id' => $order->get_id()));
            }
        }
    }

    /**
     * Process order action - payment overdue
     *
     * @param WC_Order $order
     */
    public function process_order_action_payment_overdue($order) {
        if ($this->is_flex_pay_order($order)) {
            try {
                $this->send_payment_overdue_notification($order->get_id());
                $order->add_order_note(
                    __('Payment overdue notice sent manually.', 'wc-flex-pay'),
                    false,
                    true
                );
            } catch (\Exception $e) {
                $this->log_error($e->getMessage(), array('order_id' => $order->get_id()));
            }
        }
    }

    /**
     * Add order actions
     *
     * @param array $actions
     * @return array
     */
    public function add_order_actions($actions) {
        global $theorder;

        // Check if this is a Flex Pay order
        $has_flex_pay = false;
        if ($theorder) {
            foreach ($theorder->get_items() as $item) {
                if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                    $has_flex_pay = true;
                    break;
                }
            }
        }

        if ($has_flex_pay) {
            $actions['wcfp_send_payment_reminder'] = __('Send Payment Reminder', 'wc-flex-pay');
            $actions['wcfp_send_payment_complete'] = __('Send Payment Complete', 'wc-flex-pay');
            $actions['wcfp_send_payment_overdue'] = __('Send Payment Overdue Notice', 'wc-flex-pay');
        }

        return $actions;
    }

    /**
     * Add bulk actions
     *
     * @param array $actions
     * @return array
     */
    public function add_bulk_actions($actions) {
        $actions['wcfp_bulk_send_payment_reminder'] = __('Send Payment Reminder', 'wc-flex-pay');
        $actions['wcfp_bulk_send_payment_complete'] = __('Send Payment Complete', 'wc-flex-pay');
        $actions['wcfp_bulk_send_payment_overdue'] = __('Send Payment Overdue Notice', 'wc-flex-pay');
        return $actions;
    }

    /**
     * Handle bulk actions
     *
     * @param string $redirect_to
     * @param string $action
     * @param array  $post_ids
     * @return string
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        $processed_orders = 0;

        switch ($action) {
            case 'wcfp_bulk_send_payment_reminder':
                foreach ($post_ids as $post_id) {
                    $order = wc_get_order($post_id);
                    if ($this->is_flex_pay_order($order)) {
                        try {
                            $this->send_payment_reminder($post_id);
                            $processed_orders++;
                        } catch (\Exception $e) {
                            $this->log_error($e->getMessage(), array('order_id' => $post_id));
                        }
                    }
                }
                $redirect_to = add_query_arg(array(
                    'wcfp_bulk_sent' => $processed_orders,
                    'wcfp_bulk_action' => 'payment_reminder',
                ), $redirect_to);
                break;

            case 'wcfp_bulk_send_payment_complete':
                foreach ($post_ids as $post_id) {
                    $order = wc_get_order($post_id);
                    if ($this->is_flex_pay_order($order)) {
                        try {
                            $this->send_payment_completed_notification($post_id);
                            $processed_orders++;
                        } catch (\Exception $e) {
                            $this->log_error($e->getMessage(), array('order_id' => $post_id));
                        }
                    }
                }
                $redirect_to = add_query_arg(array(
                    'wcfp_bulk_sent' => $processed_orders,
                    'wcfp_bulk_action' => 'payment_complete',
                ), $redirect_to);
                break;

            case 'wcfp_bulk_send_payment_overdue':
                foreach ($post_ids as $post_id) {
                    $order = wc_get_order($post_id);
                    if ($this->is_flex_pay_order($order)) {
                        try {
                            $this->send_payment_overdue_notification($post_id);
                            $processed_orders++;
                        } catch (\Exception $e) {
                            $this->log_error($e->getMessage(), array('order_id' => $post_id));
                        }
                    }
                }
                $redirect_to = add_query_arg(array(
                    'wcfp_bulk_sent' => $processed_orders,
                    'wcfp_bulk_action' => 'payment_overdue',
                ), $redirect_to);
                break;
        }

        return $redirect_to;
    }

    /**
     * Show bulk action notices
     */
    public function bulk_action_admin_notice() {
        if (empty($_REQUEST['wcfp_bulk_sent'])) {
            return;
        }

        $count = intval($_REQUEST['wcfp_bulk_sent']);
        $action = sanitize_text_field($_REQUEST['wcfp_bulk_action']);

        $message = '';
        switch ($action) {
            case 'payment_reminder':
                $message = sprintf(
                    _n(
                        'Payment reminder sent to %d order.',
                        'Payment reminders sent to %d orders.',
                        $count,
                        'wc-flex-pay'
                    ),
                    $count
                );
                break;

            case 'payment_complete':
                $message = sprintf(
                    _n(
                        'Payment complete notification sent to %d order.',
                        'Payment complete notifications sent to %d orders.',
                        $count,
                        'wc-flex-pay'
                    ),
                    $count
                );
                break;

            case 'payment_overdue':
                $message = sprintf(
                    _n(
                        'Payment overdue notice sent to %d order.',
                        'Payment overdue notices sent to %d orders.',
                        $count,
                        'wc-flex-pay'
                    ),
                    $count
                );
                break;
        }

        if ($message) {
            echo '<div class="updated"><p>' . esc_html($message) . '</p></div>';
        }
    }

    /**
     * Check if order is a Flex Pay order
     *
     * @param WC_Order $order
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
     * Check if notification is enabled
     *
     * @param string $type
     * @return bool
     */
    private function is_notification_enabled($type) {
        return 'yes' === get_option('wcfp_enable_' . $type . '_notifications', 'yes');
    }

    /**
     * Get admin email
     *
     * @return string
     */
    private function get_admin_email() {
        return get_option('wcfp_admin_email', get_option('admin_email'));
    }

    /**
     * Log notification
     *
     * @param string $type
     * @param int    $payment_id
     * @param array  $context
     */
    private function log_notification($type, $payment_id, $context = array()) {
        if (defined('WCFP_DEBUG') && WCFP_DEBUG) {
            $context['timestamp'] = current_time('mysql');
            $context['user_id'] = get_current_user_id();
            
            error_log(sprintf(
                '[WC Flex Pay] Notification sent: %s | Payment ID: %d | Context: %s',
                $type,
                $payment_id,
                json_encode($context, JSON_PRETTY_PRINT)
            ));
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
            $context['timestamp'] = current_time('mysql');
            $context['user_id'] = get_current_user_id();
            
            error_log(sprintf(
                '[WC Flex Pay] Notification error: %s | Context: %s',
                $message,
                json_encode($context, JSON_PRETTY_PRINT)
            ));

            // Log to WooCommerce error log as well
            wc_get_logger()->error($message, array(
                'source' => 'wc-flex-pay',
                'context' => $context
            ));
        }
    }
}
