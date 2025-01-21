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
                    'title'    => __('Grace Period', 'wc-flex-pay'),
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
                    'title'    => __('Enable Notifications', 'wc-flex-pay'),
                    'desc'     => __('Enable email notifications', 'wc-flex-pay'),
                    'id'       => 'wcfp_enable_notifications',
                    'default'  => 'yes',
                    'type'     => 'checkbox',
                ),
                array(
                    'title'    => __('Reminder Days', 'wc-flex-pay'),
                    'desc'     => __('Number of days before due date to send payment reminder', 'wc-flex-pay'),
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
                    'title'    => __('Admin Notifications', 'wc-flex-pay'),
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
