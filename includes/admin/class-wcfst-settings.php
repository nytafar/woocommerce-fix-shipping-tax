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
        add_action('woocommerce_admin_field_wcfst_update_meta_button', array($this, 'render_update_meta_button'));
        add_action('admin_init', array($this, 'handle_tools_actions'));
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
                    'title' => __('Update Order Meta', 'wc-fix-shipping-tax'),
                    'type'  => 'wcfst_update_meta_button',
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
     * Render the update meta button
     */
    public function render_update_meta_button() {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php _e('Process Existing Orders', 'wc-fix-shipping-tax'); ?>
            </th>
            <td class="forminp forminp-button">
                <p class="description">
                    <?php _e('This tool will scan your 200 latest orders and save the shipping tax rate to make filtering on the order list page faster and more accurate.', 'wc-fix-shipping-tax'); ?>
                </p>
                <form method="post" style="display: inline-block; margin-top: 10px;">
                    <input type="hidden" name="wcfst_action" value="update_meta">
                    <?php wp_nonce_field('wcfst_update_meta_nonce', 'wcfst_nonce'); ?>
                    <button type="submit" class="button-secondary"><?php _e('Start Meta Update', 'wc-fix-shipping-tax'); ?></button>
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
