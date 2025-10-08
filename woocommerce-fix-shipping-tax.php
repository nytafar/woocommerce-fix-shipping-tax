<?php
/**
 * Plugin Name: WooCommerce Fix Shipping Tax
 * Plugin URI: https://jellum.net/
 * Description: Fixes shipping tax calculations in existing orders to ensure proper VAT distribution
 * Version: 3.0.0
 * Author: Lasse Jellum
 * Text Domain: wc-fix-shipping-tax
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WCFST_VERSION', '2.0.0');
define('WCFST_PLUGIN_FILE', __FILE__);
define('WCFST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCFST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCFST_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class WC_Fix_Shipping_Tax {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Plugin modules
     */
    private $modules = array();
    
    /**
     * Main plugin instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load plugin
        $this->includes();
        $this->init_modules();
        $this->init_hooks();
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Show notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('WooCommerce Fix Shipping Tax requires WooCommerce to be installed and activated.', 'wc-fix-shipping-tax'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core includes
        require_once WCFST_PLUGIN_DIR . 'includes/class-wcfst-core.php';
        
        // Admin includes
        if (is_admin()) {
            require_once WCFST_PLUGIN_DIR . 'includes/admin/class-wcfst-admin.php';
            require_once WCFST_PLUGIN_DIR . 'includes/admin/class-wcfst-order-meta-box.php';
            require_once WCFST_PLUGIN_DIR . 'includes/admin/class-wcfst-order-list.php';
            require_once WCFST_PLUGIN_DIR . 'includes/admin/class-wcfst-bulk-actions.php';
            
            // Settings includes
            require_once WCFST_PLUGIN_DIR . 'includes/admin/settings/class-wcfst-settings-precision.php';
            require_once WCFST_PLUGIN_DIR . 'includes/admin/settings/class-wcfst-settings-fiken.php';
            require_once WCFST_PLUGIN_DIR . 'includes/admin/settings/class-wcfst-settings-tools.php';
            require_once WCFST_PLUGIN_DIR . 'includes/admin/settings/class-wcfst-settings-base.php';
            
            // Keep old settings class for backward compatibility
            require_once WCFST_PLUGIN_DIR . 'includes/admin/class-wcfst-settings.php';
        }
    }
    
    /**
     * Initialize modules
     */
    private function init_modules() {
        // Core module - always loaded
        $this->modules['core'] = new WCFST_Core();
        
        // Admin modules
        if (is_admin()) {
            $this->modules['settings'] = new WCFST_Settings();
            $settings = WCFST_Settings::get_settings();

            $this->modules['admin'] = new WCFST_Admin($this->modules['core']);
            $this->modules['order_meta_box'] = new WCFST_Order_Meta_Box($this->modules['core']);
            $this->modules['bulk_actions'] = new WCFST_Bulk_Actions($this->modules['core']);

            if ($settings['enable_order_list_column']) {
                $this->modules['order_list'] = new WCFST_Order_List($this->modules['core']);
            }
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(WCFST_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(WCFST_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Plugin loaded
        do_action('wcfst_loaded');
    }
    
    /**
     * Get a module instance
     */
    public function get_module($name) {
        return isset($this->modules[$name]) ? $this->modules[$name] : null;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        // Set default options
        update_option('wcfst_version', WCFST_VERSION);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
    }
}

/**
 * Main function to get plugin instance
 */
function WCFST() {
    return WC_Fix_Shipping_Tax::instance();
}

// Initialize plugin
add_action('plugins_loaded', 'WCFST', 10);
