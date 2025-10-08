<?php
/**
 * Precision Settings class
 *
 * Handles decimal precision configuration for backend display
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Settings_Precision {

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
        add_action('woocommerce_admin_field_wcfst_precision_info', array($this, 'render_precision_info'));
        
        // Apply decimal precision override if enabled
        $this->apply_precision_override();
    }

    /**
     * Get precision settings
     */
    public function get_settings() {
        return array(
            array(
                'title' => __('Decimal Precision Settings', 'wc-fix-shipping-tax'),
                'type'  => 'title',
                'desc'  => __('Configure WooCommerce internal rounding precision. This affects how calculations are displayed in the backend and can help align with your tax requirements.', 'wc-fix-shipping-tax'),
                'id'    => 'wcfst_precision_title',
            ),
            array(
                'title' => __('Current Precision', 'wc-fix-shipping-tax'),
                'type'  => 'wcfst_precision_info',
            ),
            array(
                'title'   => __('Override Rounding Precision', 'wc-fix-shipping-tax'),
                'desc'    => __('Enable custom rounding precision override for backend display.', 'wc-fix-shipping-tax'),
                'id'      => 'wcfst_enable_precision_override',
                'type'    => 'checkbox',
                'default' => 'no',
                'desc_tip' => __('This changes how WooCommerce displays calculations in the admin area. It does not affect order item prices saved to the database.', 'wc-fix-shipping-tax'),
            ),
            array(
                'title'             => __('Decimal Precision', 'wc-fix-shipping-tax'),
                'desc'              => __('Number of decimal places for internal calculations and backend display. Default is 6. Set to 2 for standard currency precision.', 'wc-fix-shipping-tax'),
                'id'                => 'wcfst_precision_value',
                'type'              => 'number',
                'default'           => '2',
                'custom_attributes' => array(
                    'min'  => '0',
                    'max'  => '10',
                    'step' => '1',
                ),
                'desc_tip' => __('This setting only affects how calculations are displayed in the WooCommerce admin. For order item precision (e.g., Fiken integration), use the setting in the Fiken Integration section below.', 'wc-fix-shipping-tax'),
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wcfst_precision_section_end',
            ),
        );
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
                    <?php _e('The internal rounding precision affects how WooCommerce displays calculations in the admin area. This is separate from the order item precision used for Fiken integration.', 'wc-fix-shipping-tax'); ?>
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
}
