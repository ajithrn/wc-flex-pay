<?php
/**
 * Class WCFP_Email_Payment_Overdue file
 *
 * @package WC_Flex_Pay\Emails
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WCFP_Email_Payment_Overdue', false)) :

    /**
     * Payment Overdue Email
     *
     * An email sent to the customer when a flex pay payment is overdue.
     *
     * @class       WCFP_Email_Payment_Overdue
     * @extends     WC_Email
     */
    class WCFP_Email_Payment_Overdue extends WC_Email {

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
            $this->id             = 'wcfp_payment_overdue';
            $this->customer_email = true;
            $this->title          = __('Flex Pay Payment Overdue', 'wc-flex-pay');
            $this->description    = __('Payment overdue emails are sent when a flex pay installment payment becomes overdue.', 'wc-flex-pay');
            $this->template_base  = WCFP_PLUGIN_DIR . 'templates/';
            $this->template_html  = 'emails/payment-overdue.php';
            $this->template_plain = 'emails/plain/payment-overdue.php';
            $this->placeholders   = array(
                '{site_title}'    => $this->get_blogname(),
                '{order_date}'    => '',
                '{order_number}'  => '',
                '{payment_amount}'=> '',
                '{payment_date}'  => '',
                '{payment_method}'=> '',
                '{customer_name}' => '',
                '{days_overdue}'  => '',
                '{days_remaining}'=> '',
                '{total_remaining}'=> '',
                '{error_message}' => '',
                '{retry_url}'     => '',
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
            return __('[{site_title}] URGENT: Payment of {payment_amount} is {days_overdue} days overdue - Order #{order_number}', 'wc-flex-pay');
        }

        /**
         * Get email heading.
         *
         * @return string
         */
        public function get_default_heading() {
            return __('Payment Overdue - Immediate Action Required', 'wc-flex-pay');
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

                // Get payment details
                $payment_manager = new \WCFP\Payment();
                $remaining_payments = $payment_manager->get_order_payments($order->get_id());
                $remaining_payments = array_filter($remaining_payments, function($p) {
                    return $p['status'] === 'pending';
                });

                $total_remaining = array_sum(array_column($remaining_payments, 'amount'));
                $days_overdue = floor((current_time('timestamp') - strtotime($this->payment['due_date'])) / (60 * 60 * 24));
                $grace_period = absint(get_option('wcfp_overdue_grace_period', 3));
                $days_remaining = $grace_period - $days_overdue;

                // Set placeholders
                $retry_url = add_query_arg(array(
                    'retry_payment' => $payment_id,
                    'order_key' => $order->get_order_key(),
                ), $order->get_checkout_payment_url());

                $this->placeholders['{order_date}'] = wc_format_datetime($order->get_date_created());
                $this->placeholders['{order_number}'] = $order->get_order_number();
                $this->placeholders['{payment_amount}'] = wc_price($this->payment['amount']);
                $this->placeholders['{payment_date}'] = date_i18n(get_option('date_format'), strtotime($this->payment['due_date']));
                $this->placeholders['{payment_method}'] = $order->get_payment_method_title();
                $this->placeholders['{customer_name}'] = $order->get_billing_first_name();
                $this->placeholders['{days_overdue}'] = $days_overdue;
                $this->placeholders['{days_remaining}'] = $days_remaining;
                $this->placeholders['{total_remaining}'] = wc_price($total_remaining);
                $this->placeholders['{error_message}'] = !empty($this->payment['error_message']) ? $this->payment['error_message'] : '';
                $this->placeholders['{retry_url}'] = $retry_url;

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
                        '[WC Flex Pay] Failed to send payment overdue email: %s | Payment ID: %d | Order ID: %d',
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
                'send_after' => array(
                    'title'       => __('Send After', 'wc-flex-pay'),
                    'type'        => 'number',
                    'description' => __('Number of days after due date to send this notice.', 'wc-flex-pay'),
                    'default'     => '1',
                    'css'         => 'width:50px;',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'max'  => '30',
                        'step' => '1',
                    ),
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
            return __('If you need assistance or have any questions, please contact our support team immediately.', 'wc-flex-pay');
        }
    }

endif;

return new WCFP_Email_Payment_Overdue();
