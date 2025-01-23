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
            if (!isset($_POST['wcfp_installments']) || !is_array($_POST['wcfp_installments'])) {
                $this->add_notice(__('No installments provided.', 'wc-flex-pay'));
                return;
            }

            try {
                $installments = array();
                
                if (is_array($_POST['wcfp_installments'])) {
                    foreach ($_POST['wcfp_installments'] as $installment) {
                        if (empty($installment['amount']) || empty($installment['due_date']) || 
                            (isset($installment['number']) && $installment['number'] === '0')) {
                            continue;
                        }
                        
                        $installments[] = array(
                            'number' => absint($installment['number']),
                            'amount' => wc_clean($installment['amount']),
                            'due_date' => wc_clean($installment['due_date']),
                            'status' => 'pending'
                        );
                    }
                }

                if (empty($installments)) {
                    throw new \Exception(__('At least one installment is required.', 'wc-flex-pay'));
                }

                usort($installments, function($a, $b) {
                    return strtotime($a['due_date']) - strtotime($b['due_date']);
                });

                $validated_installments = $this->validate_installments($installments);
                
                // Create payment data structure
                $payments = array(
                    'installments' => $validated_installments,
                    'summary' => array(
                        'total_installments' => count($validated_installments),
                        'paid_installments' => 0,
                        'total_amount' => $this->calculate_total_price($validated_installments),
                        'paid_amount' => 0,
                        'next_due_date' => $validated_installments[0]['due_date']
                    )
                );

                $this->save_payment_data($post_id, $payments);

                $product->set_regular_price($payments['summary']['total_amount']);
                $product->set_price($payments['summary']['total_amount']); // Also set the current price
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
                $this->save_payment_data($post_id, array());
            } catch (\Exception $e) {
                error_log('WC Flex Pay Error: ' . $e->getMessage());
            }
        }

        $product->save();
    }

    /**
     * Validate installments
     */
    private function validate_installments($installments) {
        $validated = array();
        $last_date = null;

        foreach ($installments as $installment) {
            $amount = wc_format_decimal($installment['amount']);
            if (!is_numeric($amount) || $amount <= 0) {
                throw new \Exception(__('Amount must be a valid number greater than 0.', 'wc-flex-pay'));
            }

            $due_date = sanitize_text_field($installment['due_date']);
            if (!$due_date || !strtotime($due_date)) {
                throw new \Exception(__('Invalid due date format.', 'wc-flex-pay'));
            }

            if ($last_date && strtotime($due_date) <= strtotime($last_date)) {
                throw new \Exception(__('Due dates must be in chronological order.', 'wc-flex-pay'));
            }

            $validated[] = array(
                'number' => absint($installment['number']),
                'amount' => $amount,
                'due_date' => $due_date,
                'status' => 'pending',
                'logs' => array()
            );

            $last_date = $due_date;
        }

        if (empty($validated)) {
            throw new \Exception(__('At least one valid installment is required.', 'wc-flex-pay'));
        }

        return $validated;
    }

    /**
     * Save payment data
     */
    private function save_payment_data($product_id, $payments) {
        update_post_meta($product_id, '_wcfp_payments', $payments);
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

        $payments = get_post_meta($product->get_id(), '_wcfp_payments', true);
        if (empty($payments) || empty($payments['installments'])) {
            return;
        }

        // Add form start/end if needed
        $form_start = '';
        $form_end = '';
        if (!has_action('woocommerce_before_add_to_cart_button', array($this, 'display_payment_schedule'))) {
            $form_start = '<form class="cart" method="post" enctype="multipart/form-data">';
            $form_end = '<button type="submit" name="add-to-cart" value="' . esc_attr($product->get_id()) . '" class="single_add_to_cart_button button alt">' . esc_html($product->single_add_to_cart_text()) . '</button></form>';
        }

        wc_get_template(
            'single-product/payment-schedule.php',
            array(
                'product' => $product,
                'payments' => $payments,
                'form_start' => $form_start,
                'form_end' => $form_end,
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
        if ($cart_item['wcfp_payment_type'] === 'installment' && isset($cart_item['wcfp_payments'])) {
            $payments = $cart_item['wcfp_payments'];
            $current_date = current_time('Y-m-d');
            $future_installments = array();
            $pending_amount = 0;
            
            foreach ($payments['installments'] as $installment) {
                if (strtotime($installment['due_date']) > strtotime($current_date)) {
                    $future_installments[] = $installment;
                    $pending_amount += $installment['amount'];
                }
            }
            
            $pending_amount *= $quantity;
            $initial_amount = $cart_item['wcfp_initial_payment'] * $quantity;
            
            $output = '<div class="wcfp-subtotal-breakdown">';
            $output .= '<div class="wcfp-initial-payment">' . wc_price($initial_amount) . '</div>';
            
            if (!empty($future_installments)) {
                $output .= '<div class="wcfp-pending-payment">';
                $output .= '<small class="wcfp-pending-amount">' . sprintf(
                    /* translators: %s: pending amount */
                    __('+ %s in installments', 'wc-flex-pay'),
                    wc_price($pending_amount)
                ) . '</small>';
                
                // Add next payment info
                $next_installment = reset($future_installments);
                $output .= '<small class="wcfp-next-payment">' . sprintf(
                    /* translators: 1: installment number, 2: amount, 3: date */
                    __('Next: Installment %1$d - %2$s on %3$s', 'wc-flex-pay'),
                    $next_installment['number'],
                    wc_price($next_installment['amount'] * $quantity),
                    date_i18n(get_option('date_format'), strtotime($next_installment['due_date']))
                ) . '</small>';
                $output .= '</div>';
            }
            
            $output .= '</div>';
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
        $total_pending = 0;
        $upcoming_payments = array();

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['wcfp_payment_type']) && $cart_item['wcfp_payment_type'] === 'installment' && isset($cart_item['wcfp_payments'])) {
                $product = $cart_item['data'];
                $payments = $cart_item['wcfp_payments'];
                
                if (!empty($payments['installments'])) {
                    $has_installments = true;
                    $quantity = $cart_item['quantity'];
                    $current_date = current_time('Y-m-d');
                    
                    foreach ($payments['installments'] as $installment) {
                        if (strtotime($installment['due_date']) > strtotime($current_date)) {
                            $date = date_i18n(get_option('date_format'), strtotime($installment['due_date']));
                            
                            if (!isset($upcoming_payments[$date])) {
                                $upcoming_payments[$date] = array(
                                    'amount' => 0,
                                    'installments' => array()
                                );
                            }
                            
                            $amount = $installment['amount'] * $quantity;
                            $upcoming_payments[$date]['amount'] += $amount;
                            $total_pending += $amount;
                            
                            $upcoming_payments[$date]['installments'][] = array(
                                'number' => $installment['number'],
                                'product_name' => $product->get_name(),
                                'amount' => $amount
                            );
                        }
                    }
                }
            }
        }

        if ($has_installments) {
            echo '<tr class="wcfp-installment-notice">
                <td colspan="2">
                    <div class="woocommerce-info">
                        <h4>' . sprintf(
                            /* translators: %s: total pending amount */
                            esc_html__('Upcoming Payments (Total: %s)', 'wc-flex-pay'),
                            wc_price($total_pending)
                        ) . '</h4>
                        <div class="wcfp-payment-timeline">';
            
            ksort($upcoming_payments);
            foreach ($upcoming_payments as $date => $payment) {
                echo '<div class="wcfp-payment-date">
                    <div class="wcfp-date-header">
                        <strong>' . esc_html($date) . '</strong>
                        <span class="wcfp-total">' . wc_price($payment['amount']) . '</span>
                    </div>
                    <ul class="wcfp-installment-list">';
                
                foreach ($payment['installments'] as $installment) {
                    echo '<li>' . sprintf(
                        /* translators: 1: product name, 2: installment number, 3: amount */
                        esc_html__('%1$s (Installment %2$d) - %3$s', 'wc-flex-pay'),
                        esc_html($installment['product_name']),
                        $installment['number'],
                        wc_price($installment['amount'])
                    ) . '</li>';
                }
                
                echo '</ul></div>';
            }
            
            echo '</div></div></td></tr>';
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
            $payments = get_post_meta($product_id, '_wcfp_payments', true);
            
            if (empty($payments) || empty($payments['installments'])) {
                return $cart_item_data;
            }

            $cart_item_data['wcfp_payment_type'] = $payment_type;
            $cart_item_data['wcfp_payments'] = $payments;
            
            if ($payment_type === 'installment') {
                // Calculate initial payment including past due amounts
                $initial_payment = 0;
                $current_date = current_time('Y-m-d');
                
                foreach ($payments['installments'] as $installment) {
                    if (strtotime($installment['due_date']) <= strtotime($current_date)) {
                        // Add past due and current payments to initial payment
                        $initial_payment += $installment['amount'];
                    } else {
                        break; // Stop once we hit future payments
                    }
                }
                
                // If no payments are due yet, use first payment as initial
                if ($initial_payment === 0 && !empty($payments['installments'])) {
                    $initial_payment = $payments['installments'][0]['amount'];
                }
                
                $cart_item_data['wcfp_initial_payment'] = $initial_payment;
                $cart_item_data['wcfp_total_price'] = $payments['summary']['total_amount'];
            } else {
                $cart_item_data['wcfp_initial_payment'] = $payments['summary']['total_amount'];
                $cart_item_data['wcfp_total_price'] = $payments['summary']['total_amount'];
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
            if (isset($cart_item['wcfp_payments']['installments'])) {
                $current_date = current_time('Y-m-d');
                $future_installments = array_filter($cart_item['wcfp_payments']['installments'], function($installment) use ($current_date) {
                    return strtotime($installment['due_date']) > strtotime($current_date);
                });

                if (!empty($future_installments)) {
                    $schedule_rows = array();
                    foreach ($future_installments as $installment) {
                        $schedule_rows[] = sprintf(
                            /* translators: %1$d: installment number, %2$s: formatted date, %3$s: amount */
                            __('Installment %1$d: %2$s - %3$s', 'wc-flex-pay'),
                            $installment['number'],
                            date_i18n(get_option('date_format'), strtotime($installment['due_date'])),
                            wc_price($installment['amount'])
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
        $total_pending = 0;
        $future_payments = array();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($this->is_flex_pay_enabled($product)) {
                $payments = get_post_meta($product->get_id(), '_wcfp_payments', true);
                if (!empty($payments['installments'])) {
                    $has_installments = true;
                    $quantity = $item->get_quantity();
                    $current_date = current_time('Y-m-d');
                    
                    foreach ($payments['installments'] as $installment) {
                        if (strtotime($installment['due_date']) > strtotime($current_date)) {
                            $date = date_i18n(get_option('date_format'), strtotime($installment['due_date']));
                            
                            if (!isset($future_payments[$date])) {
                                $future_payments[$date] = array(
                                    'amount' => 0,
                                    'installments' => array()
                                );
                            }
                            
                            $amount = $installment['amount'] * $quantity;
                            $future_payments[$date]['amount'] += $amount;
                            $total_pending += $amount;
                            
                            $future_payments[$date]['installments'][] = array(
                                'number' => $installment['number'],
                                'product_name' => $item->get_name(),
                                'amount' => $amount
                            );
                        }
                    }
                }
            }
        }

        if ($has_installments) {
            $note = sprintf(
                /* translators: %s: total pending amount */
                __('Future Payments Schedule (Total Pending: %s):', 'wc-flex-pay'),
                wc_price($total_pending)
            ) . "\n\n";

            ksort($future_payments);
            foreach ($future_payments as $date => $payment) {
                $note .= sprintf(
                    /* translators: %1$s: date, %2$s: amount */
                    __('%1$s - Total: %2$s', 'wc-flex-pay') . "\n",
                    $date,
                    wc_price($payment['amount'])
                );

                foreach ($payment['installments'] as $installment) {
                    $note .= sprintf(
                        /* translators: 1: product name, 2: installment number, 3: amount */
                        __('  • %1$s (Installment %2$d) - %3$s', 'wc-flex-pay') . "\n",
                        $installment['product_name'],
                        $installment['number'],
                        wc_price($installment['amount'])
                    );
                }
                $note .= "\n";
            }

            // Add payment instructions
            $note .= __('Payment Instructions:', 'wc-flex-pay') . "\n";
            $note .= __('• You will receive payment links for each installment via email', 'wc-flex-pay') . "\n";
            $note .= __('• Each payment must be completed by its due date', 'wc-flex-pay') . "\n";
            $note .= __('• Payment links can also be found in your account dashboard', 'wc-flex-pay');

            $order->add_order_note($note);
        }
    }
}
