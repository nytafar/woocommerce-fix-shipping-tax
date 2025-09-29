<?php
/**
 * Bulk Actions class
 * 
 * Handles bulk operations on orders
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Bulk_Actions {
    
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
        // Register bulk actions
        add_filter('bulk_actions-edit-shop_order', array($this, 'register_bulk_actions'));
        
        // Handle bulk actions
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);
        
        // Display admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    /**
     * Register bulk actions
     */
    public function register_bulk_actions($actions) {
        $actions['wcfst_preview_15'] = __('Preview Shipping Tax Fix (15%)', 'wc-fix-shipping-tax');
        $actions['wcfst_apply_15'] = __('Apply Shipping Tax Fix (15%)', 'wc-fix-shipping-tax');
        $actions['wcfst_preview_25'] = __('Preview Shipping Tax Fix (25%)', 'wc-fix-shipping-tax');
        $actions['wcfst_apply_25'] = __('Apply Shipping Tax Fix (25%)', 'wc-fix-shipping-tax');
        
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        // Check if this is our action
        if (!in_array($action, array('wcfst_preview_15', 'wcfst_apply_15', 'wcfst_preview_25', 'wcfst_apply_25'))) {
            return $redirect_to;
        }
        
        // Parse action
        $is_preview = strpos($action, 'preview') !== false;
        $tax_rate = strpos($action, '15') !== false ? 15 : 25;
        
        // Process orders
        $processed = 0;
        $changed = 0;
        $errors = 0;
        
        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);
            
            if (!$order) {
                $errors++;
                continue;
            }
            
            $processed++;
            
            if ($is_preview) {
                // Add preview notes
                $calculations = $this->core->calculate_shipping_tax_fix($order, $tax_rate);
                
                if (!empty($calculations)) {
                    foreach ($calculations as $calc) {
                        $message = sprintf(
                            __("Bulk Preview (%d%%): %s - Current: Base %s + VAT %s, Proposed: Base %s + VAT %s", 'wc-fix-shipping-tax'),
                            $tax_rate,
                            $calc['shipping_method'],
                            wc_price($calc['current_base']),
                            wc_price($calc['current_vat']),
                            wc_price($calc['new_base']),
                            wc_price($calc['new_vat'])
                        );
                        
                        $order->add_order_note($message);
                    }
                }
            } else {
                // Apply fix
                $result = $this->core->apply_shipping_tax_fix($order, $tax_rate);
                
                if ($result['success']) {
                    $changed++;
                    
                    // Update meta for filtering
                    update_post_meta($post_id, '_wcfst_shipping_tax_rate', $tax_rate);
                    update_post_meta($post_id, '_wcfst_last_fix_applied', current_time('mysql'));
                }
            }
        }
        
        // Add query args for admin notice
        $redirect_to = add_query_arg(array(
            'wcfst_bulk_action' => $action,
            'wcfst_processed' => $processed,
            'wcfst_changed' => $changed,
            'wcfst_errors' => $errors,
        ), $redirect_to);
        
        return $redirect_to;
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        if (!isset($_GET['wcfst_bulk_action'])) {
            return;
        }
        
        $action = $_GET['wcfst_bulk_action'];
        $processed = isset($_GET['wcfst_processed']) ? intval($_GET['wcfst_processed']) : 0;
        $changed = isset($_GET['wcfst_changed']) ? intval($_GET['wcfst_changed']) : 0;
        $errors = isset($_GET['wcfst_errors']) ? intval($_GET['wcfst_errors']) : 0;
        
        $is_preview = strpos($action, 'preview') !== false;
        $tax_rate = strpos($action, '15') !== false ? 15 : 25;
        
        if ($is_preview) {
            $message = sprintf(
                _n(
                    'Shipping tax preview (%2$d%%) generated for %1$d order. Check order notes for details.',
                    'Shipping tax preview (%2$d%%) generated for %1$d orders. Check order notes for details.',
                    $processed,
                    'wc-fix-shipping-tax'
                ),
                $processed,
                $tax_rate
            );
        } else {
            $message = sprintf(
                __('Shipping tax fix (%d%%) applied to %d out of %d orders.', 'wc-fix-shipping-tax'),
                $tax_rate,
                $changed,
                $processed
            );
            
            if ($errors > 0) {
                $message .= ' ' . sprintf(
                    _n(
                        '%d order could not be processed.',
                        '%d orders could not be processed.',
                        $errors,
                        'wc-fix-shipping-tax'
                    ),
                    $errors
                );
            }
        }
        
        $class = $errors > 0 ? 'notice-warning' : 'notice-success';
        
        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr($class),
            esc_html($message)
        );
    }
}
