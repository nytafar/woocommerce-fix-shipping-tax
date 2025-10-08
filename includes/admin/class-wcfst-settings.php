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
        add_action('woocommerce_admin_field_wcfst_precision_info', array($this, 'render_precision_info'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_datepicker_scripts'));
        add_action('wp_ajax_wcfst_run_tool', array($this, 'handle_ajax_actions'));
        
        // Apply decimal precision override if enabled
        $this->apply_precision_override();
    }

    /**
     * Enqueue datepicker scripts
     */
    public function enqueue_datepicker_scripts($hook) {
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
                    'title' => __('Decimal Precision Settings', 'wc-fix-shipping-tax'),
                    'type'  => 'title',
                    'desc'  => __('Configure WooCommerce internal rounding precision to align with your tax requirements.', 'wc-fix-shipping-tax'),
                    'id'    => 'wcfst_precision_title',
                ),
                array(
                    'title' => __('Current Precision', 'wc-fix-shipping-tax'),
                    'type'  => 'wcfst_precision_info',
                ),
                array(
                    'title'   => __('Override Rounding Precision', 'wc-fix-shipping-tax'),
                    'desc'    => __('Enable custom rounding precision override.', 'wc-fix-shipping-tax'),
                    'id'      => 'wcfst_enable_precision_override',
                    'type'    => 'checkbox',
                    'default' => 'no',
                ),
                array(
                    'title'             => __('Decimal Precision', 'wc-fix-shipping-tax'),
                    'desc'              => __('Number of decimal places for internal calculations. Default is 6. Set to 2 for standard currency precision.', 'wc-fix-shipping-tax'),
                    'id'                => 'wcfst_precision_value',
                    'type'              => 'number',
                    'default'           => '2',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'max'  => '10',
                        'step' => '1',
                    ),
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wcfst_precision_section_end',
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
     * Render precision information display
     */
    public function render_precision_info() {
        // Get the constant value or default 6 if not defined
        $constant_precision = defined('WC_ROUNDING_PRECISION') ? WC_ROUNDING_PRECISION : 6;
        // Get the filtered precision WooCommerce uses internally
        $filtered_precision = apply_filters('woocommerce_internal_rounding_precision', $constant_precision);
        
        $override_enabled = get_option('wcfst_enable_precision_override', 'no') === 'yes';
        $override_value = get_option('wcfst_precision_value', '2');
        
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php _e('Precision Status', 'wc-fix-shipping-tax'); ?>
            </th>
            <td class="forminp">
                <div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 10px;">
                    <p style="margin: 0 0 10px 0;">
                        <strong><?php _e('WC_ROUNDING_PRECISION constant:', 'wc-fix-shipping-tax'); ?></strong> 
                        <code style="background: #fff; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($constant_precision); ?></code>
                    </p>
                    <p style="margin: 0;">
                        <strong><?php _e('Active internal rounding precision:', 'wc-fix-shipping-tax'); ?></strong> 
                        <code style="background: #fff; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($filtered_precision); ?></code>
                        <?php if ($override_enabled && $filtered_precision != $constant_precision): ?>
                            <span style="color: #d63638; margin-left: 5px;">⚠ <?php _e('(Override active)', 'wc-fix-shipping-tax'); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <p class="description">
                    <?php _e('The internal rounding precision affects how WooCommerce calculates taxes and totals. A higher precision (e.g., 6) can cause rounding discrepancies with standard 2-decimal currency. Setting it to 2 aligns calculations with displayed amounts.', 'wc-fix-shipping-tax'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Apply decimal precision override if enabled
     */
    private function apply_precision_override() {
        $override_enabled = get_option('wcfst_enable_precision_override', 'no') === 'yes';
        
        if ($override_enabled) {
            $precision_value = absint(get_option('wcfst_precision_value', '2'));
            
            add_filter('woocommerce_internal_rounding_precision', function($precision) use ($precision_value) {
                return $precision_value;
            }, 9999);
        }
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
                <input type="text" name="wcfst_start_date" class="wcfst-datepicker" placeholder="Start Date (YYYY-MM-DD)" />
                <input type="text" name="wcfst_end_date" class="wcfst-datepicker" placeholder="End Date (YYYY-MM-DD)" />
                <p class="description">
                    <?php _e('Select a date range to process orders and populate the shipping tax meta for filtering. Leave blank to process all orders.', 'wc-fix-shipping-tax'); ?>
                </p>
                <label for="wcfst_overwrite_existing">
                    <input type="checkbox" name="wcfst_overwrite_existing" id="wcfst_overwrite_existing" value="yes">
                    <?php _e('Overwrite existing meta field values', 'wc-fix-shipping-tax'); ?>
                </label>
                <br>
                <button type="button" id="wcfst-start-processing" class="button-secondary" style="margin-top: 10px;"><?php _e('Start Processing', 'wc-fix-shipping-tax'); ?></button>
                <button type="button" id="wcfst-stop-processing" class="button-primary" style="margin-top: 10px; margin-left: 10px;"><?php _e('Stop Processing', 'wc-fix-shipping-tax'); ?></button>
                <span class="spinner" style="float: none; margin-top: 10px;"></span>
                <div id="wcfst-tool-feedback" style="margin-top: 10px;"></div>
            </td>
        </tr>
        <?php
    }

    /**
     * Handle AJAX actions for tools
     */
    public function handle_ajax_actions() {
        check_ajax_referer('wcfst_tools_ajax_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $action_type = $_POST['action_type'] ?? '';
        $core = WCFST()->get_module('core');

        if ($action_type === 'start') {
            $start_date = !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
            $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
            $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';

            if ($core) {
                $core->schedule_meta_update($start_date, $end_date, $overwrite);
            }
            wp_send_json_success(array('message' => __('Order meta update process has been scheduled.', 'wc-fix-shipping-tax')));
        } elseif ($action_type === 'stop') {
            if ($core) {
                $core->cancel_meta_update();
            }
            wp_send_json_success(array('message' => __('Order meta update process has been stopped.', 'wc-fix-shipping-tax')));
        } else {
            wp_send_json_error(array('message' => 'Invalid action type.'));
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
        return $settings;
    }
}