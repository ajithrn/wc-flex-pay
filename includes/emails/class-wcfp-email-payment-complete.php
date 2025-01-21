<?php
/**
 * Class WCFP_Email_Payment_Complete file
 *
 * @package WC_Flex_Pay\Emails
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WCFP_Email_Payment_Complete', false)) :

    /**
     * Payment Complete Email
     *
     * An email sent to the customer when a flex pay payment is completed.
     *
     * @class       WCFP_Email_Payment_Complete
     * @extends     WC_Email
     */
    class WCFP_Email_Payment_Complete extends WC_Email {

        /**
         * Payment ID
         *
         * @var int
         */
        public $payment_id;

        /**
         * Payment data
         *
         * @var array
         */
        public $payment;

        /**
         * Constructor
         */
        public function __construct() {
            $this->id             = 'wcfp_payment_complete';
            $this->customer_email = true;
            $this->title          = __('Flex Pay Payment Complete', 'wc-flex-pay');
            $this->description    = __('Payment complete emails are sent when a flex pay installment payment is successfully processed.', 'wc-flex-pay');
            $this->template_base  = WCFP_PLUGIN_DIR . 'templates/';
            $this->template_html  = 'emails/payment-complete.php';
            $this->template_plain = 'emails/plain/payment-complete.php';
            $this->placeholders   = array(
                '{site_title}'    => $this->get_blogname(),
                '{order_date}'    => '',
                '{order_number}'  => '',
                '{payment_amount}'=> '',
                '{payment_date}'  => '',
                '{payment_method}'=> '',
                '{customer_name}' => '',
                '{remaining_amount}' => '',
                '{next_payment_date}' => '',
                '{next_payment_amount}' => '',
            );

            // Call parent constructor.
            parent::__construct();
        }

        /**
         * Check if required tables exist
         *
         * @return bool
         */
        private function tables_exist() {
            global $wpdb;
            
            $table = $wpdb->prefix . 'wcfp_order_payments';
            return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
        }

        /**
         * Get email subject.
         *
         * @return string
         */
        public function get_default_subject() {
            return __('[{site_title}] Payment of {payment_amount} received for order #{order_number}', 'wc-flex-pay');
        }

        /**
         * Get email heading.
         *
         * @return string
         */
        public function get_default_heading() {
            return __('Payment of {payment_amount} Received', 'wc-flex-pay');
        }

        /**
         * Trigger the sending of this email.
         *
         * @param int      $payment_id The payment ID.
         * @param WC_Order $order      Order object.
         * @return bool
         */
        public function trigger($payment_id, $order) {
            try {
                $this->setup_locale();

                if (!$payment_id || !is_numeric($payment_id)) {
                    throw new \Exception('Invalid payment ID');
                }

                if (!$order instanceof \WC_Order) {
                    throw new \Exception('Invalid order object');
                }

                $this->object = $order;
                $this->payment_id = $payment_id;
                $this->payment = $this->get_payment($payment_id);

                if (!$this->payment) {
                    throw new \Exception('Payment not found');
                }

                $this->recipient = $order->get_billing_email();
                if (!$this->recipient) {
                    throw new \Exception('No recipient email found');
                }

                // Get next payment details
                $payment_manager = new \WCFP\Payment();
                $next_payment_id = $payment_manager->get_next_pending_payment($order->get_id());
                $next_payment = $next_payment_id ? $payment_manager->get_payment($next_payment_id) : null;

                // Set placeholders
                $this->placeholders['{order_date}'] = wc_format_datetime($order->get_date_created());
                $this->placeholders['{order_number}'] = $order->get_order_number();
                $this->placeholders['{payment_amount}'] = wc_price($this->payment['amount']);
                $this->placeholders['{payment_date}'] = date_i18n(get_option('date_format'), current_time('timestamp'));
                $this->placeholders['{payment_method}'] = $order->get_payment_method_title();
                $this->placeholders['{customer_name}'] = $order->get_billing_first_name();
                
                if ($next_payment) {
                    $this->placeholders['{next_payment_date}'] = date_i18n(get_option('date_format'), strtotime($next_payment['due_date']));
                    $this->placeholders['{next_payment_amount}'] = wc_price($next_payment['amount']);
                }

                if ($this->is_enabled() && $this->get_recipient()) {
                    return $this->send(
                        $this->get_recipient(),
                        $this->get_subject(),
                        $this->get_content(),
                        $this->get_headers(),
                        $this->get_attachments()
                    );
                }

                return false;
            } catch (\Exception $e) {
                if (defined('WCFP_DEBUG') && WCFP_DEBUG) {
                    error_log(sprintf(
                        '[WC Flex Pay] Failed to send payment complete email: %s | Payment ID: %d | Order ID: %d',
                        $e->getMessage(),
                        $payment_id,
                        $order ? $order->get_id() : 0
                    ));
                }
                return false;
            } finally {
                $this->restore_locale();
            }
        }

        /**
         * Get payment details
         *
         * @param int $payment_id Payment ID.
         * @return array|null
         */
        private function get_payment($payment_id) {
            if (!$this->tables_exist()) {
                return null;
            }

            global $wpdb;

            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wcfp_order_payments WHERE id = %d AND 1=%d",
                    $payment_id,
                    1
                ),
                ARRAY_A
            );
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
                    'order'         => $this->object,
                    'payment'       => $this->payment,
                    'email_heading' => $this->get_heading(),
                    'sent_to_admin' => false,
                    'plain_text'    => false,
                    'email'         => $this,
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
                    'order'         => $this->object,
                    'payment'       => $this->payment,
                    'email_heading' => $this->get_heading(),
                    'sent_to_admin' => false,
                    'plain_text'    => true,
                    'email'         => $this,
                ),
                '',
                $this->template_base
            );
        }

        /**
         * Initialize Settings Form Fields
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled'    => array(
                    'title'   => __('Enable/Disable', 'wc-flex-pay'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable this email notification', 'wc-flex-pay'),
                    'default' => 'yes',
                ),
                'subject'    => array(
                    'title'       => __('Subject', 'wc-flex-pay'),
                    'type'        => 'text',
                    'description' => sprintf(
                        __('Available placeholders: %s', 'wc-flex-pay'),
                        '<code>' . implode(', ', array_keys($this->placeholders)) . '</code>'
                    ),
                    'placeholder' => $this->get_default_subject(),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'heading'    => array(
                    'title'       => __('Email Heading', 'wc-flex-pay'),
                    'type'        => 'text',
                    'description' => sprintf(
                        __('Available placeholders: %s', 'wc-flex-pay'),
                        '<code>' . implode(', ', array_keys($this->placeholders)) . '</code>'
                    ),
                    'placeholder' => $this->get_default_heading(),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'email_type' => array(
                    'title'       => __('Email type', 'wc-flex-pay'),
                    'type'        => 'select',
                    'description' => __('Choose which format of email to send.', 'wc-flex-pay'),
                    'default'     => 'html',
                    'class'       => 'email_type wc-enhanced-select',
                    'options'     => $this->get_email_type_options(),
                    'desc_tip'    => true,
                ),
                'additional_content' => array(
                    'title'       => __('Additional content', 'wc-flex-pay'),
                    'description' => __('Text to appear below the main email content.', 'wc-flex-pay'),
                    'css'         => 'width:400px; height: 75px;',
                    'placeholder' => __('N/A', 'wc-flex-pay'),
                    'type'        => 'textarea',
                    'default'     => $this->get_default_additional_content(),
                    'desc_tip'    => true,
                ),
            );
        }

        /**
         * Get default additional content.
         *
         * @return string
         */
        public function get_default_additional_content() {
            return __('Thank you for choosing our flexible payment option.', 'wc-flex-pay');
        }
    }

endif;

return new WCFP_Email_Payment_Complete();
