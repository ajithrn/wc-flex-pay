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
        // Include email classes
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-complete.php';
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-failed.php';
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-reminder.php';
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-overdue.php';
        require_once WCFP_PLUGIN_DIR . 'includes/emails/class-wcfp-email-payment-link.php';

        // Add email classes
        $email_classes['WCFP_Email_Payment_Complete'] = new \WCFP_Email_Payment_Complete();
        $email_classes['WCFP_Email_Payment_Failed'] = new \WCFP_Email_Payment_Failed();
        $email_classes['WCFP_Email_Payment_Reminder'] = new \WCFP_Email_Payment_Reminder();
        $email_classes['WCFP_Email_Payment_Overdue'] = new \WCFP_Email_Payment_Overdue();
        $email_classes['WCFP_Email_Payment_Link'] = new Emails\Payment_Link();

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
    public static function send_payment_complete($payment_id, $order) {
        do_action('wcfp_payment_complete_notification', $payment_id, $order);
        WC()->mailer()->get_emails()['WCFP_Email_Payment_Complete']->trigger($payment_id, $order);
    }

    /**
     * Send payment failed email
     *
     * @param int      $payment_id Payment ID.
     * @param WC_Order $order      Order object.
     */
    public static function send_payment_failed($payment_id, $order) {
        do_action('wcfp_payment_failed_notification', $payment_id, $order);
        WC()->mailer()->get_emails()['WCFP_Email_Payment_Failed']->trigger($payment_id, $order);
    }

    /**
     * Send payment reminder email
     *
     * @param int      $payment_id Payment ID.
     * @param WC_Order $order      Order object.
     */
    public static function send_payment_reminder($payment_id, $order) {
        do_action('wcfp_payment_reminder_notification', $payment_id, $order);
        WC()->mailer()->get_emails()['WCFP_Email_Payment_Reminder']->trigger($payment_id, $order);
    }

    /**
     * Send payment overdue email
     *
     * @param int      $payment_id Payment ID.
     * @param WC_Order $order      Order object.
     */
    public static function send_payment_overdue($payment_id, $order) {
        do_action('wcfp_payment_overdue_notification', $payment_id, $order);
        WC()->mailer()->get_emails()['WCFP_Email_Payment_Overdue']->trigger($payment_id, $order);
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
