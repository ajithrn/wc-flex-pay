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
            'create_tables',
            'add_capabilities',
            'set_default_options',
        ),
        '1.1.0' => array(
            'update_payment_schedules_table',
        ),
    );

    /**
     * Create database tables
     *
     * @throws Exception If table creation fails
     */
    public static function create_tables() {
        global $wpdb;

        $wpdb->hide_errors();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $collate = '';

        if ($wpdb->has_cap('collation')) {
            $collate = $wpdb->get_charset_collate();
        }

        $tables = array();

        // Payment Schedules table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcfp_payment_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            installment_number INT NOT NULL,
            amount DECIMAL(19,4) NOT NULL,
            due_date DATE NOT NULL,
            description TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id)
        ) $collate;";

        // Order Payments table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcfp_order_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            schedule_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(19,4) NOT NULL,
            due_date DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            description TEXT,
            transaction_id VARCHAR(100),
            notes TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY schedule_id (schedule_id),
            KEY status (status)
        ) $collate;";

        // Payment Logs table
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcfp_payment_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY payment_id (payment_id),
            KEY type (type)
        ) $collate;";

        // Create tables
        foreach ($tables as $table) {
            $result = dbDelta($table);
            if (empty($result)) {
                throw new \Exception(sprintf(
                    __('Error creating table: %s', 'wc-flex-pay'),
                    $wpdb->last_error
                ));
            }
        }

        // Verify tables exist
        $required_tables = array(
            $wpdb->prefix . 'wcfp_payment_schedules',
            $wpdb->prefix . 'wcfp_order_payments',
            $wpdb->prefix . 'wcfp_payment_logs'
        );

        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                throw new \Exception(sprintf(
                    __('Table %s was not created successfully', 'wc-flex-pay'),
                    $table
                ));
            }
        }
    }

    /**
     * Update payment schedules table for version 1.1.0
     */
    public static function update_payment_schedules_table() {
        global $wpdb;

        // Add description column if it doesn't exist
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE table_name = '{$wpdb->prefix}wcfp_payment_schedules' AND column_name = 'description'");
            
        if (empty($row)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wcfp_payment_schedules 
                         ADD COLUMN description TEXT AFTER due_date");
        }

        // Change due_days to due_date if it exists
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE table_name = '{$wpdb->prefix}wcfp_payment_schedules' AND column_name = 'due_days'");
            
        if (!empty($row)) {
            // First, add the new column
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wcfp_payment_schedules 
                         ADD COLUMN due_date DATE AFTER amount");
            
            // Update the data
            $wpdb->query("UPDATE {$wpdb->prefix}wcfp_payment_schedules 
                         SET due_date = DATE_ADD(created_at, INTERVAL due_days DAY)");
            
            // Drop the old column
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wcfp_payment_schedules 
                         DROP COLUMN due_days");
        }

        // Add description to order_payments if it doesn't exist
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE table_name = '{$wpdb->prefix}wcfp_order_payments' AND column_name = 'description'");
            
        if (empty($row)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wcfp_order_payments 
                         ADD COLUMN description TEXT AFTER status");
        }
    }

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
