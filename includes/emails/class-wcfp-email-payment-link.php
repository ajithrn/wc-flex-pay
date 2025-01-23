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
class Payment_Link extends \WC_Email {

    /**
     * Order ID
     *
     * @var int
     */
    private $order_id;

    /**
     * Installment number
     *
     * @var int
     */
    private $installment_number;

    /**
     * Payment link data
     *
     * @var array
     */
    private $link_data;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'wcfp_payment_link';
        $this->title = __('Flex Pay Payment Link', 'wc-flex-pay');
        $this->description = __('Payment link emails are sent when an admin generates a payment link for an installment.', 'wc-flex-pay');
        $this->template_html = 'emails/payment-link.php';
        $this->template_plain = 'emails/plain/payment-link.php';
        $this->template_base = WCFP_PLUGIN_DIR . 'templates/';
        $this->placeholders = array(
            '{order_number}' => '',
            '{installment_number}' => '',
            '{payment_amount}' => '',
            '{due_date}' => '',
        );

        // Call parent constructor
        parent::__construct();
    }

    /**
     * Get email subject
     *
     * @return string
     */
    public function get_default_subject() {
        return __('Payment link for your order {order_number} - Installment {installment_number}', 'wc-flex-pay');
    }

    /**
     * Get email heading
     *
     * @return string
     */
    public function get_default_heading() {
        return __('Payment Link for Installment {installment_number}', 'wc-flex-pay');
    }

    /**
     * Trigger the email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $link_data Payment link data
     */
    public function trigger($order_id, $installment_number, $link_data) {
        $this->setup_locale();

        if ($order_id && $installment_number) {
            $this->order_id = $order_id;
            $this->installment_number = $installment_number;
            $this->link_data = $link_data;

            $order = wc_get_order($order_id);
            if ($order) {
                $this->object = $order;
                $this->recipient = $order->get_billing_email();

                // Get installment data from order items
                foreach ($order->get_items() as $item) {
                    if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                        $payment_status = $item->get_meta('_wcfp_payment_status');
                        if (!empty($payment_status[$installment_number - 1])) {
                            $installment = $payment_status[$installment_number - 1];
                            $this->placeholders['{order_number}'] = $order->get_order_number();
                            $this->placeholders['{installment_number}'] = $installment_number;
                            $this->placeholders['{payment_amount}'] = wc_price($installment['amount']);
                            $this->placeholders['{due_date}'] = date_i18n(
                                get_option('date_format'),
                                strtotime($installment['due_date'])
                            );
                            break;
                        }
                    }
                }
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
     * Get content html
     *
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order' => $this->object,
                'installment_number' => $this->installment_number,
                'link_data' => $this->link_data,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text' => false,
                'email' => $this,
                'payments' => (new \WCFP\Payment())->get_order_payments($this->order_id),
            ),
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
            array(
                'order' => $this->object,
                'installment_number' => $this->installment_number,
                'link_data' => $this->link_data,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text' => true,
                'email' => $this,
                'payments' => (new \WCFP\Payment())->get_order_payments($this->order_id),
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Initialize settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wc-flex-pay'),
                'type' => 'checkbox',
                'label' => __('Enable this email notification', 'wc-flex-pay'),
                'default' => 'yes',
            ),
            'subject' => array(
                'title' => __('Subject', 'wc-flex-pay'),
                'type' => 'text',
                'description' => sprintf(__('Available placeholders: %s', 'wc-flex-pay'), '<code>{order_number}, {installment_number}, {payment_amount}, {due_date}</code>'),
                'placeholder' => $this->get_default_subject(),
                'default' => '',
                'desc_tip' => true,
            ),
            'heading' => array(
                'title' => __('Email Heading', 'wc-flex-pay'),
                'type' => 'text',
                'description' => sprintf(__('Available placeholders: %s', 'wc-flex-pay'), '<code>{order_number}, {installment_number}, {payment_amount}, {due_date}</code>'),
                'placeholder' => $this->get_default_heading(),
                'default' => '',
                'desc_tip' => true,
            ),
            'email_type' => array(
                'title' => __('Email type', 'wc-flex-pay'),
                'type' => 'select',
                'description' => __('Choose which format of email to send.', 'wc-flex-pay'),
                'default' => 'html',
                'class' => 'email_type wc-enhanced-select',
                'options' => $this->get_email_type_options(),
                'desc_tip' => true,
            ),
        );
    }
}
