<?php
/**
 * Payment Overdue Email
 *
 * @package WC_Flex_Pay\Emails
 */

namespace WCFP\Emails;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Overdue Email Class
 */
class Payment_Overdue extends Email_Base {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'wcfp_payment_overdue',
            __('Flex Pay Payment Overdue', 'wc-flex-pay'),
            __('Payment overdue emails are sent when an installment payment is overdue.', 'wc-flex-pay')
        );
    }

    /**
     * Get default subject
     *
     * @return string
     */
    public function get_default_subject() {
        return __('Payment Overdue: Order #{order_number} - Installment #{installment_number}', 'wc-flex-pay');
    }

    /**
     * Get default heading
     *
     * @return string
     */
    public function get_default_heading() {
        return __('Payment Overdue: Order #{order_number} - Installment #{installment_number}', 'wc-flex-pay');
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
        $args['grace_period'] = absint(get_option('wcfp_overdue_grace_period', 3));
        $args['extended_period'] = absint(get_option('wcfp_extended_grace_period', 7));
        return $args;
    }
}
