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
        add_action('wcfp_payment_status_completed', array($this, 'send_payment_completed_notification'));
        add_action('wcfp_payment_status_failed', array($this, 'send_payment_failed_notification'));
        add_action('wcfp_payment_status_overdue', array($this, 'send_payment_overdue_notification'));
        
        // Payment Reminders
        add_action('init', array($this, 'schedule_reminders'));
        add_action('wcfp_payment_reminder', array($this, 'send_payment_reminder'));
        
        // Admin Notifications
        add_action('wcfp_payment_status_failed', array($this, 'notify_admin_payment_failed'));
        add_action('wcfp_payment_status_overdue', array($this, 'notify_admin_payment_overdue'));
    }

    /**
     * Check if required tables exist
     *
     * @return bool
     */
    private function tables_exist() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'wcfp_order_payments'
        );

        foreach ($required_tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * Register email classes
     *
     * @param array $email_classes
     * @return array
     */
    public function register_emails($email_classes) {
        $email_classes['WCFP_Email_Payment_Complete'] = include WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-complete.php';
        $email_classes['WCFP_Email_Payment_Failed'] = include WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-failed.php';
        $email_classes['WCFP_Email_Payment_Reminder'] = include WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-reminder.php';
        $email_classes['WCFP_Email_Payment_Overdue'] = include WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-overdue.php';
        
        return $email_classes;
    }

    /**
     * Schedule payment reminders
     */
    public function schedule_reminders() {
        if (!$this->tables_exist()) {
            $this->log_error('Database tables not found');
            return;
        }

        if (!wp_next_scheduled('wcfp_payment_reminder')) {
            wp_schedule_event(time(), 'daily', 'wcfp_payment_reminder');
        }
    }

    /**
     * Send payment reminder
     */
    public function send_payment_reminder() {
        if (!$this->tables_exist()) {
            $this->log_error('Database tables not found');
            return;
        }

        if (!$this->is_notification_enabled('reminder')) {
            return;
        }

        global $wpdb;

        $reminder_days = absint(get_option('wcfp_reminder_days', 3));
        $reminder_date = date('Y-m-d H:i:s', strtotime("+{$reminder_days} days"));

        $upcoming_payments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcfp_order_payments 
                WHERE status = %s 
                AND due_date <= %s 
                AND due_date > NOW() 
                AND 1=%d",
                'pending',
                $reminder_date,
                1
            ),
            ARRAY_A
        ) ?: array();

        foreach ($upcoming_payments as $payment) {
            try {
                $order = wc_get_order($payment['order_id']);
                if (!$order) {
                    continue;
                }

                $mailer = WC()->mailer();
                $email = new \WCFP_Email_Payment_Reminder();
                
                $email->trigger($payment['id'], $order);

                // Log reminder sent
                $this->log_notification('reminder_sent', $payment['id'], array(
                    'due_date' => $payment['due_date'],
                    'amount' => $payment['amount']
                ));
            } catch (\Exception $e) {
                $this->log_error($e->getMessage(), array(
                    'payment_id' => $payment['id'],
                    'order_id' => $payment['order_id']
                ));
            }
        }
    }

    /**
     * Send payment completed notification
     *
     * @param int $payment_id
     */
    public function send_payment_completed_notification($payment_id) {
        if (!$this->is_notification_enabled('completed')) {
            return;
        }

        try {
            $payment = $this->get_payment($payment_id);
            if (!$payment) {
                throw new \Exception('Payment not found');
            }

            $order = wc_get_order($payment['order_id']);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $mailer = WC()->mailer();
            $email = new \WCFP_Email_Payment_Complete();
            
            $email->trigger($payment_id, $order);

            $this->log_notification('completed_sent', $payment_id);
        } catch (\Exception $e) {
            $this->log_error($e->getMessage(), array('payment_id' => $payment_id));
        }
    }

    /**
     * Send payment failed notification
     *
     * @param int $payment_id
     */
    public function send_payment_failed_notification($payment_id) {
        if (!$this->is_notification_enabled('failed')) {
            return;
        }

        try {
            $payment = $this->get_payment($payment_id);
            if (!$payment) {
                throw new \Exception('Payment not found');
            }

            $order = wc_get_order($payment['order_id']);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $mailer = WC()->mailer();
            $email = new \WCFP_Email_Payment_Failed();
            
            $email->trigger($payment_id, $order);

            $this->log_notification('failed_sent', $payment_id);
        } catch (\Exception $e) {
            $this->log_error($e->getMessage(), array('payment_id' => $payment_id));
        }
    }

    /**
     * Send payment overdue notification
     *
     * @param int $payment_id
     */
    public function send_payment_overdue_notification($payment_id) {
        if (!$this->is_notification_enabled('overdue')) {
            return;
        }

        try {
            $payment = $this->get_payment($payment_id);
            if (!$payment) {
                throw new \Exception('Payment not found');
            }

            $order = wc_get_order($payment['order_id']);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $mailer = WC()->mailer();
            $email = new \WCFP_Email_Payment_Overdue();
            
            $email->trigger($payment_id, $order);

            $this->log_notification('overdue_sent', $payment_id);
        } catch (\Exception $e) {
            $this->log_error($e->getMessage(), array('payment_id' => $payment_id));
        }
    }

    /**
     * Notify admin of payment failure
     *
     * @param int $payment_id
     */
    public function notify_admin_payment_failed($payment_id) {
        if (!$this->is_notification_enabled('admin_failed')) {
            return;
        }

        try {
            $payment = $this->get_payment($payment_id);
            if (!$payment) {
                throw new \Exception('Payment not found');
            }

            $order = wc_get_order($payment['order_id']);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $admin_email = $this->get_admin_email();
            
            $subject = sprintf(
                __('[%s] Flex Pay Payment Failed - Order #%s', 'wc-flex-pay'),
                get_bloginfo('name'),
                $order->get_order_number()
            );
            
            $message = sprintf(
                __('Payment of %s for order #%s has failed. Please check the order for more details.', 'wc-flex-pay'),
                wc_price($payment['amount']),
                $order->get_order_number()
            );

            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($admin_email, $subject, $message, $headers);

            $this->log_notification('admin_failed_sent', $payment_id);
        } catch (\Exception $e) {
            $this->log_error($e->getMessage(), array('payment_id' => $payment_id));
        }
    }

    /**
     * Notify admin of overdue payment
     *
     * @param int $payment_id
     */
    public function notify_admin_payment_overdue($payment_id) {
        if (!$this->is_notification_enabled('admin_overdue')) {
            return;
        }

        try {
            $payment = $this->get_payment($payment_id);
            if (!$payment) {
                throw new \Exception('Payment not found');
            }

            $order = wc_get_order($payment['order_id']);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $admin_email = $this->get_admin_email();
            
            $subject = sprintf(
                __('[%s] Flex Pay Payment Overdue - Order #%s', 'wc-flex-pay'),
                get_bloginfo('name'),
                $order->get_order_number()
            );
            
            $message = sprintf(
                __('Payment of %s for order #%s is overdue (due date: %s). Please check the order for more details.', 'wc-flex-pay'),
                wc_price($payment['amount']),
                $order->get_order_number(),
                date_i18n(get_option('date_format'), strtotime($payment['due_date']))
            );

            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($admin_email, $subject, $message, $headers);

            $this->log_notification('admin_overdue_sent', $payment_id);
        } catch (\Exception $e) {
            $this->log_error($e->getMessage(), array('payment_id' => $payment_id));
        }
    }

    /**
     * Get payment details
     *
     * @param int $payment_id
     * @return array|null
     */
    private function get_payment($payment_id) {
        if (!$this->tables_exist()) {
            return null;
        }

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcfp_order_payments WHERE id = %d AND 1=%d",
                $payment_id,
                1
            ),
            ARRAY_A
        );
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
            error_log(sprintf(
                '[WC Flex Pay] Notification sent: %s | Payment ID: %d | Context: %s',
                $type,
                $payment_id,
                json_encode($context)
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
            error_log(sprintf(
                '[WC Flex Pay] Notification error: %s | Context: %s',
                $message,
                json_encode($context)
            ));
        }
    }

    /**
     * Format email string
     *
     * @param string $string
     * @param array  $args
     * @return string
     */
    public function format_string($string, $args = array()) {
        if (!empty($args)) {
            foreach ($args as $key => $value) {
                $string = str_replace('{' . $key . '}', $value, $string);
            }
        }
        return $string;
    }

    /**
     * Get email template
     *
     * @param string $template_name
     * @return string
     */
    public function get_template($template_name) {
        $template = '';
        
        // Look within theme/woocommerce/emails/
        $template = locate_template(
            array(
                "woocommerce/emails/{$template_name}",
                "emails/{$template_name}",
            )
        );

        // Get default template
        if (!$template) {
            $template = WCFP_PLUGIN_DIR . "templates/emails/{$template_name}";
        }

        return $template;
    }
}
