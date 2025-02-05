<?php
/**
 * Payment Complete Email
 *
 * @package WC_Flex_Pay\Emails
 */

namespace WCFP\Emails;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Complete Email Class
 */
class Payment_Complete extends Email_Base {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'wcfp_payment_complete',
            __('Flex Pay Payment Complete', 'wc-flex-pay'),
            __('Payment complete emails are sent when an installment payment is completed.', 'wc-flex-pay')
        );
    }

    /**
     * Get default subject
     *
     * @return string
     */
    public function get_default_subject() {
        return __('Payment Complete for {product_name}: Installment #{installment_number}', 'wc-flex-pay');
    }

    /**
     * Get default heading
     *
     * @return string
     */
    public function get_default_heading() {
        return __('{product_name}: Installment #{installment_number} Payment Complete', 'wc-flex-pay');
    }

    /**
     * Get template args
     *
     * @param bool $plain_text Whether to get plain text template args
     * @return array
     */
    protected function get_template_args($plain_text = false) {
        $args = parent::get_template_args($plain_text);
        
        // Get order and ensure it exists
        $order = wc_get_order($this->order_id);
        if (!$order) {
            return $args;
        }

        // Initialize payment status data
        $payment_status = array();
        $current_payment = null;
        
        // Check if this is a sub-order payment
        $parent_order_id = $order->get_meta('_wcfp_parent_order');
        if ($parent_order_id) {
            // This is a sub-order, get parent order data
            $parent_order = wc_get_order($parent_order_id);
            if ($parent_order) {
                foreach ($parent_order->get_items() as $item) {
                    if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                        $payment_status = $item->get_meta('_wcfp_payment_status');
                        if (!empty($payment_status[$this->installment_number - 1])) {
                            $current_payment = $payment_status[$this->installment_number - 1];
                            $current_payment['sub_order_id'] = $this->order_id;
                            break;
                        }
                    }
                }
            }
        } else {
            // This is a parent order
            foreach ($order->get_items() as $item) {
                if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                    $payment_status = $item->get_meta('_wcfp_payment_status');
                    if (!empty($payment_status[$this->installment_number - 1])) {
                        $current_payment = $payment_status[$this->installment_number - 1];
                        break;
                    }
                }
            }
        }

        // Add payment status data to args
        $args['payment_status'] = $payment_status;
        $args['current_payment'] = $current_payment;
        
        // If payment data wasn't provided, build it from the current payment
        if (empty($args['payment_data']) && $current_payment) {
            $args['payment_data'] = array(
                'total_amount' => array_sum(array_column($payment_status, 'amount')),
                'paid_amount' => array_sum(array_column(array_filter($payment_status, function($status) {
                    return $status['status'] === 'completed';
                }), 'amount')),
                'current_installment' => $current_payment,
                'sub_order_id' => $current_payment['sub_order_id'] ?? null,
                'current_payment' => array(
                    'amount' => $current_payment['amount'],
                    'transaction_id' => $current_payment['transaction_id'],
                    'payment_method' => $order->get_payment_method_title(),
                    'date' => $current_payment['payment_date'],
                    'sub_order_id' => $current_payment['sub_order_id'] ?? null,
                    'installment_number' => $this->installment_number
                )
            );
            $args['payment_data']['pending_amount'] = $args['payment_data']['total_amount'] - $args['payment_data']['paid_amount'];
        }

        return $args;
    }
}
