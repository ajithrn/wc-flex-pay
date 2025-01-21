<?php
/**
 * Product related functions and actions
 *
 * @package WC_Flex_Pay
 */

namespace WCFP;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product Class
 */
class Product {
    /**
     * Admin notices
     */
    private $notices = array();

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
        // Frontend Display
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_payment_schedule'));
        
        // Cart & Checkout
        add_filter('woocommerce_cart_item_price', array($this, 'modify_cart_item_price'), 10, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'modify_cart_item_subtotal'), 10, 3);
        add_action('woocommerce_before_calculate_totals', array($this, 'adjust_cart_prices'), 10, 1);
        add_action('woocommerce_cart_totals_before_order_total', array($this, 'display_cart_installment_notice'));
        add_action('woocommerce_review_order_before_payment', array($this, 'display_checkout_installment_notice'));
        add_action('woocommerce_checkout_order_processed', array($this, 'add_future_payments_note'), 10, 3);
        
        // Add payment type to cart
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_payment_type_to_cart_item'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_payment_type_in_cart'), 10, 2);

        // Admin
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('woocommerce_process_product_meta', array($this, 'process_flex_pay_meta'), 10, 1);
        add_action('woocommerce_product_bulk_edit_end', array($this, 'add_bulk_edit_fields'));
        add_action('woocommerce_product_bulk_edit_save', array($this, 'save_bulk_edit_fields'));
    }

    /**
     * Add admin notice
     */
    private function add_notice($message, $type = 'error') {
        $this->notices[] = array(
            'message' => $message,
            'type' => $type
        );
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'product') {
            return;
        }

        foreach ($this->notices as $notice) {
            ?>
            <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                <p><?php echo wp_kses_post($notice['message']); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Calculate total price including all installments
     *
     * @param array $schedules Payment schedules
     * @return float
     */
    public function calculate_total_price($schedules) {
        if (empty($schedules)) {
            return 0;
        }
        return array_sum(array_column($schedules, 'amount'));
    }

    /**
     * Process Flex Pay meta and update product price
     */
    public function process_flex_pay_meta($post_id) {
        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        $enabled = isset($_POST['_wcfp_enabled']) ? 'yes' : 'no';
        $product->update_meta_data('_wcfp_enabled', $enabled);

        if ($enabled === 'yes') {
            if (!isset($_POST['wcfp_schedule']) || !is_array($_POST['wcfp_schedule'])) {
                $this->add_notice(__('No payment schedule provided.', 'wc-flex-pay'));
                return;
            }

            try {
                $schedules = array();
                
                if (is_array($_POST['wcfp_schedule'])) {
                    foreach ($_POST['wcfp_schedule'] as $schedule) {
                        if (empty($schedule['amount']) || empty($schedule['due_date']) || 
                            (isset($schedule['installment_number']) && $schedule['installment_number'] === '0')) {
                            continue;
                        }
                        
                        $schedules[] = array(
                            'amount' => wc_clean($schedule['amount']),
                            'due_date' => wc_clean($schedule['due_date']),
                            'description' => isset($schedule['description']) ? wc_clean($schedule['description']) : '',
                        );
                    }
                }

                if (empty($schedules)) {
                    throw new \Exception(__('At least one payment schedule is required.', 'wc-flex-pay'));
                }

                usort($schedules, function($a, $b) {
                    return strtotime($a['due_date']) - strtotime($b['due_date']);
                });

                $number = 1;
                foreach ($schedules as &$schedule) {
                    $schedule['installment_number'] = $number++;
                }

                $validated_schedules = $this->validate_schedules($schedules);
                $this->save_payment_schedules($post_id, $validated_schedules);

                $total = $this->calculate_total_price($validated_schedules);
                $product->set_regular_price($total);
                $product->set_price($total); // Also set the current price
                $product->save();

                // Force WooCommerce to refresh its price cache
                delete_transient('wc_product_' . $product->get_id() . '_price_' . \WC_Cache_Helper::get_transient_version('product'));
                
                $this->add_notice(__('Payment schedule saved successfully.', 'wc-flex-pay'), 'success');
            } catch (\Exception $e) {
                $this->add_notice($e->getMessage());
                error_log('WC Flex Pay Error: ' . $e->getMessage());
            }
        } else {
            try {
                $this->save_payment_schedules($post_id, array());
            } catch (\Exception $e) {
                error_log('WC Flex Pay Error: ' . $e->getMessage());
            }
        }

        $product->save();
    }

    /**
     * Validate payment schedules
     */
    private function validate_schedules($schedules) {
        $validated = array();
        $last_date = null;

        foreach ($schedules as $schedule) {
            $amount = wc_format_decimal($schedule['amount']);
            if (!is_numeric($amount) || $amount <= 0) {
                throw new \Exception(__('Amount must be a valid number greater than 0.', 'wc-flex-pay'));
            }

            $due_date = sanitize_text_field($schedule['due_date']);
            if (!$due_date || !strtotime($due_date)) {
                throw new \Exception(__('Invalid due date format.', 'wc-flex-pay'));
            }

            if ($last_date && strtotime($due_date) <= strtotime($last_date)) {
                throw new \Exception(__('Due dates must be in chronological order.', 'wc-flex-pay'));
            }

            $validated[] = array(
                'installment_number' => $schedule['installment_number'],
                'amount' => $amount,
                'due_date' => $due_date,
                'description' => sanitize_text_field($schedule['description'] ?? ''),
            );

            $last_date = $due_date;
        }

        if (empty($validated)) {
            throw new \Exception(__('At least one valid payment schedule is required.', 'wc-flex-pay'));
        }

        return $validated;
    }

    /**
     * Save payment schedules
     */
    private function save_payment_schedules($product_id, $schedules) {
        update_post_meta($product_id, '_wcfp_schedules', $schedules);
    }

    /**
     * Get payment schedules for a product
     */
    public function get_payment_schedules($product_id) {
        $schedules = get_post_meta($product_id, '_wcfp_schedules', true);
        return !empty($schedules) ? $schedules : array();
    }

    /**
     * Check if Flex Pay is enabled for product
     */
    public function is_flex_pay_enabled($product) {
        return 'yes' === get_post_meta($product->get_id(), '_wcfp_enabled', true);
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_product() && !is_cart() && !is_checkout()) {
            return;
        }

        wp_enqueue_style(
            'wcfp-frontend',
            WCFP_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WCFP_VERSION
        );

        wp_enqueue_script(
            'wcfp-frontend',
            WCFP_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            WCFP_VERSION,
            true
        );

        wp_localize_script(
            'wcfp-frontend',
            'wcfp_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcfp-frontend'),
                'i18n' => array(
                    'select_payment_type' => __('Please select a payment type.', 'wc-flex-pay'),
                ),
            )
        );
    }

    /**
     * Display payment schedule on product page
     */
    public function display_payment_schedule() {
        global $product;

        if (!$this->is_flex_pay_enabled($product)) {
            return;
        }

        $schedules = $this->get_payment_schedules($product->get_id());
        if (empty($schedules)) {
            return;
        }

        wc_get_template(
            'single-product/payment-schedule.php',
            array(
                'product'   => $product,
                'schedules' => $schedules,
            ),
            '',
            WCFP_PLUGIN_DIR . 'templates/'
        );
    }

    /**
     * Add bulk edit fields
     */
    public function add_bulk_edit_fields() {
        ?>
        <div class="inline-edit-group">
            <label class="alignleft">
                <span class="title"><?php _e('Flex Pay', 'wc-flex-pay'); ?></span>
                <span class="input-text-wrap">
                    <select class="change_flex_pay" name="change_flex_pay">
                        <option value=""><?php _e('— No Change —', 'wc-flex-pay'); ?></option>
                        <option value="yes"><?php _e('Enable', 'wc-flex-pay'); ?></option>
                        <option value="no"><?php _e('Disable', 'wc-flex-pay'); ?></option>
                    </select>
                </span>
            </label>
        </div>
        <?php
    }

    /**
     * Save bulk edit fields
     */
    public function save_bulk_edit_fields($product) {
        if (isset($_REQUEST['change_flex_pay']) && !empty($_REQUEST['change_flex_pay'])) {
            $product->update_meta_data('_wcfp_enabled', sanitize_text_field($_REQUEST['change_flex_pay']));
            $product->save();
        }
    }

    /**
     * Modify cart item price display
     */
    public function modify_cart_item_price($price, $cart_item, $cart_item_key) {
        if (!isset($cart_item['wcfp_payment_type']) || !isset($cart_item['wcfp_initial_payment'])) {
            return $price;
        }

        $product = $cart_item['data'];
        if (!$this->is_flex_pay_enabled($product)) {
            return $price;
        }

        if ($cart_item['wcfp_payment_type'] === 'installment') {
            return wc_price($cart_item['wcfp_initial_payment']);
        }

        return wc_price($cart_item['wcfp_total_price']);
    }

    /**
     * Modify cart item subtotal display
     */
    public function modify_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        if (!isset($cart_item['wcfp_payment_type']) || !isset($cart_item['wcfp_initial_payment'])) {
            return $subtotal;
        }

        $product = $cart_item['data'];
        if (!$this->is_flex_pay_enabled($product)) {
            return $subtotal;
        }

        $quantity = $cart_item['quantity'];
        if ($cart_item['wcfp_payment_type'] === 'installment') {
            $schedules = $this->get_payment_schedules($product->get_id());
            $current_date = current_time('Y-m-d');
            $future_payments = 0;
            $pending_amount = 0;
            
            foreach ($schedules as $schedule) {
                if (strtotime($schedule['due_date']) > strtotime($current_date)) {
                    $future_payments++;
                    $pending_amount += $schedule['amount'];
                }
            }
            
            $pending_amount *= $quantity;
            
            $output = wc_price($cart_item['wcfp_initial_payment'] * $quantity);
            
            if ($future_payments > 0) {
                $output .= '<br><small>' . sprintf(
                    __('Pay %s later', 'wc-flex-pay'),
                    wc_price($pending_amount)
                ) . '</small>';
                
                // Add upcoming payment details
                $current_date = current_time('Y-m-d');
                $next_payment = null;
                foreach ($schedules as $schedule) {
                    if (strtotime($schedule['due_date']) > strtotime($current_date)) {
                        $next_payment = $schedule;
                        break;
                    }
                }
                
                if ($next_payment) {
                    $output .= '<br><small>' . sprintf(
                        __('Next payment: %s on %s', 'wc-flex-pay'),
                        wc_price($next_payment['amount'] * $quantity),
                        date_i18n(get_option('date_format'), strtotime($next_payment['due_date']))
                    ) . '</small>';
                }
            }
            
            return $output;
        }

        return wc_price($cart_item['wcfp_total_price'] * $quantity);
    }

    /**
     * Display installment notice in cart
     */
    public function display_cart_installment_notice() {
        $cart = WC()->cart;
        $has_installments = false;
        $upcoming_payments = array();

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['wcfp_payment_type']) && $cart_item['wcfp_payment_type'] === 'installment') {
                $product = $cart_item['data'];
                $schedules = $this->get_payment_schedules($product->get_id());
                
                if (!empty($schedules)) {
                    $has_installments = true;
                    $quantity = $cart_item['quantity'];
                    
                    // Skip first payment as it's paid at checkout
                    array_shift($schedules);
                    
                    foreach ($schedules as $index => $schedule) {
                        $payment_number = $index + 2; // +2 because first payment is initial and index starts at 0
                        $date = date_i18n(get_option('date_format'), strtotime($schedule['due_date']));
                        
                        if (!isset($upcoming_payments[$date])) {
                            $upcoming_payments[$date] = array(
                                'amount' => 0,
                                'number' => $payment_number
                            );
                        }
                        $upcoming_payments[$date]['amount'] += $schedule['amount'] * $quantity;
                    }
                }
            }
        }

        if ($has_installments) {
            echo '<tr class="wcfp-installment-notice">
                <td colspan="2">
                    <div class="woocommerce-info">
                        <h4>' . esc_html__('Upcoming Payments', 'wc-flex-pay') . '</h4>
                        <ul>';
            
            foreach ($upcoming_payments as $date => $payment) {
                echo '<li>' . sprintf(
                    /* translators: 1: payment number, 2: date, 3: amount */
                    esc_html__('Payment %1$d: %2$s - %3$s', 'wc-flex-pay'),
                    $payment['number'],
                    $date,
                    wc_price($payment['amount'])
                ) . '</li>';
            }
            
            echo '</ul></div></td></tr>';
        }
    }

    /**
     * Display installment notice at checkout
     */
    public function display_checkout_installment_notice() {
        $this->display_cart_installment_notice();
    }

    /**
     * Add payment type and schedule to cart item data
     */
    public function add_payment_type_to_cart_item($cart_item_data, $product_id, $variation_id) {
        $product = wc_get_product($product_id);
        if (!$product || !$this->is_flex_pay_enabled($product)) {
            return $cart_item_data;
        }

        if (isset($_POST['wcfp_payment_type'])) {
            $payment_type = sanitize_text_field($_POST['wcfp_payment_type']);
            $schedules = $this->get_payment_schedules($product_id);
            
            if (empty($schedules)) {
                return $cart_item_data;
            }

            $cart_item_data['wcfp_payment_type'] = $payment_type;
            $cart_item_data['wcfp_schedules'] = $schedules;
            
            if ($payment_type === 'installment') {
                // Calculate initial payment including past due amounts
                $initial_payment = 0;
                $current_date = current_time('Y-m-d');
                
                foreach ($schedules as $schedule) {
                    if (strtotime($schedule['due_date']) <= strtotime($current_date)) {
                        // Add past due and current payments to initial payment
                        $initial_payment += $schedule['amount'];
                    } else {
                        break; // Stop once we hit future payments
                    }
                }
                
                // If no payments are due yet, use first payment as initial
                if ($initial_payment === 0 && !empty($schedules)) {
                    $initial_payment = $schedules[0]['amount'];
                }
                
                $cart_item_data['wcfp_initial_payment'] = $initial_payment;
                $cart_item_data['wcfp_total_price'] = $this->calculate_total_price($schedules);
            } else {
                $cart_item_data['wcfp_initial_payment'] = $this->calculate_total_price($schedules);
                $cart_item_data['wcfp_total_price'] = $this->calculate_total_price($schedules);
            }
        }
        return $cart_item_data;
    }

    /**
     * Display payment type in cart
     */
    public function display_payment_type_in_cart($item_data, $cart_item) {
        if (isset($cart_item['wcfp_payment_type']) && $cart_item['wcfp_payment_type'] === 'installment') {
            // Add payment summary
            $item_data[] = array(
                'key' => __('Total Price', 'wc-flex-pay'),
                'value' => sprintf('<span class="wcfp-total">%s</span>', wc_price($cart_item['wcfp_total_price']))
            );
            
            $item_data[] = array(
                'key' => __('Initial Payment', 'wc-flex-pay'),
                'value' => sprintf('<span class="wcfp-initial">%s</span>', wc_price($cart_item['wcfp_initial_payment']))
            );
            
            $pending_payment = $cart_item['wcfp_total_price'] - $cart_item['wcfp_initial_payment'];
            $item_data[] = array(
                'key' => __('Pending Payment', 'wc-flex-pay'),
                'value' => sprintf('<span class="wcfp-pending">%s</span>', wc_price($pending_payment))
            );

            // Add payment schedule if there are future payments
            if (isset($cart_item['wcfp_schedules'])) {
                $schedules = $cart_item['wcfp_schedules'];
                $current_date = current_time('Y-m-d');
                $future_schedules = array_filter($schedules, function($schedule) use ($current_date) {
                    return strtotime($schedule['due_date']) > strtotime($current_date);
                });

                if (!empty($future_schedules)) {
                    $schedule_rows = array();
                    foreach ($future_schedules as $schedule) {
                        $schedule_rows[] = sprintf(
                            '%s: &nbsp;&nbsp; %s',
                            date_i18n(get_option('date_format'), strtotime($schedule['due_date'])),
                            wc_price($schedule['amount'])
                        );
                    }
                    $schedule_html = implode("\n", $schedule_rows);
                    
                    $item_data[] = array(
                        'key' => __('Payment Schedule', 'wc-flex-pay'),
                        'value' => $schedule_html
                    );
                }
            }
        }
        return $item_data;
    }

    /**
     * Adjust cart prices based on payment type
     */
    public function adjust_cart_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (!isset($cart_item['data']) || !isset($cart_item['wcfp_payment_type']) || !isset($cart_item['wcfp_initial_payment'])) {
                continue;
            }

            $product = $cart_item['data'];
            if (!$this->is_flex_pay_enabled($product)) {
                continue;
            }

            // Set price to initial payment amount
            $product->set_price($cart_item['wcfp_initial_payment']);
        }
    }

    /**
     * Add future payments note to order
     */
    public function add_future_payments_note($order_id, $posted_data, $order) {
        $has_installments = false;
        $future_payments = array();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($this->is_flex_pay_enabled($product)) {
                $schedules = $this->get_payment_schedules($product->get_id());
                if (!empty($schedules)) {
                    $has_installments = true;
                    // Skip first payment as it's paid at checkout
                    array_shift($schedules);
                    foreach ($schedules as $schedule) {
                        $date = date_i18n(get_option('date_format'), strtotime($schedule['due_date']));
                        if (!isset($future_payments[$date])) {
                            $future_payments[$date] = 0;
                        }
                        $future_payments[$date] += $schedule['amount'] * $item->get_quantity();
                    }
                }
            }
        }

        if ($has_installments) {
            $note = __('Future Payments Schedule:', 'wc-flex-pay') . "\n";
            foreach ($future_payments as $date => $amount) {
                $note .= sprintf(
                    '%s: %s' . "\n",
                    $date,
                    wc_price($amount)
                );
            }
            $order->add_order_note($note);
        }
    }
}
