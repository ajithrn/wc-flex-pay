<?php
/**
 * Payment Link Email
 *
 * @package WC_Flex_Pay\Emails
 */

namespace WCFP\Emails;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Link Email Class
 */
class Payment_Link extends Email_Base {

    /**
     * Payment link data
     *
     * @var array
     */
    protected $link_data;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'wcfp_payment_link',
            __('Flex Pay Payment Link', 'wc-flex-pay'),
            __('Payment link emails are sent when an admin generates a payment link for an installment.', 'wc-flex-pay')
        );
    }

    /**
     * Get email subject
     *
     * @return string
     */
    public function get_default_subject() {
        return __('Your Payment Link for {product_name}: Installment #{installment_number}', 'wc-flex-pay');
    }

    /**
     * Get email heading
     *
     * @return string
     */
    public function get_default_heading() {
        return __('{product_name}: Installment #{installment_number}', 'wc-flex-pay');
    }

    /**
     * Trigger the email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $link_data Payment link data
     */
    public function trigger($order_id, $installment_number, $link_data = array()) {
        $this->setup_locale();

        if ($order_id && $installment_number) {
            $this->order_id = $order_id;
            $this->installment_number = $installment_number;
            $this->link_data = $link_data;

            $order = wc_get_order($order_id);
            if ($order) {
                $this->object = $order;
                $this->recipient = $this->get_option('recipient');
                if (empty($this->recipient)) {
                    $this->recipient = $order->get_billing_email();
                }

                $this->setup_installment_data($order, $installment_number);
            }
        }

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $this->get_attachments()
            );
        }

        $this->restore_locale();
    }

    /**
     * Get template args
     *
     * @param bool $plain_text Whether to get plain text template args
     * @return array
     */
    protected function get_template_args($plain_text = false) {
        $args = parent::get_template_args($plain_text);

        // Map link data to payment data format
        $payment_data = array(
            'sub_order_id' => isset($this->link_data['sub_order_id']) ? $this->link_data['sub_order_id'] : '',
            'payment_method' => isset($this->link_data['payment_method']) ? $this->link_data['payment_method'] : '',
            'expires_at' => isset($this->link_data['expires_at']) ? $this->link_data['expires_at'] : '',
            'url' => isset($this->link_data['url']) ? $this->link_data['url'] : '',
        );

        return array_merge($args, array(
            'payment_data' => $payment_data,
            'link_data' => $this->link_data,
            'payments' => (new \WCFP\Payment())->get_order_payments($this->order_id),
        ));
    }

    /**
     * Get content html
     *
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            $this->get_template_args(false),
            '',
            $this->template_base
        );
    }

    /**
     * Get content plain
     *
     * @return string
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            $this->get_template_args(true),
            '',
            $this->template_base
        );
    }
}
