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
     * Order note icons
     *
     * @var array
     */
    private $note_icons = array(
        'payment' => '💰',
        'email' => '📧',
        'order' => '📦',
        'system' => 'ℹ️',
        'warning' => '⚠️',
        'error' => '❌',
        'success' => '✅'
    );

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
        add_action('woocommerce_order_item_add_action_buttons', array($this, 'add_order_item_actions'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // AJAX handlers
        add_action('wp_ajax_wcfp_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_wcfp_update_schedule', array($this, 'ajax_update_schedule'));
        add_action('wp_ajax_wcfp_bulk_edit_schedules', array($this, 'ajax_bulk_edit_schedules'));
        add_action('wp_ajax_wcfp_create_sub_order', array($this, 'ajax_create_sub_order'));
        add_action('wp_ajax_wcfp_generate_payment_link', array($this, 'ajax_generate_payment_link'));
        add_action('wp_ajax_wcfp_send_payment_link', array($this, 'ajax_send_payment_link'));
        
        // Order list customization
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_list_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_order_list_columns'));
        add_filter('woocommerce_admin_order_preview_actions', array($this, 'add_order_preview_actions'), 10, 2);
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
        // Only load for admin users
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        error_log('WCFP Debug - Proceeding with script enqueue for all admin pages');

        // Enqueue frontend styles in admin
        wp_enqueue_style(
            'wcfp-frontend',
            WCFP_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WCFP_VERSION
        );
        error_log('WCFP Debug - Frontend CSS URL: ' . WCFP_PLUGIN_URL . 'assets/css/frontend.css');

        // Enqueue styles
        wp_enqueue_style(
            'wcfp-admin',
            WCFP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WCFP_VERSION
        );
        error_log('WCFP Debug - Admin CSS URL: ' . WCFP_PLUGIN_URL . 'assets/css/admin.css');

        // Enqueue clipboard.js
        wp_enqueue_script(
            'clipboard',
            WCFP_PLUGIN_URL . 'assets/js/clipboard.min.js',
            array(),
            '2.0.11',
            true
        );
        error_log('WCFP Debug - Clipboard.js URL: ' . WCFP_PLUGIN_URL . 'assets/js/clipboard.min.js');

        // Check if dependencies are registered
        error_log('WCFP Debug - Checking dependencies:');
        error_log('WCFP Debug - jQuery: ' . (wp_script_is('jquery', 'registered') ? 'registered' : 'not registered'));
        error_log('WCFP Debug - jQuery UI Datepicker: ' . (wp_script_is('jquery-ui-datepicker', 'registered') ? 'registered' : 'not registered'));
        error_log('WCFP Debug - WP Util: ' . (wp_script_is('wp-util', 'registered') ? 'registered' : 'not registered'));
        error_log('WCFP Debug - Clipboard: ' . (wp_script_is('clipboard', 'registered') ? 'registered' : 'not registered'));

        // Ensure jQuery UI core is loaded before datepicker
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-datepicker');

        // Enqueue admin scripts
        wp_enqueue_script(
            'wcfp-admin',
            WCFP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'wp-util', 'clipboard'),
            WCFP_VERSION,
            true
        );
        error_log('WCFP Debug - Admin JS URL: ' . WCFP_PLUGIN_URL . 'assets/js/admin.js');

        // Localize script
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
                    'confirm_delete' => __('Are you sure you want to delete this installment?', 'wc-flex-pay'),
                    'no_installments' => __('Please add at least one installment.', 'wc-flex-pay'),
                    'invalid_amount' => __('Please enter a valid amount.', 'wc-flex-pay'),
                    'invalid_date' => __('Please enter a valid date.', 'wc-flex-pay'),
                    'copied' => __('Copied!', 'wc-flex-pay'),
                    'email_sent' => __('Payment link sent successfully!', 'wc-flex-pay'),
                ),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'currency_pos' => get_option('woocommerce_currency_pos'),
                'decimal_sep' => wc_get_price_decimal_separator(),
                'thousand_sep' => wc_get_price_thousand_separator(),
                'decimals' => wc_get_price_decimals(),
                'date_format' => get_option('date_format'),
            )
        );

        // Add jQuery UI styles for datepicker
        wp_enqueue_style(
            'jquery-ui-style',
            WCFP_PLUGIN_URL . 'assets/css/jquery-ui.min.css',
            array(),
            WCFP_VERSION
        );

        // Log all enqueued scripts and styles for debugging
        global $wp_scripts, $wp_styles;
        error_log('WCFP Debug - Enqueued scripts: ' . print_r($wp_scripts->queue, true));
        error_log('WCFP Debug - Enqueued styles: ' . print_r($wp_styles->queue, true));
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
        // Parent order meta box
        add_meta_box(
            'wcfp-order-payments',
            __('Flex Pay Payments', 'wc-flex-pay'),
            array($this, 'render_order_meta_box'),
            'shop_order',
            'normal',
            'high'
        );

        // Sub-order meta box
        add_meta_box(
            'wcfp-sub-order-info',
            __('Flex Pay Sub-order Information', 'wc-flex-pay'),
            array($this, 'render_sub_order_meta_box'),
            'shop_order',
            'side',
            'high'
        );
    }

    /**
     * Add order item actions
     *
     * @param WC_Order $order Order object
     */
    public function add_order_item_actions($order) {
        // Only show for parent orders
        if ($order->get_meta('_wcfp_parent_order')) {
            return;
        }

        $payments = $this->get_order_payments($order->get_id());
        if (empty($payments)) {
            return;
        }

        ?>
        <button type="button" 
                class="button create-sub-order" 
                data-order-id="<?php echo esc_attr($order->get_id()); ?>">
            <?php esc_html_e('Create Sub-order', 'wc-flex-pay'); ?>
        </button>
        <?php
    }

    /**
     * Add columns to order list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_order_list_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            if ($key === 'order_status') {
                $new_columns['wcfp_info'] = __('Flex Pay', 'wc-flex-pay');
            }
        }
        
        return $new_columns;
    }

    /**
     * Render order list columns
     *
     * @param string $column Column name
     */
    public function render_order_list_columns($column) {
        global $post;
        
        if ($column === 'wcfp_info') {
            $order = wc_get_order($post->ID);
            if (!$order) {
                return;
            }

            // Check if this is a sub-order
            $parent_id = $order->get_meta('_wcfp_parent_order');
            if ($parent_id) {
                $installment = $order->get_meta('_wcfp_installment_number');
                printf(
                    '<span class="wcfp-sub-order-badge">%s</span>',
                    sprintf(
                        /* translators: 1: parent order number, 2: installment number */
                        esc_html__('Sub-order of #%1$s (Installment %2$d)', 'wc-flex-pay'),
                        esc_html($parent_id),
                        esc_html($installment)
                    )
                );
                return;
            }

            // Show Flex Pay info for parent orders
            $payments = $this->get_order_payments($order->get_id());
            if (!empty($payments)) {
                $completed = 0;
                $total = count($payments);
                
                foreach ($payments as $payment) {
                    if ($payment['status'] === 'completed') {
                        $completed++;
                    }
                }

                printf(
                    '<span class="wcfp-payments-badge">%s</span>',
                    sprintf(
                        /* translators: 1: completed payments, 2: total payments */
                        esc_html__('%1$d/%2$d payments', 'wc-flex-pay'),
                        esc_html($completed),
                        esc_html($total)
                    )
                );
            }
        }
    }

    /**
     * Add actions to order preview
     *
     * @param array    $actions Existing actions
     * @param WC_Order $order   Order object
     * @return array Modified actions
     */
    public function add_order_preview_actions($actions, $order) {
        $parent_id = $order->get_meta('_wcfp_parent_order');
        if ($parent_id) {
            $actions['view_parent'] = array(
                'url' => admin_url('post.php?post=' . $parent_id . '&action=edit'),
                'name' => __('View Parent Order', 'wc-flex-pay')
            );
        }
        return $actions;
    }

    /**
     * Create sub-order via AJAX
     */
    public function ajax_create_sub_order() {
        check_ajax_referer('wcfp-admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'wc-flex-pay'));
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error(__('Invalid order ID.', 'wc-flex-pay'));
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new \Exception(__('Order not found.', 'wc-flex-pay'));
            }

            $payment_manager = new \WCFP\Payment();
            $next_payment = $payment_manager->get_next_pending_payment($order_id);
            if (!$next_payment) {
                throw new \Exception(__('No pending payments found.', 'wc-flex-pay'));
            }

            // Create sub-order
            $sub_order = $payment_manager->create_sub_order($order, $next_payment);
            
            wp_send_json_success(array(
                'message' => __('Sub-order created successfully.', 'wc-flex-pay'),
                'redirect' => admin_url('post.php?post=' . $sub_order->get_id() . '&action=edit')
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
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
     * Render sub-order meta box
     */
    public function render_sub_order_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }

        // Only show for sub-orders
        if (!$order->get_meta('_wcfp_parent_order')) {
            return;
        }

        include WCFP_PLUGIN_DIR . 'templates/admin/sub-order-meta-box.php';
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
        $order = wc_get_order($order_id);
        if (!$order) {
            return array();
        }

        $order_manager = new \WCFP\Order();
        $payments = $order_manager->get_order_payments($order);
        return $payments['has_installments'] ? $payments : array();
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
     * Generate payment link via AJAX
     */
    public function ajax_generate_payment_link() {
        check_ajax_referer('wcfp-admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'wc-flex-pay'));
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $installment = isset($_POST['installment']) ? absint($_POST['installment']) : 0;

        if (!$order_id || !$installment) {
            wp_send_json_error(__('Invalid parameters.', 'wc-flex-pay'));
        }

        try {
            $payment_manager = new \WCFP\Payment();
            $link = $payment_manager->generate_payment_link($order_id, $installment, array(
                'regenerate' => true
            ));

            wp_send_json_success(array(
                'message' => __('Payment link generated successfully.', 'wc-flex-pay'),
                'link' => $link
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Send payment link via email
     */
    public function ajax_send_payment_link() {
        check_ajax_referer('wcfp-admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'wc-flex-pay'));
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $installment = isset($_POST['installment']) ? absint($_POST['installment']) : 0;

        if (!$order_id || !$installment) {
            wp_send_json_error(__('Invalid parameters.', 'wc-flex-pay'));
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new \Exception(__('Order not found.', 'wc-flex-pay'));
            }

            $payment_manager = new \WCFP\Payment();
            $link = $payment_manager->generate_payment_link($order_id, $installment);

            // Send email
            $mailer = WC()->mailer();
            $email = new \WCFP\Emails\Payment_Link($mailer);
            $email->trigger($order_id, $installment, $link);

            // Log event
            $payment_manager->log_event(
                $order_id,
                sprintf(
                    __('Payment link for installment %d sent to %s', 'wc-flex-pay'),
                    $installment,
                    $order->get_billing_email()
                ),
                'email'
            );

            wp_send_json_success(__('Payment link sent successfully.', 'wc-flex-pay'));
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
