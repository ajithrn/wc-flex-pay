<?php
/**
 * Base Email Class
 *
 * @package WC_Flex_Pay\Emails
 */

namespace WCFP\Emails;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Email Class
 * 
 * Abstract base class for all FlexPay email types
 */
abstract class Email_Base extends \WC_Email {

    /**
     * Order ID
     *
     * @var int
     */
    protected $order_id;

    /**
     * Installment number
     *
     * @var int
     */
    protected $installment_number;

    /**
     * Payment data
     *
     * @var array
     */
    protected $payment_data;

    /**
     * Payment link data
     *
     * @var array
     */
    protected $link_data;

    /**
     * Constructor
     *
     * @param string $id Email ID
     * @param string $title Email title
     * @param string $description Email description
     */
    public function __construct($id, $title, $description) {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        
        // Map ID to template filename by removing prefix and converting to kebab case
        $template_name = str_replace('wcfp_', '', $id);
        $template_name = str_replace('_', '-', $template_name);
        
        $this->template_html = "emails/{$template_name}.php";
        $this->template_plain = "emails/plain/{$template_name}.php";
        $this->template_base = WCFP_PLUGIN_DIR . 'templates/';
        $this->recipient = '';

        // Common placeholders
        $this->placeholders = array(
            '{order_number}' => '',
            '{installment_number}' => '',
            '{payment_amount}' => '',
            '{due_date}' => '',
            '{product_name}' => '',
        );

        // Call parent constructor first
        parent::__construct();

        // Initialize settings
        $this->init_settings();
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        // Initialize form fields
        $this->init_form_fields();

        // Initialize settings with defaults
        $this->settings = array_merge(
            array(
                'enabled' => 'yes',
                'recipient' => $this->recipient,
                'subject' => $this->get_default_subject(),
                'heading' => $this->get_default_heading(),
                'email_type' => 'html',
            ),
            (array) get_option($this->get_option_key(), array())
        );

        // Set email defaults
        $this->email_type = $this->settings['email_type'];
        $this->enabled = $this->settings['enabled'];
    }

    /**
     * Update placeholders with order data
     *
     * @param \WC_Order      $order Order object
     * @param int           $installment_number Installment number
     * @param array         $installment Installment data
     * @param \WC_Order_Item $item Order item
     * @return void
     */
    public function update_placeholders($order, $installment_number, $installment, $item) {
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{installment_number}'] = $installment_number;
        $this->placeholders['{payment_amount}'] = wc_price($installment['amount']);
        $this->placeholders['{due_date}'] = date_i18n(
            get_option('date_format'),
            strtotime($installment['due_date'])
        );
        $this->placeholders['{product_name}'] = $item->get_name();
    }

    /**
     * Setup installment data from order
     *
     * @param \WC_Order $order Order object
     * @param int       $installment_number Installment number
     * @return void
     */
    public function setup_installment_data($order, $installment_number) {
        foreach ($order->get_items() as $item) {
            if ('yes' === $item->get_meta('_wcfp_enabled') && 'installment' === $item->get_meta('_wcfp_payment_type')) {
                $payment_status = $item->get_meta('_wcfp_payment_status');
                if (!empty($payment_status[$installment_number - 1])) {
                    $this->update_placeholders(
                        $order,
                        $installment_number,
                        $payment_status[$installment_number - 1],
                        $item
                    );
                    break;
                }
            }
        }
    }

    /**
     * Trigger the email
     *
     * @param int   $order_id Order ID
     * @param int   $installment_number Installment number
     * @param array $payment_data Payment data
     */
    public function trigger($order_id, $installment_number, $payment_data = array()) {
        $this->setup_locale();

        if ($order_id && $installment_number) {
            $this->order_id = $order_id;
            $this->installment_number = $installment_number;
            $this->payment_data = $payment_data;
            $this->link_data = $payment_data; // For backward compatibility with payment link email

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
        // Get additional content from settings
        $additional_content = $this->get_option('additional_content', '');
        
        // Initialize payment data with defaults
        $payment_data = array_merge(array(
            'total_amount' => 0,
            'paid_amount' => 0,
            'pending_amount' => 0,
            'current_installment' => null,
            'sub_order_id' => null,
            'payment_method' => '',
            'expires_at' => null,
            'url' => '',
        ), (array) $this->payment_data);

        return array(
            'order' => $this->object,
            'installment_number' => $this->installment_number,
            'payment_data' => $payment_data,
            'link_data' => $this->link_data,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text' => $plain_text,
            'email' => $this,
            'additional_content' => $additional_content,
        );
    }

    /**
     * Initialize form fields
     *
     * @return void
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
                'description' => sprintf(__('Available placeholders: %s', 'wc-flex-pay'), '<code>{order_number}, {installment_number}, {payment_amount}, {due_date}, {product_name}</code>'),
                'placeholder' => $this->get_default_subject(),
                'default' => $this->get_default_subject(),
                'desc_tip' => true,
            ),
            'heading' => array(
                'title' => __('Email Heading', 'wc-flex-pay'),
                'type' => 'text',
                'description' => sprintf(__('Available placeholders: %s', 'wc-flex-pay'), '<code>{order_number}, {installment_number}, {payment_amount}, {due_date}, {product_name}</code>'),
                'placeholder' => $this->get_default_heading(),
                'default' => $this->get_default_heading(),
                'desc_tip' => true,
            ),
            'additional_content' => array(
                'title' => __('Additional content', 'wc-flex-pay'),
                'type' => 'textarea',
                'description' => __('Text to appear below the main email content.', 'wc-flex-pay'),
                'placeholder' => __('Enter any additional content here.', 'wc-flex-pay'),
                'default' => '',
                'desc_tip' => true,
            ),
            'email_type' => array(
                'title' => __('Email type', 'wc-flex-pay'),
                'type' => 'select',
                'description' => __('Choose which format of email to send.', 'wc-flex-pay'),
                'default' => 'html',
                'class' => 'email_type wc-enhanced-select',
                'options' => array(
                    'plain' => __('Plain text', 'wc-flex-pay'),
                    'html' => __('HTML', 'wc-flex-pay'),
                    'multipart' => __('Multipart', 'wc-flex-pay'),
                ),
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Get content html
     *
     * @return string
     */
    public function get_content_html() {
        $template = $this->get_template_html();
        if (!file_exists($template)) {
            return '';
        }
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
        $template = $this->get_template_plain();
        if (!file_exists($template)) {
            return '';
        }
        return wc_get_template_html(
            $this->template_plain,
            $this->get_template_args(true),
            '',
            $this->template_base
        );
    }

    /**
     * Get template html path
     *
     * @return string
     */
    protected function get_template_html() {
        return $this->template_base . $this->template_html;
    }

    /**
     * Get template plain path
     *
     * @return string
     */
    protected function get_template_plain() {
        return $this->template_base . $this->template_plain;
    }

    /**
     * Get email subject with placeholders replaced
     *
     * @return string
     */
    public function get_subject() {
        $subject = $this->get_option('subject', $this->get_default_subject());
        return $this->format_string($subject);
    }

    /**
     * Get email heading with placeholders replaced
     *
     * @return string
     */
    public function get_heading() {
        $heading = $this->get_option('heading', $this->get_default_heading());
        return $this->format_string($heading);
    }

    /**
     * Get setting value with default
     *
     * @param string $key Setting key
     * @param mixed  $empty_value Value to return if setting is empty
     * @return string
     */
    public function get_option($key, $empty_value = null) {
        $value = parent::get_option($key, $empty_value);
        return is_null($value) ? '' : $value;
    }

    /**
     * Get recipient
     *
     * @return string
     */
    public function get_recipient() {
        $recipient = parent::get_recipient();
        return is_null($recipient) ? '' : $recipient;
    }

    /**
     * Get default subject
     *
     * @return string
     */
    public function get_default_subject() {
        // This should be overridden by child classes
        return '';
    }

    /**
     * Get default heading
     *
     * @return string
     */
    public function get_default_heading() {
        // This should be overridden by child classes
        return '';
    }
}
