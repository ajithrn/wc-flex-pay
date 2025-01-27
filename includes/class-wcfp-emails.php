<?php
/**
 * Email Handler Class
 *
 * @package WC_Flex_Pay\Includes
 */

namespace WCFP;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Emails Class
 *
 * Handles the email notifications for Flex Pay
 */
class Emails {

    /**
     * Single instance of the class
     *
     * @var Emails
     */
    private static $instance = null;

    /**
     * Get class instance
     *
     * @return Emails
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('woocommerce_email_classes', array($this, 'register_emails'));
        add_filter('woocommerce_email_actions', array($this, 'register_email_actions'));
    }

    /**
     * Prevent cloning of the instance
     */
    public function __clone() {
        wc_doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', 'wc-flex-pay'), '1.6.0');
    }

    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup() {
        wc_doing_it_wrong(__FUNCTION__, __('Unserializing instances of this class is forbidden.', 'wc-flex-pay'), '1.6.0');
    }

    /**
     * Register our custom emails with WooCommerce
     *
     * @param array $email_classes Array of registered email classes.
     * @return array
     */
    public function register_emails($email_classes) {
        if (!class_exists('\WCFP\Emails\Email_Base')) {
            require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-base.php';
        }

        $email_map = array(
            'Payment_Complete' => 'wcfp_payment_complete',
            'Payment_Failed' => 'wcfp_payment_failed',
            'Payment_Reminder' => 'wcfp_payment_reminder',
            'Payment_Overdue' => 'wcfp_payment_overdue',
            'Payment_Link' => 'wcfp_payment_link',
            'Order_Details' => 'wcfp_order_details'
        );

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
     * Register custom email actions
     *
     * @param array $actions Array of registered email actions.
     * @return array
     */
    public function register_email_actions($actions) {
        $actions[] = 'wcfp_payment_complete';
        $actions[] = 'wcfp_payment_failed';
        $actions[] = 'wcfp_payment_reminder';
        $actions[] = 'wcfp_payment_overdue';
        $actions[] = 'wcfp_payment_link';
        $actions[] = 'wcfp_send_order_details_notification';

        return $actions;
    }

    /**
     * Send payment complete email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $payment_data Payment data
     */
    public function send_payment_complete($order_id, $installment_number, $payment_data = array()) {
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        if (isset($emails['wcfp_payment_complete'])) {
            do_action('wcfp_payment_complete_notification', $order_id, $installment_number, $payment_data);
            $emails['wcfp_payment_complete']->trigger($order_id, $installment_number, $payment_data);
        }
    }

    /**
     * Send payment failed email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $payment_data Payment data
     */
    public function send_payment_failed($order_id, $installment_number, $payment_data = array()) {
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        if (isset($emails['wcfp_payment_failed'])) {
            do_action('wcfp_payment_failed_notification', $order_id, $installment_number, $payment_data);
            $emails['wcfp_payment_failed']->trigger($order_id, $installment_number, $payment_data);
        }
    }

    /**
     * Send payment reminder email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $payment_data Payment data
     */
    public function send_payment_reminder($order_id, $installment_number, $payment_data = array()) {
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        if (isset($emails['wcfp_payment_reminder'])) {
            do_action('wcfp_payment_reminder_notification', $order_id, $installment_number, $payment_data);
            $emails['wcfp_payment_reminder']->trigger($order_id, $installment_number, $payment_data);
        }
    }

    /**
     * Send payment overdue email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $payment_data Payment data
     */
    public function send_payment_overdue($order_id, $installment_number, $payment_data = array()) {
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        if (isset($emails['wcfp_payment_overdue'])) {
            do_action('wcfp_payment_overdue_notification', $order_id, $installment_number, $payment_data);
            $emails['wcfp_payment_overdue']->trigger($order_id, $installment_number, $payment_data);
        }
    }

    /**
     * Send payment link email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $link_data Payment link data
     */
    public function send_payment_link($order_id, $installment_number, $link_data) {
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        if (isset($emails['wcfp_payment_link'])) {
            do_action('wcfp_payment_link_notification', $order_id, $installment_number, $link_data);
            $emails['wcfp_payment_link']->trigger($order_id, $installment_number, $link_data);
        }
    }

    /**
     * Send order details email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $payment_data Payment data
     */
    public function send_order_details($order_id, $installment_number = null, $payment_data = array()) {
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        if (isset($emails['wcfp_order_details'])) {
            // Get the latest installment number if not provided
            if (is_null($installment_number)) {
                $order = wc_get_order($order_id);
                if ($order) {
                    foreach ($order->get_items() as $item) {
                        $payment_status = $item->get_meta('_wcfp_payment_status');
                        if (!empty($payment_status)) {
                            $installment_number = count($payment_status);
                            break;
                        }
                    }
                }
                $installment_number = $installment_number ?: 1;
            }

            // Prepare payment data with complete schedule
            if (empty($payment_data)) {
                $order = wc_get_order($order_id);
                if ($order) {
                    foreach ($order->get_items() as $item) {
                        $payment_status = $item->get_meta('_wcfp_payment_status');
                        if (!empty($payment_status)) {
                            $payment_data = array(
                                'total_amount' => 0,
                                'paid_amount' => 0,
                                'pending_amount' => 0,
                                'payment_schedule' => $payment_status
                            );
                            break;
                        }
                    }
                }
            }

            // Send a single email with complete payment schedule
            do_action('wcfp_send_order_details_notification', $order_id, $installment_number, $payment_data);
            $emails['wcfp_order_details']->trigger($order_id, $installment_number, $payment_data);
        }
    }
}
