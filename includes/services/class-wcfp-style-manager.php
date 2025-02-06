<?php
/**
 * Style Manager Class
 *
 * @package WC_Flex_Pay\Services
 */

namespace WCFP\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Style Manager Class
 * 
 * Handles email styles and provides consistent styling across all emails
 */
class Style_Manager {
    /**
     * Instance of this class
     *
     * @var Style_Manager
     */
    private static $instance = null;

    /**
     * Style cache
     *
     * @var array
     */
    private $style_cache = array();

    /**
     * Get class instance
     *
     * @return Style_Manager
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get all email styles
     *
     * @return string Combined styles
     */
    public function get_styles() {
        if (isset($this->style_cache['all'])) {
            return $this->style_cache['all'];
        }

        ob_start();
        
        // Load all style components
        $this->load_style('common');
        $this->load_style('components');
        $this->load_style('layout');

        $styles = ob_get_clean();
        $this->style_cache['all'] = $styles;

        return $styles;
    }

    /**
     * Get specific component styles
     *
     * @param string $component Component name
     * @return string Component styles
     */
    public function get_component_styles($component) {
        if (isset($this->style_cache[$component])) {
            return $this->style_cache[$component];
        }

        ob_start();
        $this->load_style($component);
        $styles = ob_get_clean();
        $this->style_cache[$component] = $styles;

        return $styles;
    }

    /**
     * Load style file
     *
     * @param string $name Style file name
     */
    private function load_style($name) {
        $file = WCFP_PLUGIN_DIR . "templates/emails/styles/{$name}.php";
        if (file_exists($file)) {
            include $file;
        }
    }

    /**
     * Get common CSS variables
     *
     * @return array CSS variables
     */
    public function get_variables() {
        return array(
            '--brand-color' => '#8d0759',
            '--brand-color-light' => '#a6086a',
            '--brand-color-dark' => '#740648',
            '--brand-color-bg' => '#f9e6f2',
            '--text-color' => '#333333',
            '--text-color-light' => '#666666',
            '--border-color' => '#e5e5e5',
            '--success-color' => '#5b841b',
            '--warning-color' => '#94660c',
            '--error-color' => '#761919'
        );
    }

    /**
     * Get status badge styles
     *
     * @param string $status Status type
     * @return array Style properties
     */
    public function get_status_badge_styles($status) {
        $styles = array(
            'pending' => array(
                'background' => '#f8dda7',
                'color' => '#94660c'
            ),
            'completed' => array(
                'background' => '#c6e1c6',
                'color' => '#5b841b'
            ),
            'overdue' => array(
                'background' => '#eba3a3',
                'color' => '#761919'
            ),
            'failed' => array(
                'background' => '#eba3a3',
                'color' => '#761919'
            )
        );

        return isset($styles[$status]) ? $styles[$status] : $styles['pending'];
    }

    /**
     * Get button styles
     *
     * @param string $type Button type (primary, secondary)
     * @return array Style properties
     */
    public function get_button_styles($type = 'primary') {
        $styles = array(
            'primary' => array(
                'background' => '#8d0759',
                'color' => '#ffffff',
                'border' => 'none'
            ),
            'secondary' => array(
                'background' => '#f9e6f2',
                'color' => '#8d0759',
                'border' => '1px solid #8d0759'
            )
        );

        return isset($styles[$type]) ? $styles[$type] : $styles['primary'];
    }

    /**
     * Get table styles
     *
     * @return array Style properties
     */
    public function get_table_styles() {
        return array(
            'border' => '1px solid #e5e5e5',
            'background' => '#ffffff',
            'header_background' => '#f8f8f8',
            'cell_padding' => '12px',
            'border_color' => '#e5e5e5'
        );
    }
}
