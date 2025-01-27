<?php
/**
 * Payment Failed Email
 *
 * @package WC_Flex_Pay\Emails
 */

namespace WCFP\Emails;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Failed Email Class
 */
class Payment_Failed extends Email_Base {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'wcfp_payment_failed',
            __('Flex Pay Payment Failed', 'wc-flex-pay'),
            __('Payment failed emails are sent when an installment payment fails.', 'wc-flex-pay')
        );
    }

    /**
     * Get default subject
     *
     * @return string
     */
    public function get_default_subject() {
        return __('Payment Failed for {product_name}: Installment #{installment_number}', 'wc-flex-pay');
    }

    /**
     * Get default heading
     *
     * @return string
     */
    public function get_default_heading() {
        return __('{product_name}: Installment #{installment_number} Payment Failed', 'wc-flex-pay');
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
        $args['failure_reason'] = get_post_meta($this->order_id, '_wcfp_payment_failure_reason', true);
        return $args;
    }
}
