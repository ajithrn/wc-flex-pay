<?php
/**
 * Installation related functions and actions
 *
 * @package WC_Flex_Pay
 */

namespace WCFP;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Install Class
 */
class Install {

    /**
     * DB updates and callbacks that need to be run per version.
     *
     * @var array
     */
    private static $db_updates = array(
        '1.0.0' => array(
            'add_capabilities',
            'set_default_options',
        ),
    );

    /**
     * Add plugin capabilities
     */
    public static function add_capabilities() {
        global $wp_roles;

        if (!class_exists('WP_Roles')) {
            return;
        }

        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }

        $capabilities = self::get_core_capabilities();

        foreach ($capabilities as $cap_group) {
            foreach ($cap_group as $cap) {
                $wp_roles->add_cap('administrator', $cap);
                $wp_roles->add_cap('shop_manager', $cap);
            }
        }
    }

    /**
     * Get capabilities for WC Flex Pay
     *
     * @return array
     */
    private static function get_core_capabilities() {
        $capabilities = array();

        $capabilities['core'] = array(
            'manage_wc_flex_pay',
            'view_wc_flex_pay_reports',
            'export_wc_flex_pay_reports',
        );

        $capability_types = array(
            'wc_flex_pay_payment',
            'wc_flex_pay_schedule',
        );

        foreach ($capability_types as $capability_type) {
            $capabilities[$capability_type] = array(
                "edit_{$capability_type}",
                "read_{$capability_type}",
                "delete_{$capability_type}",
                "edit_{$capability_type}s",
                "edit_others_{$capability_type}s",
                "publish_{$capability_type}s",
                "read_private_{$capability_type}s",
                "delete_{$capability_type}s",
                "delete_private_{$capability_type}s",
                "delete_published_{$capability_type}s",
                "delete_others_{$capability_type}s",
                "edit_private_{$capability_type}s",
                "edit_published_{$capability_type}s",
            );
        }

        return $capabilities;
    }

    /**
     * Set default options
     */
    public static function set_default_options() {
        $options = array(
            'wcfp_version' => WCFP_VERSION,
            'wcfp_db_version' => WCFP_VERSION,
            'wcfp_enable_notifications' => 'yes',
            'wcfp_reminder_days' => 3,
            'wcfp_overdue_grace_period' => 3,
            'wcfp_payment_status_colors' => array(
                'pending' => '#ffba00',
                'processing' => '#73a724',
                'completed' => '#2ea2cc',
                'failed' => '#a00',
                'overdue' => '#d63638',
            ),
        );

        foreach ($options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Update DB version to current
     *
     * @param string $version
     */
    private static function update_db_version($version = null) {
        update_option('wcfp_db_version', is_null($version) ? WCFP_VERSION : $version);
    }

    /**
     * Get list of DB update callbacks
     *
     * @return array
     */
    public static function get_db_update_callbacks() {
        return self::$db_updates;
    }
}
