<?php
/**
 * Order Details Email Class
 *
 * @package WC_Flex_Pay\Emails
 */

namespace WCFP\Emails;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order Details Email
 *
 * An email sent to the customer with their Flex Pay order details.
 */
class Order_Details extends Email_Base {

    /**
     * Track sent orders to prevent duplicates
     *
     * @var array
     */
    private static $sent_orders = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'wcfp_order_details';
        $this->title = __('Flex Pay Order Details', 'wc-flex-pay');
        $this->description = __('Order details emails are sent to customers containing their Flex Pay payment schedule and status.', 'wc-flex-pay');

        parent::__construct(
            $this->id,
            $this->title,
            $this->description
        );

        // Triggers for this email
        add_action('wcfp_send_order_details_notification', array($this, 'trigger'), 10, 3);

        // Reset sent orders tracking daily
        add_action('wp_loaded', array($this, 'maybe_reset_sent_orders'));
    }

    /**
     * Reset sent orders tracking if it's a new day
     */
    public function maybe_reset_sent_orders() {
        $last_reset = get_transient('wcfp_order_details_last_reset');
        $today = date('Y-m-d');

        if ($last_reset !== $today) {
            self::$sent_orders = array();
            set_transient('wcfp_order_details_last_reset', $today, DAY_IN_SECONDS);
        }
    }

    /**
     * Get default subject
     *
     * @return string
     */
    public function get_default_subject() {
        return __('Your {site_title} Flex Pay order details', 'wc-flex-pay');
    }

    /**
     * Get default heading
     *
     * @return string
     */
    public function get_default_heading() {
        return __('Flex Pay Order Details', 'wc-flex-pay');
    }

    /**
     * Get default additional content
     *
     * @return string
     */
    public function get_default_additional_content() {
        return __('Thank you for choosing our Flex Pay payment option.', 'wc-flex-pay');
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        parent::init_form_fields();

        // Add custom fields
        $this->form_fields['trigger_send'] = array(
            'title' => __('Trigger Send', 'wc-flex-pay'),
            'type' => 'multiselect',
            'description' => __('Choose when this email should be sent.', 'wc-flex-pay'),
            'default' => array('manual', 'payment_complete'),
            'class' => 'wc-enhanced-select',
            'options' => array(
                'manual' => __('Manual send', 'wc-flex-pay'),
                'payment_complete' => __('After payment completion', 'wc-flex-pay'),
                'sub_order_create' => __('After sub-order creation', 'wc-flex-pay'),
            ),
        );
    }

    /**
     * Trigger the sending of this email.
     *
     * @param int   $order_id Order ID.
     * @param int   $installment_number Installment number.
     * @param array $payment_data Payment data.
     */
    public function trigger($order_id, $installment_number = 1, $payment_data = array()) {
        // Prevent duplicate sends for the same order
        if (isset(self::$sent_orders[$order_id])) {
            return;
        }

        $this->setup_locale();

        if ($order_id) {
            $this->object = wc_get_order($order_id);
            if (is_a($this->object, 'WC_Order')) {
                $this->recipient = $this->object->get_billing_email();
                $this->order_id = $order_id;
                $this->installment_number = $installment_number;
                $this->payment_data = $payment_data;

                $this->placeholders['{order_number}'] = $this->object->get_order_number();
                $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
                $this->placeholders['{site_title}'] = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

                // Send the email
                if ($this->is_enabled() && $this->get_recipient()) {
                    $sent = $this->send(
                        $this->get_recipient(),
                        $this->get_subject(),
                        $this->get_content(),
                        $this->get_headers(),
                        $this->get_attachments()
                    );

                    // Only mark as sent if email was sent successfully
                    if ($sent) {
                        self::$sent_orders[$order_id] = true;
                    }
                }
            }
        }

        $this->restore_locale();
    }

    /**
     * Get content html.
     *
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'installment_number' => $this->installment_number,
                'payment_data' => $this->payment_data,
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin' => false,
                'plain_text' => false,
                'email' => $this,
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Get content plain.
     *
     * @return string
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'installment_number' => $this->installment_number,
                'payment_data' => $this->payment_data,
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin' => false,
                'plain_text' => true,
                'email' => $this,
            ),
            '',
            $this->template_base
        );
    }
}

return new Order_Details();
