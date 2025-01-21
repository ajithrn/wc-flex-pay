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
        include WCFP_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Get payment data for reports
     */
    private function get_payment_report_data() {
        $payment_data = array();
        
        // Get all orders with flex pay payments
        $orders = wc_get_orders(array(
            'meta_key' => '_wcfp_payments',
            'limit' => -1,
        ));

        foreach ($orders as $order) {
            $payments = get_post_meta($order->get_id(), '_wcfp_payments', true);
            if (empty($payments)) continue;

            foreach ($payments as $payment_id => $payment) {
                $payment_data[] = array_merge(
                    $payment,
                    array(
                        'id' => $payment_id,
                        'order_id' => $order->get_id(),
                        'order_status' => $order->get_status(),
                        '_billing_first_name' => $order->get_billing_first_name(),
                        '_billing_last_name' => $order->get_billing_last_name(),
                        '_billing_email' => $order->get_billing_email(),
                        '_payment_method_title' => $order->get_payment_method_title(),
                    )
                );
            }
        }

        // Sort by due date
        usort($payment_data, function($a, $b) {
            return strtotime($a['due_date']) - strtotime($b['due_date']);
        });

        return $payment_data;
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
        $payments = get_post_meta($order_id, '_wcfp_payments', true);
        if (empty($payments)) {
            return array();
        }

        // Sort by due date
        uasort($payments, function($a, $b) {
            return strtotime($a['due_date']) - strtotime($b['due_date']);
        });

        return $payments;
    }

    /**
     * Log payment action
     */
    private function log_payment($payment_id, $message) {
        $orders = wc_get_orders(array(
            'meta_key' => '_wcfp_payments',
            'limit' => -1,
        ));

        foreach ($orders as $order) {
            $payments = get_post_meta($order->get_id(), '_wcfp_payments', true);
            if (!empty($payments) && isset($payments[$payment_id])) {
                $logs = get_post_meta($order->get_id(), '_wcfp_payment_logs', true);
                if (!is_array($logs)) {
                    $logs = array();
                }

                $logs[] = array(
                    'payment_id' => $payment_id,
                    'type' => 'info',
                    'message' => $message,
                    'created_at' => current_time('mysql')
                );

                update_post_meta($order->get_id(), '_wcfp_payment_logs', $logs);
                break;
            }
        }
    }

    /**
     * Save payment schedule
     */
    private function save_payment_schedule($product_id, $schedule) {
        $schedules = array();

        foreach ($schedule as $installment_number => $item) {
            if (empty($item['amount']) || empty($item['due_date'])) {
                continue;
            }

            $schedules[] = array(
                'installment_number' => absint($installment_number),
                'amount' => wc_format_decimal($item['amount']),
                'due_date' => sanitize_text_field($item['due_date']),
            );
        }

        update_post_meta($product_id, '_wcfp_schedules', $schedules);
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
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if (!$schedule_id || !$product_id) {
            wp_send_json_error(__('Invalid schedule or product ID.', 'wc-flex-pay'));
        }

        try {
            $schedules = get_post_meta($product_id, '_wcfp_schedules', true);
            if (!is_array($schedules)) {
                $schedules = array();
            }

            // Find and update the schedule
            foreach ($schedules as &$schedule) {
                if ($schedule['installment_number'] === $schedule_id) {
                    $schedule['amount'] = isset($_POST['amount']) ? wc_format_decimal($_POST['amount']) : 0;
                    $schedule['due_date'] = isset($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : '';
                    break;
                }
            }

            update_post_meta($product_id, '_wcfp_schedules', $schedules);
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

        // Get all orders with flex pay payments
        $orders = wc_get_orders(array(
            'meta_key' => '_wcfp_payments',
            'limit' => -1,
        ));

        $overdue_count = 0;
        $current_date = current_time('mysql');

        foreach ($orders as $order) {
            $payments = get_post_meta($order->get_id(), '_wcfp_payments', true);
            if (empty($payments)) continue;

            foreach ($payments as $payment) {
                if ($payment['status'] === 'pending' && strtotime($payment['due_date']) < strtotime($current_date)) {
                    $overdue_count++;
                }
            }
        }

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
