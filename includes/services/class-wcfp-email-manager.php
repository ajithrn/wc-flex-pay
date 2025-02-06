<?php
/**
 * Email Manager Class
 *
 * @package WC_Flex_Pay\Services
 */

namespace WCFP\Services;

use WCFP\Services\Style_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Manager Class
 * 
 * Handles centralized email sending functionality
 */
class Email_Manager {
    /**
     * Email Types
     */
    const PAYMENT_COMPLETE = 'payment_complete';
    const PAYMENT_FAILED = 'payment_failed';
    const PAYMENT_REMINDER = 'payment_reminder';
    const PAYMENT_OVERDUE = 'payment_overdue';
    const PAYMENT_LINK = 'payment_link';
    const ORDER_DETAILS = 'order_details';

    /**
     * Instance of this class
     *
     * @var Email_Manager
     */
    private static $instance = null;

    /**
     * Get class instance
     *
     * @return Email_Manager
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Send email
     *
     * @param string $type Email type
     * @param int    $order_id Order ID
     * @param int    $installment_number Installment number
     * @param array  $data Additional data
     * @return bool Whether the email was sent successfully
     */
    public function send_email($type, $order_id, $installment_number, array $data = []) {
        try {
            $emails = WC()->mailer()->get_emails();
            $email_id = "wcfp_{$type}";
            
            if (!isset($emails[$email_id])) {
                throw new \Exception("Email type {$type} not found");
            }

            // Add debug info if enabled
            if (defined('WCFP_DEBUG') && WCFP_DEBUG) {
                error_log(sprintf(
                    '[WC Flex Pay] Sending email type: %s for order #%d installment #%d',
                    $type,
                    $order_id,
                    $installment_number
                ));
            }

            // Trigger email
            $emails[$email_id]->trigger($order_id, $installment_number, $data);

            // Log success
            $this->log_email_sent($type, $order_id, $installment_number);

            return true;
        } catch (\Exception $e) {
            $this->log_error($e->getMessage(), array(
                'type' => $type,
                'order_id' => $order_id,
                'installment_number' => $installment_number,
                'data' => $data
            ));
            return false;
        }
    }

    /**
     * Log email sent
     *
     * @param string $type Email type
     * @param int    $order_id Order ID
     * @param int    $installment_number Installment number
     */
    private function log_email_sent($type, $order_id, $installment_number) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $order->add_order_note(
            sprintf(
                /* translators: 1: email type, 2: installment number */
                __('Sent %1$s email for installment #%2$d', 'wc-flex-pay'),
                str_replace('_', ' ', $type),
                $installment_number
            )
        );
    }

    /**
     * Log error
     *
     * @param string $message Error message
     * @param array  $context Error context
     */
    private function log_error($message, array $context = []) {
        if (defined('WCFP_DEBUG') && WCFP_DEBUG) {
            error_log(sprintf(
                '[WC Flex Pay] Email Error: %s | Context: %s',
                $message,
                json_encode($context)
            ));
        }
    }

    /**
     * Get email template path
     *
     * @param string $template Template name
     * @return string Template path
     */
    public function get_template_path($template) {
        return WCFP_PLUGIN_DIR . "templates/emails/{$template}.php";
    }

    /**
     * Style manager instance
     *
     * @var Style_Manager
     */
    private $style_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->style_manager = Style_Manager::instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Email Classes
        add_filter('woocommerce_email_classes', array($this, 'register_emails'));
        
        // Template Override
        add_filter('woocommerce_locate_template', array($this, 'override_wc_templates'), 20, 3);

        // Suppress WooCommerce default emails for Flex Pay orders
        add_filter('woocommerce_email_enabled_new_order', array($this, 'maybe_disable_wc_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', array($this, 'maybe_disable_wc_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', array($this, 'maybe_disable_wc_email'), 10, 2);
    }

    /**
     * Register email classes
     *
     * @param array $email_classes Existing email classes
     * @return array Modified email classes
     */
    public function register_emails($email_classes) {
        if (!class_exists('\WCFP\Emails\Email_Base')) {
            require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-base.php';
        }

        // Map of email classes to their IDs
        $email_map = array(
            'Payment_Complete' => 'wcfp_payment_complete',
            'Payment_Failed' => 'wcfp_payment_failed',
            'Payment_Reminder' => 'wcfp_payment_reminder',
            'Payment_Overdue' => 'wcfp_payment_overdue',
            'Payment_Link' => 'wcfp_payment_link',
            'Order_Details' => 'wcfp_order_details'
        );

        // Load and register each email class
        foreach ($email_map as $class => $id) {
            $class_file = strtolower(str_replace('_', '-', $class));
            $file = WCFP_PLUGIN_DIR . "includes/emails/class-wcfp-email-{$class_file}.php";
            
            if (file_exists($file)) {
                require_once $file;
                $class_name = "\\WCFP\\Emails\\{$class}";
                if (class_exists($class_name)) {
                    $email_classes[$id] = new $class_name();
                }
            }
        }

        return $email_classes;
    }

    /**
     * Override WooCommerce templates for Flex Pay orders
     *
     * @param string $template      Template file
     * @param string $template_name Template name
     * @param string $template_path Template path
     * @return string Modified template path
     */
    public function override_wc_templates($template, $template_name, $template_path) {
        $override_templates = array(
            'emails/order-details.php',
            'emails/customer-completed-order.php',
            'order/order-details.php'
        );

        if (in_array($template_name, $override_templates)) {
            $order = $this->get_current_order_from_template();
            if ($order && $this->is_flex_pay_order($order)) {
                $override = WCFP_PLUGIN_DIR . 'templates/' . $template_name;
                if (file_exists($override)) {
                    return $override;
                }
            }
        }
        
        return $template;
    }

    /**
     * Get current order from template context
     *
     * @return WC_Order|false Order object or false
     */
    private function get_current_order_from_template() {
        global $post, $order;
        
        if (is_admin() && $post && $post->post_type === 'shop_order') {
            return wc_get_order($post->ID);
        }

        if (did_action('woocommerce_email_header')) {
            // We're in an email template
            if ($order instanceof \WC_Order) {
                return $order;
            }
        }

        return false;
    }

    /**
     * Check if order is a Flex Pay order
     *
     * @param WC_Order $order Order object
     * @return bool Whether order is a Flex Pay order
     */
    /**
     * Maybe disable WooCommerce email for Flex Pay orders
     *
     * @param bool     $enabled Whether the email is enabled
     * @param WC_Order $order   Order object
     * @return bool Whether the email should be enabled
     */
    public function maybe_disable_wc_email($enabled, $order) {
        if ($enabled && ($this->is_flex_pay_order($order) || $this->is_flex_pay_sub_order($order))) {
            // Only disable customer emails
            $current_filter = current_filter();
            $customer_emails = [
                'woocommerce_email_enabled_customer_completed_order',
                'woocommerce_email_enabled_customer_processing_order',
                'woocommerce_email_enabled_customer_on_hold_order'
            ];
            if (in_array($current_filter, $customer_emails)) {
                return false;
            }
        }
        return $enabled;
    }

    /**
     * Check if order is a flex pay sub-order
     *
     * @param WC_Order $order Order object
     * @return bool Whether order is a flex pay sub-order
     */
    private function is_flex_pay_sub_order($order) {
        if (!$order) {
            return false;
        }
        return (bool) $order->get_meta('_wcfp_parent_order');
    }

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
     * Get email styles
     *
     * @return string Combined styles
     */
    public function get_styles() {
        return $this->style_manager->get_styles();
    }

    /**
     * Get component styles
     *
     * @param string $component Component name
     * @return string Component styles
     */
    public function get_component_styles($component) {
        return $this->style_manager->get_component_styles($component);
    }

    /**
     * Get status badge styles
     *
     * @param string $status Status type
     * @return array Style properties
     */
    public function get_status_badge_styles($status) {
        return $this->style_manager->get_status_badge_styles($status);
    }

    /**
     * Get button styles
     *
     * @param string $type Button type
     * @return array Style properties
     */
    public function get_button_styles($type = 'primary') {
        return $this->style_manager->get_button_styles($type);
    }

    /**
     * Get table styles
     *
     * @return array Style properties
     */
    public function get_table_styles() {
        return $this->style_manager->get_table_styles();
    }
}
