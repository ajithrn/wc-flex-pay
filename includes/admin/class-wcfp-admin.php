<?php
/**
 * Admin related functions and actions
 *
 * @package WC_Flex_Pay\Admin
 */

namespace WCFP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Class
 */
class Admin {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_menu_items'));
        
        // Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Settings
        add_filter('woocommerce_get_settings_pages', array($this, 'add_settings_page'));
        
        // Product meta box
        add_action('woocommerce_product_data_tabs', array($this, 'add_product_data_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_product_data_panel'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'));
        
        // Order meta box
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // AJAX handlers
        add_action('wp_ajax_wcfp_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_wcfp_update_schedule', array($this, 'ajax_update_schedule'));
        add_action('wp_ajax_wcfp_bulk_edit_schedules', array($this, 'ajax_bulk_edit_schedules'));
    }

    /**
     * Add menu items
     */
    public function add_menu_items() {
        add_submenu_page(
            'woocommerce',
            __('Flex Pay', 'wc-flex-pay'),
            __('Flex Pay', 'wc-flex-pay'),
            'manage_woocommerce',
            'wc-flex-pay',
            array($this, 'render_dashboard_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();

        // Only enqueue on our pages
        if (strpos($screen->id, 'wc-flex-pay') === false) {
            return;
        }

        wp_enqueue_style(
            'wcfp-admin',
            WCFP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WCFP_VERSION
        );

        wp_enqueue_script(
            'wcfp-admin',
            WCFP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-datepicker'),
            WCFP_VERSION,
            true
        );

        wp_localize_script(
            'wcfp-admin',
            'wcfp_admin_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('wcfp-admin'),
                'i18n'     => array(
                    'confirm_process' => __('Are you sure you want to process this payment?', 'wc-flex-pay'),
                    'confirm_update'  => __('Are you sure you want to update this schedule?', 'wc-flex-pay'),
                    'error'          => __('An error occurred. Please try again.', 'wc-flex-pay'),
                ),
            )
        );
    }

    /**
     * Add settings page
     */
    public function add_settings_page($settings) {
        $settings[] = include WCFP_PLUGIN_DIR . 'includes/admin/class-wcfp-settings.php';
        return $settings;
    }

    /**
     * Add product data tab
     */
    public function add_product_data_tab($tabs) {
        $tabs['flex_pay'] = array(
            'label'    => __('Flex Pay', 'wc-flex-pay'),
            'target'   => 'flex_pay_product_data',
            'class'    => array('show_if_simple', 'show_if_variable'),
            'priority' => 21,
        );
        return $tabs;
    }

    /**
     * Add product data panel
     */
    public function add_product_data_panel() {
        global $post;

        $product = wc_get_product($post);
        if (!$product) {
            return;
        }

        include WCFP_PLUGIN_DIR . 'templates/admin/product-data-panel.php';
    }

    /**
     * Save product meta
     */
    public function save_product_meta($post_id) {
        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        // Save Flex Pay settings
        $enabled = isset($_POST['_wcfp_enabled']) ? 'yes' : 'no';
        $product->update_meta_data('_wcfp_enabled', $enabled);

        if (isset($_POST['wcfp_schedule']) && is_array($_POST['wcfp_schedule'])) {
            $this->save_payment_schedule($post_id, $_POST['wcfp_schedule']);
        }

        $product->save();
    }

    /**
     * Add order meta box
     */
    public function add_order_meta_box() {
        add_meta_box(
            'wcfp-order-payments',
            __('Flex Pay Payments', 'wc-flex-pay'),
            array($this, 'render_order_meta_box'),
            'shop_order',
            'normal',
            'high'
        );
    }

    /**
     * Render order meta box
     */
    public function render_order_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }

        include WCFP_PLUGIN_DIR . 'templates/admin/order-meta-box.php';
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        if (!$this->tables_exist()) {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('WC Flex Pay database tables are not set up properly. Please deactivate and reactivate the plugin.', 'wc-flex-pay'); ?></p>
            </div>
            <?php
            return;
        }

        include WCFP_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Check if required tables exist
     */
    private function tables_exist() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'wcfp_payment_schedules',
            $wpdb->prefix . 'wcfp_order_payments',
            $wpdb->prefix . 'wcfp_payment_logs'
        );

        foreach ($required_tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get payment data for reports
     */
    private function get_payment_report_data() {
        global $wpdb;

        return $wpdb->get_results("
            SELECT 
                p.*, 
                o.ID as order_id,
                o.post_status as order_status,
                pm1.meta_value as _billing_first_name,
                pm2.meta_value as _billing_last_name,
                pm3.meta_value as _billing_email,
                pm4.meta_value as _payment_method_title
            FROM {$wpdb->prefix}wcfp_order_payments p
            LEFT JOIN {$wpdb->posts} o ON p.order_id = o.ID
            LEFT JOIN {$wpdb->postmeta} pm1 ON o.ID = pm1.post_id AND pm1.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} pm2 ON o.ID = pm2.post_id AND pm2.meta_key = '_billing_last_name'
            LEFT JOIN {$wpdb->postmeta} pm3 ON o.ID = pm3.post_id AND pm3.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm4 ON o.ID = pm4.post_id AND pm4.meta_key = '_payment_method_title'
            ORDER BY p.due_date ASC
        ", ARRAY_A);
    }

    /**
     * Get CSS class for payment status
     */
    private function get_status_class($status) {
        $classes = array(
            'pending' => 'pending',
            'completed' => 'completed',
            'overdue' => 'overdue',
            'failed' => 'failed',
            'processing' => 'pending'
        );
        return isset($classes[$status]) ? $classes[$status] : '';
    }

    /**
     * Get order payments
     */
    private function get_order_payments($order_id) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcfp_order_payments WHERE order_id = %d ORDER BY due_date ASC",
                $order_id
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Log payment action
     */
    private function log_payment($payment_id, $message) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'wcfp_payment_logs',
            array(
                'payment_id' => $payment_id,
                'type'      => 'info',
                'message'   => $message,
            ),
            array('%d', '%s', '%s')
        );
    }

    /**
     * Save payment schedule
     */
    private function save_payment_schedule($product_id, $schedule) {
        if (!$this->tables_exist()) {
            throw new \Exception(__('Database tables not found.', 'wc-flex-pay'));
        }

        global $wpdb;

        // Delete existing schedule
        $result = $wpdb->delete(
            $wpdb->prefix . 'wcfp_payment_schedules',
            array('product_id' => $product_id),
            array('%d')
        );

        if ($result === false) {
            throw new \Exception($wpdb->last_error ?: __('Failed to delete existing schedule.', 'wc-flex-pay'));
        }

        // Insert new schedule
        foreach ($schedule as $installment_number => $item) {
            if (empty($item['amount']) || empty($item['due_date'])) {
                continue;
            }

            $result = $wpdb->insert(
                $wpdb->prefix . 'wcfp_payment_schedules',
                array(
                    'product_id'         => $product_id,
                    'installment_number' => absint($installment_number),
                    'amount'             => wc_format_decimal($item['amount']),
                    'due_date'           => sanitize_text_field($item['due_date']),
                ),
                array('%d', '%d', '%f', '%s')
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error ?: __('Failed to insert schedule.', 'wc-flex-pay'));
            }
        }
    }

    /**
     * Process payment via AJAX
     */
    public function ajax_process_payment() {
        check_ajax_referer('wcfp-admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'wc-flex-pay'));
        }

        $payment_id = isset($_POST['payment_id']) ? absint($_POST['payment_id']) : 0;
        if (!$payment_id) {
            wp_send_json_error(__('Invalid payment ID.', 'wc-flex-pay'));
        }

        try {
            do_action('wcfp_process_payment', $payment_id);
            wp_send_json_success(__('Payment processed successfully.', 'wc-flex-pay'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Update schedule via AJAX
     */
    public function ajax_update_schedule() {
        check_ajax_referer('wcfp-admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'wc-flex-pay'));
        }

        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        if (!$schedule_id) {
            wp_send_json_error(__('Invalid schedule ID.', 'wc-flex-pay'));
        }

        try {
            global $wpdb;

            $data = array(
                'amount'   => isset($_POST['amount']) ? wc_format_decimal($_POST['amount']) : 0,
                'due_date' => isset($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : '',
            );

            $result = $wpdb->update(
                $wpdb->prefix . 'wcfp_payment_schedules',
                $data,
                array('id' => $schedule_id),
                array('%f', '%s'),
                array('%d')
            );

            if ($result === false) {
                throw new \Exception($wpdb->last_error ?: __('Failed to update schedule.', 'wc-flex-pay'));
            }

            wp_send_json_success(__('Schedule updated successfully.', 'wc-flex-pay'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Bulk edit schedules via AJAX
     */
    public function ajax_bulk_edit_schedules() {
        check_ajax_referer('wcfp-admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'wc-flex-pay'));
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('absint', $_POST['product_ids']) : array();
        if (empty($product_ids)) {
            wp_send_json_error(__('No products selected.', 'wc-flex-pay'));
        }

        try {
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                if (!$product) {
                    continue;
                }

                if (isset($_POST['schedule']) && is_array($_POST['schedule'])) {
                    $this->save_payment_schedule($product_id, $_POST['schedule']);
                }
            }

            wp_send_json_success(__('Schedules updated successfully.', 'wc-flex-pay'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (!$this->tables_exist()) {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('WC Flex Pay database tables are not set up properly. Please deactivate and reactivate the plugin.', 'wc-flex-pay'); ?></p>
            </div>
            <?php
            return;
        }

        global $wpdb;

        // Check for overdue payments
        $overdue_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wcfp_order_payments WHERE status = %s AND due_date < CURDATE()",
                'pending'
            )
        );

        if ($overdue_count > 0) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php
                    printf(
                        /* translators: %1$d: number of overdue payments, %2$s: link to dashboard */
                        esc_html__('You have %1$d overdue Flex Pay payment(s). View %2$sdashboard%3$s for details.', 'wc-flex-pay'),
                        esc_html($overdue_count),
                        '<a href="' . esc_url(admin_url('admin.php?page=wc-flex-pay')) . '">',
                        '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
}

return new Admin();
