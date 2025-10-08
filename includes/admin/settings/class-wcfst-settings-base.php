<?php
/**
 * Base Settings class
 *
 * Handles core settings functionality and coordination
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Settings_Base {

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
        add_filter('woocommerce_get_sections_tax', array($this, 'add_tax_section'));
        add_filter('woocommerce_get_settings_tax', array($this, 'add_tax_settings'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Add settings section to the Tax tab
     */
    public function add_tax_section($sections) {
        $sections['wcfst'] = __('Shipping Tax Fix', 'wc-fix-shipping-tax');
        return $sections;
    }

    /**
     * Add settings fields to the new section
     */
    public function add_tax_settings($settings, $current_section) {
        if ('wcfst' === $current_section) {
            $wcfst_settings = array();
            
            // General settings
            $wcfst_settings = array_merge($wcfst_settings, $this->get_general_settings());
            
            // Precision settings
            $precision_settings = new WCFST_Settings_Precision();
            $wcfst_settings = array_merge($wcfst_settings, $precision_settings->get_settings());
            
            // Fiken integration settings
            $fiken_settings = new WCFST_Settings_Fiken();
            $wcfst_settings = array_merge($wcfst_settings, $fiken_settings->get_settings());
            
            // Order editing settings
            $order_editing_settings = new WCFST_Settings_Order_Editing();
            $wcfst_settings = array_merge($wcfst_settings, $order_editing_settings->get_settings());
            
            // Tools settings
            $tools_settings = new WCFST_Settings_Tools();
            $wcfst_settings = array_merge($wcfst_settings, $tools_settings->get_settings());
            
            return $wcfst_settings;
        }
        return $settings;
    }

    /**
     * Get general settings
     */
    private function get_general_settings() {
        return array(
            array(
                'title' => __('Shipping Tax Fix Settings', 'wc-fix-shipping-tax'),
                'type'  => 'title',
                'id'    => 'wcfst_title',
            ),
            array(
                'title'   => __('Enable Order List Column', 'wc-fix-shipping-tax'),
                'desc'    => __('Enable the "Shipping Tax" column and filter in the WooCommerce order list.', 'wc-fix-shipping-tax'),
                'id'      => 'wcfst_enable_order_list_column',
                'type'    => 'checkbox',
                'default' => 'no',
            ),
            array(
                'title'   => __('Create Order Note', 'wc-fix-shipping-tax'),
                'desc'    => __('Add a detailed note to the order after applying a fix.', 'wc-fix-shipping-tax'),
                'id'      => 'wcfst_auto_backup',
                'type'    => 'checkbox',
                'default' => 'yes',
            ),
            array(
                'title'   => __('Enable Debug Logging', 'wc-fix-shipping-tax'),
                'desc'    => __('Enable detailed debug logging for troubleshooting.', 'wc-fix-shipping-tax'),
                'id'      => 'wcfst_enable_logging',
                'type'    => 'checkbox',
                'default' => 'no',
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wcfst_section_end',
            ),
        );
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'tax') {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
            
            wp_enqueue_script('wcfst-admin-settings', WCFST_PLUGIN_URL . 'assets/js/admin-settings.js', array('jquery'), WCFST_VERSION, true);
            wp_localize_script('wcfst-admin-settings', 'wcfst_settings_data', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcfst_tools_ajax_nonce'),
            ));

            wc_enqueue_js("
                jQuery(function($) {
                    $('.wcfst-datepicker').datepicker({
                        dateFormat: 'yy-mm-dd'
                    });
                });
            ");
        }
    }

    /**
     * Get plugin settings in the old format for backward compatibility
     */
    public static function get_settings() {
        $settings = array();
        $settings['enable_order_list_column'] = get_option('wcfst_enable_order_list_column', 'no') === 'yes';
        $settings['auto_backup'] = get_option('wcfst_auto_backup', 'yes') === 'yes';
        $settings['enable_logging'] = get_option('wcfst_enable_logging', 'no') === 'yes';
        $settings['enable_precision_override'] = get_option('wcfst_enable_precision_override', 'no') === 'yes';
        $settings['precision_value'] = absint(get_option('wcfst_precision_value', '2'));
        $settings['enable_item_rounding'] = get_option('wcfst_enable_item_rounding', 'no') === 'yes';
        $settings['item_precision_value'] = absint(get_option('wcfst_item_precision_value', '2'));
        $settings['enable_order_editing'] = get_option('wcfst_enable_order_editing', 'no') === 'yes';
        return $settings;
    }
}
