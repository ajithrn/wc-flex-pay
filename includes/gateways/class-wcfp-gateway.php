<?php
/**
 * Flex Pay Payment Gateway
 *
 * @package WC_Flex_Pay\Gateways
 */

namespace WCFP\Gateway;

use WC_Payment_Gateway;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_Flex_Pay Class
 */
class FlexPay_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'wcfp_gateway';
        $this->icon              = '';
        $this->has_fields        = true;
        $this->method_title      = __('Flex Pay', 'wc-flex-pay');
        $this->method_description = __('Process payments using Flex Pay installment system.', 'wc-flex-pay');
        $this->supports          = array(
            'products',
            'refunds',
            'tokenization',
            'add_payment_method',
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define properties
        $this->title             = $this->get_option('title');
        $this->description       = $this->get_option('description');
        $this->enabled          = $this->get_option('enabled');
        $this->testmode         = 'yes' === $this->get_option('testmode');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
        add_action('woocommerce_payment_token_deleted', array($this, 'payment_token_deleted'), 10, 2);
        add_action('woocommerce_payment_token_set_default', array($this, 'payment_token_set_default'));
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'wc-flex-pay'),
                'label'       => __('Enable Flex Pay', 'wc-flex-pay'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'wc-flex-pay'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wc-flex-pay'),
                'default'     => __('Flex Pay', 'wc-flex-pay'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'wc-flex-pay'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'wc-flex-pay'),
                'default'     => __('Pay in installments using your saved payment method.', 'wc-flex-pay'),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __('Test mode', 'wc-flex-pay'),
                'label'       => __('Enable Test Mode', 'wc-flex-pay'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in test mode.', 'wc-flex-pay'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields() {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description));
        }

        if ($this->supports('tokenization')) {
            $this->tokenization_script();
            $this->saved_payment_methods();
            $this->save_payment_method_checkbox();
        }
    }

    /**
     * Process the payment
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        try {
            // Check if this is a Flex Pay order
            $has_flex_pay = false;
            foreach ($order->get_items() as $item) {
                if ('yes' === $item->get_meta('_wcfp_enabled')) {
                    $has_flex_pay = true;
                    break;
                }
            }

            if (!$has_flex_pay) {
                throw new \Exception(__('No Flex Pay items found in order.', 'wc-flex-pay'));
            }

            // Get the payment token
            $token_id = isset($_POST['wc-' . $this->id . '-payment-token']) ? wc_clean($_POST['wc-' . $this->id . '-payment-token']) : '';
            $token = null;

            if ('new' !== $token_id) {
                $token = \WC_Payment_Tokens::get($token_id);
                if (!$token || $token->get_user_id() !== get_current_user_id()) {
                    throw new \Exception(__('Invalid payment method.', 'wc-flex-pay'));
                }
            }

            // Process the first payment
            $result = $this->process_order_payment($order, $token);
            if (!$result) {
                throw new \Exception(__('Payment error:', 'wc-flex-pay') . ' ' . $this->get_error_message());
            }

            // Mark as processing
            $order->update_status('processing', __('Payment processed successfully via Flex Pay.', 'wc-flex-pay'));

            // Remove cart
            WC()->cart->empty_cart();

            // Return thank you redirect
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );

        } catch (\Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * Process refund
     *
     * @param  int    $order_id
     * @param  float  $amount
     * @param  string $reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);

        if (!$order) {
            return new \WP_Error('invalid_order', __('Invalid order ID.', 'wc-flex-pay'));
        }

        try {
            // Process refund logic here
            $result = $this->process_order_refund($order, $amount, $reason);
            if (!$result) {
                throw new \Exception(__('Refund failed.', 'wc-flex-pay'));
            }

            return true;

        } catch (\Exception $e) {
            return new \WP_Error('error', $e->getMessage());
        }
    }

    /**
     * Process scheduled subscription payment
     *
     * @param float    $amount_to_charge
     * @param WC_Order $order
     */
    public function scheduled_subscription_payment($amount_to_charge, $order) {
        try {
            $result = $this->process_order_payment($order, null, $amount_to_charge);
            if (!$result) {
                throw new \Exception(__('Scheduled payment failed.', 'wc-flex-pay'));
            }

        } catch (\Exception $e) {
            $order->add_order_note(sprintf(__('Flex Pay scheduled payment failed: %s', 'wc-flex-pay'), $e->getMessage()));
        }
    }

    /**
     * Process order payment
     *
     * @param  WC_Order      $order
     * @param  WC_Payment_Token $token
     * @param  float           $amount
     * @return bool
     */
    private function process_order_payment($order, $token = null, $amount = null) {
        // Implement payment processing logic here
        // This would typically integrate with a payment processor API
        
        // For testing purposes, always return true
        return true;
    }

    /**
     * Process order refund
     *
     * @param  WC_Order $order
     * @param  float    $amount
     * @param  string   $reason
     * @return bool
     */
    private function process_order_refund($order, $amount, $reason) {
        // Implement refund processing logic here
        // This would typically integrate with a payment processor API
        
        // For testing purposes, always return true
        return true;
    }

    /**
     * Handle payment token deletion
     *
     * @param string $token_id
     * @param int    $user_id
     */
    public function payment_token_deleted($token_id, $user_id) {
        // Handle token deletion if needed
    }

    /**
     * Handle payment token being set as default
     *
     * @param string $token_id
     */
    public function payment_token_set_default($token_id) {
        // Handle default token change if needed
    }

    /**
     * Get error message
     *
     * @return string
     */
    private function get_error_message() {
        return __('An error occurred while processing your payment. Please try again.', 'wc-flex-pay');
    }
}
