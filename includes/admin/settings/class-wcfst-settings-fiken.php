<?php
/**
 * Fiken Integration Settings class
 *
 * Handles order item price rounding for Fiken and similar accounting systems
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Settings_Fiken {

    /**
     * Get Fiken integration settings
     */
    public function get_settings() {
        return array(
            array(
                'title' => __('Fiken Integration Settings', 'wc-fix-shipping-tax'),
                'type'  => 'title',
                'desc'  => __('Configure order item price rounding for Fiken and similar accounting system integrations.', 'wc-fix-shipping-tax'),
                'id'    => 'wcfst_item_rounding_title',
            ),
            array(
                'title'   => __('Enable Order Item Price Rounding', 'wc-fix-shipping-tax'),
                'desc'    => __('Round order item prices during checkout to eliminate rounding errors.', 'wc-fix-shipping-tax'),
                'id'      => 'wcfst_enable_item_rounding',
                'type'    => 'checkbox',
                'default' => 'no',
                'desc_tip' => __(' This saves order item prices with the specified decimal precision below, eliminating rounding errors when transferring orders to accounting systems. Trade-off: Order totals may show prices like 1308,01 due to 2-decimal tax calculations.', 'wc-fix-shipping-tax'),
            ),
            array(
                'title'             => __('Order Item Decimal Precision', 'wc-fix-shipping-tax'),
                'desc'              => __('<strong>Fiken likes 2 decimals.</strong> This controls the decimal precision for order item prices saved to the database during checkout.', 'wc-fix-shipping-tax'),
                'id'                => 'wcfst_item_precision_value',
                'type'              => 'number',
                'default'           => '2',
                'custom_attributes' => array(
                    'min'  => '0',
                    'max'  => '10',
                    'step' => '1',
                ),
                'desc_tip' => __('This is independent from the "Decimal Precision" setting above. For Fiken and most accounting systems, use 2 decimals.', 'wc-fix-shipping-tax'),
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wcfst_item_rounding_section_end',
            ),
        );
    }
}
