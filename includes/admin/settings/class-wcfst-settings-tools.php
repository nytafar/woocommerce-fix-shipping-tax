<?php
/**
 * Tools Settings class
 *
 * Handles tools and utilities for order processing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Settings_Tools {

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
        add_action('woocommerce_admin_field_wcfst_date_range_picker', array($this, 'render_date_range_picker'));
        add_action('wp_ajax_wcfst_run_tool', array($this, 'handle_ajax_actions'));
    }

    /**
     * Get tools settings
     */
    public function get_settings() {
        return array(
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
}
