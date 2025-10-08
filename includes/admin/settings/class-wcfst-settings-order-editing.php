<?php
/**
 * Order Editing Settings class
 *
 * Handles settings for enabling editing of completed orders
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Settings_Order_Editing {

    /**
     * Get order editing settings
     */
    public function get_settings() {
        return array(
            array(
                'title' => __('Order Editing Settings', 'wc-fix-shipping-tax'),
                'type'  => 'title',
                'desc'  => __('Configure whether completed orders can be edited in the admin area.', 'wc-fix-shipping-tax'),
                'id'    => 'wcfst_order_editing_title',
            ),
            array(
                'title'   => __('Enable Editing of Completed Orders', 'wc-fix-shipping-tax'),
                'desc'    => __('Allow editing of orders regardless of their status (including completed orders).', 'wc-fix-shipping-tax'),
                'id'      => 'wcfst_enable_order_editing',
                'type'    => 'checkbox',
                'default' => 'no',
                'desc_tip' => __('When enabled, all orders become editable in the admin area, even after they are marked as completed. This is useful for applying shipping tax fixes to historical orders. Use with caution as editing completed orders can affect your records.', 'wc-fix-shipping-tax'),
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wcfst_order_editing_section_end',
            ),
        );
    }
}
