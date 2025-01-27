<?php
/**
 * Payment Reminder Email
 *
 * @package WC_Flex_Pay\Emails
 */

namespace WCFP\Emails;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Reminder Email Class
 */
class Payment_Reminder extends Email_Base {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'wcfp_payment_reminder',
            __('Flex Pay Payment Reminder', 'wc-flex-pay'),
            __('Payment reminder emails are sent before an installment payment is due.', 'wc-flex-pay')
        );
    }

    /**
     * Get default subject
     *
     * @return string
     */
    public function get_default_subject() {
        return __('Payment Reminder for {product_name}: Installment #{installment_number}', 'wc-flex-pay');
    }

    /**
     * Get default heading
     *
     * @return string
     */
    public function get_default_heading() {
        return __('{product_name}: Installment #{installment_number} Payment Reminder', 'wc-flex-pay');
    }

    /**
     * Get template args
     *
     * @param bool $plain_text Whether to get plain text template args
     * @return array
     */
    protected function get_template_args($plain_text = false) {
        $args = parent::get_template_args($plain_text);
        $args['payment_status'] = get_post_meta($this->order_id, '_wcfp_payment_status', true);
        $args['reminder_days'] = absint(get_option('wcfp_reminder_days', 3));
        $args['grace_period'] = absint(get_option('wcfp_overdue_grace_period', 3));
        return $args;
    }
}
