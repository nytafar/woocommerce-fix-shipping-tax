<?php
/**
 * Plugin Settings class
 *
 * Handles plugin configuration and settings page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Settings {

    /**
     * Settings page slug
     */
    const PAGE_SLUG = 'wcfst-settings';

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
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'handle_tools_actions'));
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Fix Shipping Tax Settings', 'wc-fix-shipping-tax'),
            __('Fix Shipping Tax', 'wc-fix-shipping-tax'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'wcfst_settings_group',
            'wcfst_settings',
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'wcfst_general_section',
            __('General Settings', 'wc-fix-shipping-tax'),
            array($this, 'render_general_section'),
            'wcfst_settings_group'
        );

        add_settings_field(
            'wcfst_enable_logging',
            __('Enable Debug Logging', 'wc-fix-shipping-tax'),
            array($this, 'render_logging_field'),
            'wcfst_settings_group',
            'wcfst_general_section'
        );

        add_settings_field(
            'wcfst_auto_backup',
            __('Create Order Backup', 'wc-fix-shipping-tax'),
            array($this, 'render_backup_field'),
            'wcfst_settings_group',
            'wcfst_general_section'
        );

        add_settings_section(
            'wcfst_tools_section',
            __('Tools', 'wc-fix-shipping-tax'),
            null,
            'wcfst_settings_group'
        );

        add_settings_field(
            'wcfst_update_meta_field',
            __('Update Order Meta', 'wc-fix-shipping-tax'),
            array($this, 'render_update_meta_field'),
            'wcfst_settings_group',
            'wcfst_tools_section'
        );
    }

    /**
     * Enqueue settings scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'woocommerce_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'wcfst-admin',
            WCFST_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WCFST_VERSION
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('WooCommerce Fix Shipping Tax Settings', 'wc-fix-shipping-tax'); ?></h1>

            <p class="description">
                <?php _e('Configure the behavior of the shipping tax fix plugin.', 'wc-fix-shipping-tax'); ?>
            </p>

            <form method="post" action="options.php">
                <?php
                settings_fields('wcfst_settings_group');
                do_settings_sections('wcfst_settings_group');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general settings section
     */
    public function render_general_section() {
        echo '<p>' . __('Configure general plugin behavior:', 'wc-fix-shipping-tax') . '</p>';
    }

    /**
     * Render logging field
     */
    public function render_logging_field() {
        $settings = get_option('wcfst_settings', array());
        $enabled = isset($settings['enable_logging']) ? $settings['enable_logging'] : false;
        ?>
        <fieldset>
            <legend class="screen-reader-text">
                <span><?php _e('Enable Debug Logging', 'wc-fix-shipping-tax'); ?></span>
            </legend>
            <label for="wcfst_enable_logging">
                <input type="checkbox"
                       id="wcfst_enable_logging"
                       name="wcfst_settings[enable_logging]"
                       value="1"
                       <?php checked($enabled, true); ?> />
                <?php _e('Enable detailed debug logging for troubleshooting', 'wc-fix-shipping-tax'); ?>
            </label>
            <p class="description">
                <?php _e('When enabled, detailed logs will be written to the WooCommerce log files.', 'wc-fix-shipping-tax'); ?>
            </p>
        </fieldset>
        <?php
    }

    /**
     * Render backup field
     */
    public function render_backup_field() {
        $settings = get_option('wcfst_settings', array());
        $enabled = isset($settings['auto_backup']) ? $settings['auto_backup'] : true;
        ?>
        <fieldset>
            <legend class="screen-reader-text">
                <span><?php _e('Create Order Backup', 'wc-fix-shipping-tax'); ?></span>
            </legend>
            <label for="wcfst_auto_backup">
                <input type="checkbox"
                       id="wcfst_auto_backup"
                       name="wcfst_settings[auto_backup]"
                       value="1"
                       <?php checked($enabled, true); ?> />
                <?php _e('Create automatic backup notes before applying fixes', 'wc-fix-shipping-tax'); ?>
            </label>
            <p class="description">
                <?php _e('When enabled, the plugin will add order notes showing original values before applying fixes.', 'wc-fix-shipping-tax'); ?>
            </p>
        </fieldset>
        <?php
    }

    /**
     * Render update meta field
     */
    public function render_update_meta_field() {
        ?>
        <p><?php _e('This tool will scan all your orders and save the shipping tax rate to make filtering on the order list page faster and more accurate.', 'wc-fix-shipping-tax'); ?></p>
        <form method="post">
            <input type="hidden" name="wcfst_action" value="update_meta">
            <?php wp_nonce_field('wcfst_update_meta_nonce', 'wcfst_nonce'); ?>
            <?php submit_button(__('Start Meta Update', 'wc-fix-shipping-tax'), 'secondary', 'wcfst_update_meta_submit'); ?>
        </form>
        <?php
    }

    /**
     * Handle tools actions
     */
    public function handle_tools_actions() {
        if (isset($_POST['wcfst_action']) && $_POST['wcfst_action'] === 'update_meta') {
            if (!isset($_POST['wcfst_nonce']) || !wp_verify_nonce($_POST['wcfst_nonce'], 'wcfst_update_meta_nonce')) {
                return;
            }
            if (!current_user_can('manage_woocommerce')) {
                return;
            }

            $core = WCFST()->get_module('core');
            if ($core) {
                $core->schedule_meta_update();
            }

            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Order meta update process has been scheduled. It will run in the background.', 'wc-fix-shipping-tax') . '</p></div>';
            });
        }
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['enable_logging'] = isset($input['enable_logging']) ? (bool) $input['enable_logging'] : false;
        $sanitized['auto_backup'] = isset($input['auto_backup']) ? (bool) $input['auto_backup'] : true;

        return $sanitized;
    }

    /**
     * Get plugin settings
     */
    public static function get_settings() {
        return get_option('wcfst_settings', array(
            'enable_logging' => false,
            'auto_backup' => true,
        ));
    }

    /**
     * Check if logging is enabled
     */
    public static function is_logging_enabled() {
        $settings = self::get_settings();
        return isset($settings['enable_logging']) && $settings['enable_logging'];
    }

    /**
     * Check if auto backup is enabled
     */
    public static function is_auto_backup_enabled() {
        $settings = self::get_settings();
        return isset($settings['auto_backup']) ? $settings['auto_backup'] : true;
    }
}
