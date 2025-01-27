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
     * Constructor
     */
    public function __construct() {
        add_filter('woocommerce_email_classes', array($this, 'register_emails'));
        add_filter('woocommerce_email_actions', array($this, 'register_email_actions'));
    }

    /**
     * Register our custom emails with WooCommerce
     *
     * @param array $email_classes Array of registered email classes.
     * @return array
     */
    public function register_emails($email_classes) {
        // Email classes are registered in the Notification class
        // This class is kept for backward compatibility
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

        return $actions;
    }

    /**
     * Send payment complete email
     *
     * @param int      $payment_id Payment ID.
     * @param WC_Order $order      Order object.
     */
    public static function send_payment_complete($order_id, $installment_number, $payment_data = array()) {
        do_action('wcfp_payment_complete_notification', $order_id, $installment_number, $payment_data);
        WC()->mailer()->get_emails()['WCFP_Email_Payment_Complete']->trigger($order_id, $installment_number, $payment_data);
    }

    /**
     * Send payment failed email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $payment_data Payment data
     */
    public static function send_payment_failed($order_id, $installment_number, $payment_data = array()) {
        do_action('wcfp_payment_failed_notification', $order_id, $installment_number, $payment_data);
        WC()->mailer()->get_emails()['WCFP_Email_Payment_Failed']->trigger($order_id, $installment_number, $payment_data);
    }

    /**
     * Send payment reminder email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $payment_data Payment data
     */
    public static function send_payment_reminder($order_id, $installment_number, $payment_data = array()) {
        do_action('wcfp_payment_reminder_notification', $order_id, $installment_number, $payment_data);
        WC()->mailer()->get_emails()['WCFP_Email_Payment_Reminder']->trigger($order_id, $installment_number, $payment_data);
    }

    /**
     * Send payment overdue email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $payment_data Payment data
     */
    public static function send_payment_overdue($order_id, $installment_number, $payment_data = array()) {
        do_action('wcfp_payment_overdue_notification', $order_id, $installment_number, $payment_data);
        WC()->mailer()->get_emails()['WCFP_Email_Payment_Overdue']->trigger($order_id, $installment_number, $payment_data);
    }

    /**
     * Send payment link email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $link_data Payment link data
     */
    public static function send_payment_link($order_id, $installment_number, $link_data) {
        do_action('wcfp_payment_link_notification', $order_id, $installment_number, $link_data);
        WC()->mailer()->get_emails()['WCFP_Email_Payment_Link']->trigger($order_id, $installment_number, $link_data);
    }
}
