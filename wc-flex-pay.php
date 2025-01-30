<?php
/**
 * WC Flex Pay
 *
 * @package     WC_Flex_Pay
 * @author      Ajith
 * @copyright   2024 Ajith
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: WC Flex Pay
 * Plugin URI:  https://kwirx.com/wc-flex-pay
 * Description: Enable selling products with scheduled partial payments in WooCommerce
 * Version:     1.6.4
 * Author:      Ajith R N
 * Author URI:  https://kwirx.com
 * Text Domain: wc-flex-pay
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('WCFP_VERSION', '1.6.4');
define('WCFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCFP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCFP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Debug mode constant.
if (!defined('WCFP_DEBUG')) {
    define('WCFP_DEBUG', false);
}

// Template paths
if (!defined('WCFP_TEMPLATE_PATH')) {
    define('WCFP_TEMPLATE_PATH', 'wc-flex-pay/');
}

/**
 * Check if WooCommerce is active
 *
 * @return bool
 */
function wcfp_is_woocommerce_active() {
    $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
    }
    return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

// Show admin notice if WooCommerce is not active
if (!wcfp_is_woocommerce_active()) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('WC Flex Pay requires WooCommerce to be installed and activated.', 'wc-flex-pay'); ?></p>
        </div>
        <?php
    });
    return;
}

/**
 * Autoloader for plugin classes
 */
spl_autoload_register(function($class) {
    // Project-specific namespace prefix
    $prefix = 'WCFP\\';
    
    // Base directory for the namespace prefix
    $base_dir = WCFP_PLUGIN_DIR . 'includes/';
    
    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main plugin class
 */
final class WC_Flex_Pay {
    /**
     * Single instance of the class
     *
     * @var WC_Flex_Pay
     */
    protected static $instance = null;

    /**
     * Main WC_Flex_Pay Instance
     *
     * Ensures only one instance of WC_Flex_Pay is loaded or can be loaded.
     *
     * @static
     * @return WC_Flex_Pay - Main instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * WC_Flex_Pay Constructor.
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'), 0);
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load plugin text domain
        load_plugin_textdomain('wc-flex-pay', false, dirname(WCFP_PLUGIN_BASENAME) . '/languages');

        // Initialize components
        $this->init_components();

        // Update version if needed
        if (get_option('wcfp_version') != WCFP_VERSION) {
            update_option('wcfp_version', WCFP_VERSION);
        }
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Load and initialize core components
        require_once WCFP_PLUGIN_DIR . 'includes/class-wcfp-product.php';
        require_once WCFP_PLUGIN_DIR . 'includes/class-wcfp-order.php';
        require_once WCFP_PLUGIN_DIR . 'includes/class-wcfp-payment.php';
        require_once WCFP_PLUGIN_DIR . 'includes/class-wcfp-notification.php';
        require_once WCFP_PLUGIN_DIR . 'includes/class-wcfp-emails.php';

        // Initialize core components
        new \WCFP\Product();
        new \WCFP\Order();
        new \WCFP\Notification();
        new \WCFP\Emails();

        // Add template include filter
        add_filter('woocommerce_locate_template', array($this, 'locate_template'), 20, 3);

        // Admin
        if (is_admin()) {
            require_once WCFP_PLUGIN_DIR . 'includes/admin/class-wcfp-admin.php';
        }

        // Add frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    /**
     * Activation Hook
     */
    public static function activate() {
        // Check if WooCommerce is active
        if (!wcfp_is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('WC Flex Pay requires WooCommerce to be installed and activated.', 'wc-flex-pay'));
        }

        // Load Install class
        require_once WCFP_PLUGIN_DIR . 'includes/class-wcfp-install.php';
        
        try {
            // Add capabilities
            \WCFP\Install::add_capabilities();
            
            // Set default options
            \WCFP\Install::set_default_options();
            
            // Update version
            update_option('wcfp_version', WCFP_VERSION);
            
            // Clear the permalinks
            flush_rewrite_rules();
        } catch (\Exception $e) {
            wp_die(sprintf(
                __('Error activating WC Flex Pay: %s', 'wc-flex-pay'),
                $e->getMessage()
            ));
        }
    }

    /**
     * Deactivation Hook
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (!is_product() && !is_cart() && !is_checkout() && !is_account_page()) {
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
            )
        );
    }

    /**
     * Debug log
     *
     * @param mixed $message
     */
    public static function log($message) {
        if (defined('WCFP_DEBUG') && WCFP_DEBUG) {
            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }
            
            $logger = wc_get_logger();
            $logger->debug($message, array('source' => 'wc-flex-pay'));

            // Also log to error log for immediate visibility
            error_log('[WC Flex Pay] ' . $message);
        }
    }

    /**
     * Locate template files
     *
     * @param string $template      Template file
     * @param string $template_name Template name
     * @param string $template_path Template path
     * @return string
     */
    public function locate_template($template, $template_name, $template_path) {
        // Only look for our templates
        if (strpos($template_name, 'wcfp_') === false && strpos($template_name, 'wc-flex-pay/') === false) {
            return $template;
        }

        // Remove wcfp_ prefix if present
        $template_name = str_replace('wcfp_', '', $template_name);

        // Get template paths
        $plugin_path = WCFP_PLUGIN_DIR . 'templates/';
        $theme_path = get_stylesheet_directory() . '/' . WCFP_TEMPLATE_PATH;

        // Look within passed path within the theme
        $template = locate_template(array(
            WCFP_TEMPLATE_PATH . $template_name,
            $template_name,
        ));

        // Get the template from this plugin if not found in theme
        if (!$template && file_exists($plugin_path . $template_name)) {
            $template = $plugin_path . $template_name;
        }

        self::log("Template lookup: {$template_name} -> {$template}");

        return $template;
    }
}

/**
 * Returns the main instance of WC_Flex_Pay
 *
 * @return WC_Flex_Pay
 */
function WCFP() {
    return WC_Flex_Pay::instance();
}

// Initialize the plugin
WCFP();
