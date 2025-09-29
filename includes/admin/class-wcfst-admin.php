<?php
/**
 * Admin main class
 * 
 * Coordinates all admin functionality
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Admin {
    
    /**
     * Core module reference
     */
    private $core;
    
    /**
     * Constructor
     */
    public function __construct($core) {
        $this->core = $core;
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add order actions
        add_filter('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_wcfst_preview_15', array($this, 'preview_tax_15'));
        add_action('woocommerce_order_action_wcfst_apply_15', array($this, 'apply_tax_15'));
        add_action('woocommerce_order_action_wcfst_preview_25', array($this, 'preview_tax_25'));
        add_action('woocommerce_order_action_wcfst_apply_25', array($this, 'apply_tax_25'));
        
        // Make orders editable regardless of status
        add_filter('wc_order_is_editable', array($this, 'make_orders_editable'), 999, 2);
        add_filter('woocommerce_admin_order_should_be_locked', '__return_false', 999);
        add_filter('woocommerce_valid_order_statuses_for_payment', array($this, 'expand_valid_order_statuses'), 999, 2);
        
        // Filter order item meta data if needed
        add_filter('woocommerce_order_item_get_formatted_meta_data', array($this, 'filter_order_item_meta'), 10, 2);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only on order pages
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('shop_order', 'edit-shop_order'))) {
            return;
        }
        
        // Enqueue style
        wp_enqueue_style(
            'wcfst-admin',
            WCFST_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WCFST_VERSION
        );
        
        // Enqueue script
        wp_enqueue_script(
            'wcfst-admin',
            WCFST_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WCFST_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wcfst-admin', 'wcfst', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcfst-nonce'),
            'i18n' => array(
                'error' => __('An error occurred', 'wc-fix-shipping-tax'),
                'success' => __('Success!', 'wc-fix-shipping-tax'),
            ),
        ));
    }
    
    /**
     * Add order actions
     */
    public function add_order_actions($actions) {
        $actions['wcfst_preview_15'] = __('Preview Shipping Tax Fix (15%)', 'wc-fix-shipping-tax');
        $actions['wcfst_apply_15'] = __('Apply Shipping Tax Fix (15%)', 'wc-fix-shipping-tax');
        $actions['wcfst_preview_25'] = __('Preview Shipping Tax Fix (25%)', 'wc-fix-shipping-tax');
        $actions['wcfst_apply_25'] = __('Apply Shipping Tax Fix (25%)', 'wc-fix-shipping-tax');
        
        return $actions;
    }
    
    /**
     * Preview tax fix at 15%
     */
    public function preview_tax_15($order) {
        $this->preview_tax_fix($order, 15);
    }
    
    /**
     * Preview tax fix at 25%
     */
    public function preview_tax_25($order) {
        $this->preview_tax_fix($order, 25);
    }
    
    /**
     * Apply tax fix at 15%
     */
    public function apply_tax_15($order) {
        $this->apply_tax_fix($order, 15);
    }
    
    /**
     * Apply tax fix at 25%
    public function apply_tax_25($order) {
        $this->apply_tax_fix($order, 25);
    }
    
    /**
     * Preview tax fix
     */
    private function preview_tax_fix($order, $tax_rate) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        $calculations = $this->core->calculate_shipping_tax_fix($order, $tax_rate);
        
        if (empty($calculations)) {
            $order->add_order_note(__('No shipping items found for tax fix preview', 'wc-fix-shipping-tax'));
            return;
        }
        
        foreach ($calculations as $calc) {
            $message = sprintf(
                __("Preview Shipping Tax Fix (%d%%):\n", 'wc-fix-shipping-tax'),
                $tax_rate
            );
            
            $message .= sprintf(
                __("Shipping: %s\n", 'wc-fix-shipping-tax'),
                $calc['shipping_method']
            );
            
            $message .= sprintf(
                __("Current: Base %s + VAT %s = %s\n", 'wc-fix-shipping-tax'),
                wc_price($calc['current_base']),
                wc_price($calc['current_vat']),
                wc_price($calc['current_total'])
            );
            
            $message .= sprintf(
                __("Proposed: Base %s + VAT %s = %s\n", 'wc-fix-shipping-tax'),
                wc_price($calc['new_base']),
                wc_price($calc['new_vat']),
                wc_price($calc['new_total'])
            );
            
            if ($calc['totals_match']) {
                $message .= __("✅ Total remains unchanged", 'wc-fix-shipping-tax');
            } else {
                $message .= __("⚠️ Warning: Total would change!", 'wc-fix-shipping-tax');
            }
            
            $order->add_order_note($message);
        }
    }
    
    /**
     * Make all orders editable regardless of status
     *
     * @param bool $is_editable Whether the order is editable
     * @param WC_Order $order The order object
     */
    public function make_orders_editable($is_editable, $order) {
        // Always return true to make all orders editable
        return true;
    }
    
    /**
     * Expand valid order statuses for payment
     *
     * @param array $statuses Valid order statuses
     * @param string $action The current action
     * @return array Expanded list of valid order statuses
     */
    public function expand_valid_order_statuses($statuses, $action) {
        // Add all possible order statuses to the valid statuses list
        $all_statuses = array_keys(wc_get_order_statuses());
        return array_unique(array_merge($statuses, $all_statuses));
    }
    
    /**
     * Filter order item meta to hide or modify certain meta data
     *
     * @param array $formatted_meta The formatted meta data
     * @param WC_Order_Item $order_item The order item object
     * @return array The filtered meta data
     */
    public function filter_order_item_meta($formatted_meta, $order_item) {
        // You can filter out specific meta keys if needed
        return $formatted_meta;
    }
}
