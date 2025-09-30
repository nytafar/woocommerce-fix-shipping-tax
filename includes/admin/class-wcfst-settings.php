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
        add_action('woocommerce_admin_field_wcfst_date_range_picker', array($this, 'render_date_range_picker'));
        add_action('admin_init', array($this, 'handle_tools_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_datepicker_scripts'));
    }

    /**
     * Enqueue datepicker scripts
     */
    public function enqueue_datepicker_scripts($hook) {
        if ($hook === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'tax') {
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
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
            $wcfst_settings = array(
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
                array(
                    'title' => __('Tools', 'wc-fix-shipping-tax'),
                    'type'  => 'title',
                    'id'    => 'wcfst_tools_title',
                ),
                array(
                    'title' => __('Process Existing Orders', 'wc-fix-shipping-tax'),
                    'type'  => 'wcfst_date_range_picker',
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wcfst_tools_section_end',
                ),
            );
            return $wcfst_settings;
        }
        return $settings;
    }

    /**
     * Render the date range picker tool
     */
    public function render_date_range_picker() {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php _e('Update Order Meta', 'wc-fix-shipping-tax'); ?>
            </th>
            <td class="forminp">
                <form method="post">
                    <input type="text" name="wcfst_start_date" class="wcfst-datepicker" placeholder="Start Date (YYYY-MM-DD)" />
                    <input type="text" name="wcfst_end_date" class="wcfst-datepicker" placeholder="End Date (YYYY-MM-DD)" />
                    <p class="description">
                        <?php _e('Select a date range to process orders and populate the shipping tax meta for filtering. Leave blank to process all orders.', 'wc-fix-shipping-tax'); ?>
                    </p>
                    <input type="hidden" name="wcfst_action" value="update_meta">
                    <?php wp_nonce_field('wcfst_update_meta_nonce', 'wcfst_nonce'); ?>
                    <button type="submit" class="button-secondary" style="margin-top: 10px;"><?php _e('Start Processing', 'wc-fix-shipping-tax'); ?></button>
                </form>
            </td>
        </tr>
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

            $start_date = !empty($_POST['wcfst_start_date']) ? sanitize_text_field($_POST['wcfst_start_date']) : '';
            $end_date = !empty($_POST['wcfst_end_date']) ? sanitize_text_field($_POST['wcfst_end_date']) : '';

            $core = WCFST()->get_module('core');
            if ($core) {
                $core->schedule_meta_update($start_date, $end_date);
            }

            add_action('admin_notices', function() {
                $message = __('Order meta update process has been scheduled. It will run in the background.', 'wc-fix-shipping-tax');
                echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
            });
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
        return $settings;
    }
}