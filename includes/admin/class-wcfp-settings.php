<?php
/**
 * WC Flex Pay Settings
 *
 * @package WC_Flex_Pay\Admin
 */

namespace WCFP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Class
 */
class Settings extends \WC_Settings_Page {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id    = 'wcfp_settings';
        $this->label = __('Flex Pay', 'wc-flex-pay');

        parent::__construct();
    }

    /**
     * Get sections.
     *
     * @return array
     */
    public function get_sections() {
        $sections = array(
            ''         => __('General', 'wc-flex-pay'),
            'payment'  => __('Payment', 'wc-flex-pay'),
            'email'    => __('Email', 'wc-flex-pay'),
            'advanced' => __('Advanced', 'wc-flex-pay'),
        );

        return apply_filters('wcfp_get_sections', $sections);
    }

    /**
     * Get settings array.
     *
     * @param string $current_section
     * @return array
     */
    public function get_settings($current_section = '') {
        $settings = array();

        if ('' === $current_section) {
            $settings = array(
                array(
                    'title' => __('General Settings', 'wc-flex-pay'),
                    'type'  => 'title',
                    'desc'  => '',
                    'id'    => 'wcfp_general_settings',
                ),
                array(
                    'title'    => __('Enable/Disable', 'wc-flex-pay'),
                    'desc'     => __('Enable Flex Pay', 'wc-flex-pay'),
                    'id'       => 'wcfp_enabled',
                    'default'  => 'yes',
                    'type'     => 'checkbox',
                ),
                array(
                    'title'    => __('Test Mode', 'wc-flex-pay'),
                    'desc'     => __('Enable test mode', 'wc-flex-pay'),
                    'id'       => 'wcfp_test_mode',
                    'default'  => 'no',
                    'type'     => 'checkbox',
                ),
                array(
                    'title'    => __('Debug Log', 'wc-flex-pay'),
                    'desc'     => __('Enable logging', 'wc-flex-pay'),
                    'id'       => 'wcfp_debug_enabled',
                    'default'  => 'no',
                    'type'     => 'checkbox',
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wcfp_general_settings',
                ),
            );
        } elseif ('payment' === $current_section) {
            $settings = array(
                array(
                    'title' => __('Payment Settings', 'wc-flex-pay'),
                    'type'  => 'title',
                    'desc'  => '',
                    'id'    => 'wcfp_payment_settings',
                ),
                array(
                    'title'    => __('Payment Grace Periods', 'wc-flex-pay'),
                    'type'     => 'title',
                    'desc'     => __('Configure grace periods for different payment scenarios', 'wc-flex-pay'),
                ),
                array(
                    'title'    => __('Standard Grace Period', 'wc-flex-pay'),
                    'desc'     => __('Number of days after due date before marking payment as overdue', 'wc-flex-pay'),
                    'id'       => 'wcfp_overdue_grace_period',
                    'default'  => '3',
                    'type'     => 'number',
                    'css'      => 'width:50px;',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'step' => '1',
                    ),
                ),
                array(
                    'title'    => __('Extended Grace Period', 'wc-flex-pay'),
                    'desc'     => __('Number of days to extend payment link validity for overdue payments', 'wc-flex-pay'),
                    'id'       => 'wcfp_extended_grace_period',
                    'default'  => '7',
                    'type'     => 'number',
                    'css'      => 'width:50px;',
                    'custom_attributes' => array(
                        'min'  => '1',
                        'step' => '1',
                    ),
                ),
                array(
                    'title'    => __('Payment Status Colors', 'wc-flex-pay'),
                    'desc'     => __('Customize the colors used for different payment statuses', 'wc-flex-pay'),
                    'id'       => 'wcfp_payment_status_colors',
                    'default'  => array(
                        'pending'    => '#ffba00',
                        'processing' => '#73a724',
                        'completed'  => '#2ea2cc',
                        'failed'     => '#a00',
                        'overdue'    => '#d63638',
                    ),
                    'type'     => 'color_group',
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wcfp_payment_settings',
                ),
            );
        } elseif ('email' === $current_section) {
            $settings = array(
                array(
                    'title' => __('Email Settings', 'wc-flex-pay'),
                    'type'  => 'title',
                    'desc'  => '',
                    'id'    => 'wcfp_email_settings',
                ),
                array(
                    'title'    => __('Customer Notifications', 'wc-flex-pay'),
                    'type'     => 'title',
                    'desc'     => __('Configure customer email notifications', 'wc-flex-pay'),
                ),
                array(
                    'title'    => __('Enable Customer Notifications', 'wc-flex-pay'),
                    'desc'     => __('Enable email notifications for customers', 'wc-flex-pay'),
                    'id'       => 'wcfp_enable_customer_notifications',
                    'default'  => 'yes',
                    'type'     => 'checkbox',
                ),
                array(
                    'title'    => __('Customer Email Events', 'wc-flex-pay'),
                    'desc'     => __('Select which events trigger customer notifications', 'wc-flex-pay'),
                    'id'       => 'wcfp_customer_notification_events',
                    'default'  => array('payment_complete', 'payment_reminder', 'payment_overdue'),
                    'type'     => 'multiselect',
                    'class'    => 'wc-enhanced-select',
                    'options'  => array(
                        'payment_complete'  => __('Payment Complete', 'wc-flex-pay'),
                        'payment_reminder'  => __('Payment Reminder', 'wc-flex-pay'),
                        'payment_overdue'   => __('Payment Overdue', 'wc-flex-pay'),
                        'payment_failed'    => __('Payment Failed', 'wc-flex-pay'),
                    ),
                ),
                array(
                    'title'    => __('Payment Reminders', 'wc-flex-pay'),
                    'type'     => 'title',
                    'desc'     => __('Configure payment reminder settings', 'wc-flex-pay'),
                ),
                array(
                    'title'    => __('Initial Reminder', 'wc-flex-pay'),
                    'desc'     => __('Days before due date to send first reminder', 'wc-flex-pay'),
                    'id'       => 'wcfp_reminder_days',
                    'default'  => '3',
                    'type'     => 'number',
                    'css'      => 'width:50px;',
                    'custom_attributes' => array(
                        'min'  => '1',
                        'step' => '1',
                    ),
                ),
                array(
                    'title'    => __('Follow-up Reminder', 'wc-flex-pay'),
                    'desc'     => __('Days before due date to send follow-up reminder', 'wc-flex-pay'),
                    'id'       => 'wcfp_followup_reminder_days',
                    'default'  => '1',
                    'type'     => 'number',
                    'css'      => 'width:50px;',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'step' => '1',
                    ),
                ),
                array(
                    'title'    => __('Email Override Settings', 'wc-flex-pay'),
                    'type'     => 'title',
                    'desc'     => __('Configure WooCommerce email override settings', 'wc-flex-pay'),
                ),
                array(
                    'title'    => __('Override WooCommerce Emails', 'wc-flex-pay'),
                    'desc'     => __('Replace standard WooCommerce emails with Flex Pay versions for installment orders', 'wc-flex-pay'),
                    'id'       => 'wcfp_override_wc_emails',
                    'default'  => 'yes',
                    'type'     => 'checkbox',
                ),
                array(
                    'title'    => __('Email Overrides', 'wc-flex-pay'),
                    'desc'     => __('Select which WooCommerce emails to override', 'wc-flex-pay'),
                    'id'       => 'wcfp_email_overrides',
                    'default'  => array('order_complete', 'order_processing', 'order_on_hold'),
                    'type'     => 'multiselect',
                    'class'    => 'wc-enhanced-select',
                    'options'  => array(
                        'order_complete'    => __('Order Complete', 'wc-flex-pay'),
                        'order_processing'  => __('Order Processing', 'wc-flex-pay'),
                        'order_on_hold'     => __('Order On Hold', 'wc-flex-pay'),
                        'order_failed'      => __('Order Failed', 'wc-flex-pay'),
                    ),
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wcfp_email_override_settings',
                ),
                array(
                    'title'    => __('Admin Notifications', 'wc-flex-pay'),
                    'type'     => 'title',
                    'desc'     => __('Configure admin notification settings', 'wc-flex-pay'),
                ),
                array(
                    'title'    => __('Enable Admin Notifications', 'wc-flex-pay'),
                    'desc'     => __('Enable admin notifications', 'wc-flex-pay'),
                    'id'       => 'wcfp_admin_notifications',
                    'default'  => 'yes',
                    'type'     => 'checkbox',
                ),
                array(
                    'title'    => __('Admin Email', 'wc-flex-pay'),
                    'desc'     => __('Email address for admin notifications', 'wc-flex-pay'),
                    'id'       => 'wcfp_admin_email',
                    'default'  => get_option('admin_email'),
                    'type'     => 'email',
                    'css'      => 'width:300px;',
                ),
                array(
                    'title'    => __('Notification Events', 'wc-flex-pay'),
                    'desc'     => __('Select which events trigger admin notifications', 'wc-flex-pay'),
                    'id'       => 'wcfp_admin_notification_events',
                    'default'  => array('payment_failed', 'payment_overdue'),
                    'type'     => 'multiselect',
                    'class'    => 'wc-enhanced-select',
                    'options'  => array(
                        'payment_failed'    => __('Payment Failed', 'wc-flex-pay'),
                        'payment_overdue'   => __('Payment Overdue', 'wc-flex-pay'),
                        'payment_complete'  => __('Payment Complete', 'wc-flex-pay'),
                        'payment_reminder'  => __('Payment Reminder Sent', 'wc-flex-pay'),
                    ),
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wcfp_email_settings',
                ),
            );
        } elseif ('advanced' === $current_section) {
            $settings = array(
                array(
                    'title' => __('Advanced Settings', 'wc-flex-pay'),
                    'type'  => 'title',
                    'desc'  => '',
                    'id'    => 'wcfp_advanced_settings',
                ),
                array(
                    'title'    => __('Delete Data', 'wc-flex-pay'),
                    'desc'     => __('Delete all plugin data on uninstall', 'wc-flex-pay'),
                    'id'       => 'wcfp_delete_data',
                    'default'  => 'no',
                    'type'     => 'checkbox',
                ),
                array(
                    'title'    => __('Cache Lifetime', 'wc-flex-pay'),
                    'desc'     => __('Cache lifetime in seconds (0 to disable)', 'wc-flex-pay'),
                    'id'       => 'wcfp_cache_lifetime',
                    'default'  => '3600',
                    'type'     => 'number',
                    'css'      => 'width:100px;',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'step' => '1',
                    ),
                ),
                array(
                    'title'    => __('API Timeout', 'wc-flex-pay'),
                    'desc'     => __('API request timeout in seconds', 'wc-flex-pay'),
                    'id'       => 'wcfp_api_timeout',
                    'default'  => '30',
                    'type'     => 'number',
                    'css'      => 'width:100px;',
                    'custom_attributes' => array(
                        'min'  => '1',
                        'step' => '1',
                    ),
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wcfp_advanced_settings',
                ),
            );
        }

        return apply_filters('wcfp_get_settings_' . $this->id, $settings, $current_section);
    }

    /**
     * Output the settings.
     */
    public function output() {
        global $current_section;

        $settings = $this->get_settings($current_section);
        \WC_Admin_Settings::output_fields($settings);
    }

    /**
     * Save settings.
     */
    public function save() {
        global $current_section;

        $settings = $this->get_settings($current_section);
        \WC_Admin_Settings::save_fields($settings);

        if ($current_section) {
            do_action('wcfp_update_options_' . $this->id . '_' . $current_section);
        }
    }
}

return new Settings();
