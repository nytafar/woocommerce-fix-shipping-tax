<?php
/**
 * Core functionality class
 * 
 * Handles all tax calculations and order manipulations
 * This is the business logic layer - no UI concerns here
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Core {

    /**
     * Available tax rates
     */
    private $tax_rates = array(
        15 => array('label' => '15%', 'decimal' => 0.15),
        25 => array('label' => '25%', 'decimal' => 0.25),
    );

    /**
     * Settings reference
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load settings if available
        if (class_exists('WCFST_Settings')) {
            $this->settings = WCFST_Settings::get_settings();
        }
    }
    
    /**
     * Calculate shipping tax fix for a given rate
     * 
     * @param WC_Order $order Order object
     * @param int $tax_rate Tax rate percentage (15 or 25)
     * @return array Calculation results for each shipping item
     */
    public function calculate_shipping_tax_fix($order, $tax_rate) {
        if (!$order instanceof WC_Order) {
            $this->log('Invalid order object provided to calculate_shipping_tax_fix');
            return array();
        }
        
        if (!isset($this->tax_rates[$tax_rate])) {
            $this->log("Invalid tax rate: {$tax_rate}%");
            return array();
        }
        
        $result = array();
        $tax_decimal = $this->tax_rates[$tax_rate]['decimal'];
        $tax_multiplier = 1 + $tax_decimal;
        
        foreach ($order->get_items('shipping') as $item_id => $item) {
            // Get current values
            $current_base = $item->get_total();
            $current_tax = $item->get_total_tax();
            $total_incl = $current_base + $current_tax;
            
            // Skip if no total
            if ($total_incl <= 0) {
                continue;
            }
            
            // Calculate new values
            $new_base = round($total_incl / $tax_multiplier, 2);
            $new_vat = round($total_incl - $new_base, 2);
            
            // Check if update is needed
            $needs_update = (abs($new_base - $current_base) > 0.01) || (abs($new_vat - $current_tax) > 0.01);
            
            $result[$item_id] = array(
                'shipping_method' => $item->get_method_title(),
                'current_base' => $current_base,
                'current_vat' => $current_tax,
                'current_total' => $total_incl,
                'new_base' => $new_base,
                'new_vat' => $new_vat,
                'new_total' => $new_base + $new_vat,
                'needs_update' => $needs_update,
                'totals_match' => abs(($new_base + $new_vat) - $total_incl) < 0.01,
            );
        }
        
        return $result;
    }
    
    /**
     * Apply shipping tax fix to an order
     * 
     * @param WC_Order $order Order object
     * @param int $tax_rate Tax rate percentage (15 or 25)
     * @return array Results array with 'success' and 'message' keys
     */
    public function apply_shipping_tax_fix($order, $tax_rate) {
        if (!$order instanceof WC_Order) {
            $this->log('Invalid order object provided to apply_shipping_tax_fix');
            return array(
                'success' => false,
                'message' => __('Invalid order object', 'wc-fix-shipping-tax'),
            );
        }
        
        // Get calculations
        $calculations = $this->calculate_shipping_tax_fix($order, $tax_rate);
        
        if (empty($calculations)) {
            $this->log('No shipping items found for tax fix');
            return array(
                'success' => false,
                'message' => __('No shipping items found', 'wc-fix-shipping-tax'),
            );
        }
        
        // Store original total
        $original_total = $order->get_total();
        $this->log("Original order total: " . wc_price($original_total));
        
        // Apply fixes
        $changes_made = false;
        
        foreach ($calculations as $item_id => $calc) {
            if (!$calc['needs_update']) {
                continue;
            }
            
            $shipping_item = $order->get_item($item_id);
            if (!$shipping_item) {
                $this->log("Shipping item {$item_id} not found");
                continue;
            }
            
            // Apply the new base amount
            $shipping_item->set_total($calc['new_base']);
            
            // Get tax rate ID
            $tax_rate_id = $this->get_tax_rate_id($order, $tax_rate);
            
            if ($tax_rate_id) {
                // Set new tax amounts
                $taxes = array(
                    'total' => array($tax_rate_id => $calc['new_vat']),
                    'subtotal' => array($tax_rate_id => $calc['new_vat']),
                );
                
                $shipping_item->set_taxes($taxes);
                $shipping_item->save();
                $changes_made = true;
                
                $this->log(sprintf(
                    'Applied shipping tax fix: %s - Base: %s → %s, VAT: %s → %s',
                    $calc['shipping_method'],
                    wc_price($calc['current_base']),
                    wc_price($calc['new_base']),
                    wc_price($calc['current_vat']),
                    wc_price($calc['new_vat'])
                ));
            } else {
                $this->log("Could not find tax rate ID for {$tax_rate}% rate");
                return array(
                    'success' => false,
                    'message' => sprintf(__('Could not find tax rate ID for %d%% rate', 'wc-fix-shipping-tax'), $tax_rate),
                );
            }
        }
        
        if ($changes_made) {
            // Update tax line items
            $this->update_order_tax_items($order);
            
            // Restore original total - CRITICAL
            $order->set_total($original_total);
            $order->save();
            
            $this->log("Shipping tax fix applied successfully, total preserved: " . wc_price($original_total));
            
            return array(
                'success' => true,
                'message' => sprintf(__('Shipping tax fix applied successfully (%d%%)', 'wc-fix-shipping-tax'), $tax_rate),
            );
        }
        
        $this->log('No changes were needed for shipping tax fix');
        return array(
            'success' => false,
            'message' => __('No changes were needed', 'wc-fix-shipping-tax'),
        );
    }
    
    /**
     * Update order tax line items
     * 
     * @param WC_Order $order
     */
    private function update_order_tax_items($order) {
        // Collect all taxes
        $tax_totals = array();
        
        // Product taxes
        foreach ($order->get_items('line_item') as $item) {
            $taxes = $item->get_taxes();
            if (!empty($taxes['total'])) {
                foreach ($taxes['total'] as $tax_id => $amount) {
                    if (!isset($tax_totals[$tax_id])) {
                        $tax_totals[$tax_id] = array(
                            'tax_total' => 0,
                            'shipping_tax_total' => 0,
                        );
                    }
                    $tax_totals[$tax_id]['tax_total'] += (float) $amount;
                }
            }
        }
        
        // Shipping taxes
        foreach ($order->get_items('shipping') as $item) {
            $taxes = $item->get_taxes();
            if (!empty($taxes['total'])) {
                foreach ($taxes['total'] as $tax_id => $amount) {
                    if (!isset($tax_totals[$tax_id])) {
                        $tax_totals[$tax_id] = array(
                            'tax_total' => 0,
                            'shipping_tax_total' => 0,
                        );
                    }
                    $tax_totals[$tax_id]['shipping_tax_total'] += (float) $amount;
                }
            }
        }
        
        // Fee taxes
        foreach ($order->get_items('fee') as $item) {
            $taxes = $item->get_taxes();
            if (!empty($taxes['total'])) {
                foreach ($taxes['total'] as $tax_id => $amount) {
                    if (!isset($tax_totals[$tax_id])) {
                        $tax_totals[$tax_id] = array(
                            'tax_total' => 0,
                            'shipping_tax_total' => 0,
                        );
                    }
                    $tax_totals[$tax_id]['tax_total'] += (float) $amount;
                }
            }
        }
        
        // Remove existing tax items
        foreach ($order->get_items('tax') as $item_id => $item) {
            $order->remove_item($item_id);
        }
        
        // Create new tax items
        foreach ($tax_totals as $tax_id => $totals) {
            $item = new WC_Order_Item_Tax();
            $item->set_rate($tax_id);
            $item->set_tax_total($totals['tax_total']);
            $item->set_shipping_tax_total($totals['shipping_tax_total']);
            $order->add_item($item);
        }
    }
    
    /**
     * Get tax rate ID for a given percentage
     * 
     * @param WC_Order $order
     * @param int $tax_rate_percent
     * @return int|false
     */
    private function get_tax_rate_id($order, $tax_rate_percent) {
        // First try to get from existing order items
        foreach ($order->get_items() as $item) {
            $taxes = $item->get_taxes();
            if (!empty($taxes['total']) && is_array($taxes['total'])) {
                $tax_ids = array_keys($taxes['total']);
                if (!empty($tax_ids)) {
                    // Verify this is the right rate
                    $tax_id = reset($tax_ids);
                    $rate = $this->get_tax_rate_by_id($tax_id);
                    if ($rate && abs($rate - $tax_rate_percent) < 1) {
                        return $tax_id;
                    }
                }
            }
        }
        
        // Try database lookup
        global $wpdb;
        $tax_id = $wpdb->get_var($wpdb->prepare(
            "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates 
             WHERE tax_rate = %s LIMIT 1",
            $tax_rate_percent . '.0000'
        ));
        
        return $tax_id ? (int) $tax_id : false;
    }
    
    /**
     * Get tax rate percentage by ID
     * 
     * @param int $tax_id
     * @return float|false
     */
    private function get_tax_rate_by_id($tax_id) {
        global $wpdb;
        $rate = $wpdb->get_var($wpdb->prepare(
            "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates 
             WHERE tax_rate_id = %d LIMIT 1",
            $tax_id
        ));
        
        return $rate ? (float) $rate : false;
    }
    
    /**
     * Get current shipping tax rate for an order
     * 
     * @param WC_Order $order
     * @return int Tax rate percentage
     */
    public function get_order_shipping_tax_rate($order) {
        foreach ($order->get_items('shipping') as $item) {
            $base = $item->get_total();
            $tax = $item->get_total_tax();
            
            if ($base > 0 && $tax > 0) {
                return round(($tax / $base) * 100);
            }
        }
        
        return 0;
    }
    
    /**
     * Get available tax rates
     * 
     * @return array
     */
    public function get_available_tax_rates() {
        return $this->tax_rates;
    }
    
    /**
     * Log a message
     * 
     * @param string $message
     */
    private function log($message) {
        if (isset($this->settings) && isset($this->settings['enable_logging']) && $this->settings['enable_logging']) {
            $logger = wc_get_logger();
            $logger->info($message, array('source' => 'wcfst'));
        }
    }
}
        $changes_made = false;
        
        foreach ($calculations as $item_id => $calc) {
            if (!$calc['needs_update']) {
                continue;
            }
            
            $shipping_item = $order->get_item($item_id);
            if (!$shipping_item) {
                continue;
            }
            
            // Apply the new base amount
            $shipping_item->set_total($calc['new_base']);
            
            // Get tax rate ID
            $tax_rate_id = $this->get_tax_rate_id($order, $tax_rate);
            
            if ($tax_rate_id) {
                // Set new tax amounts
                $taxes = array(
                    'total' => array($tax_rate_id => $calc['new_vat']),
                    'subtotal' => array($tax_rate_id => $calc['new_vat']),
                );
                
                $shipping_item->set_taxes($taxes);
                $shipping_item->save();
                $changes_made = true;
                
                // Add order note
                $order->add_order_note(sprintf(
                    __('Shipping tax fix applied to %s: Base %s → %s, VAT %s → %s (%d%% rate)', 'wc-fix-shipping-tax'),
                    $calc['shipping_method'],
                    wc_price($calc['current_base']),
                    wc_price($calc['new_base']),
                    wc_price($calc['current_vat']),
                    wc_price($calc['new_vat']),
                    $tax_rate
                ));
            } else {
                return array(
                    'success' => false,
                    'message' => sprintf(__('Could not find tax rate ID for %d%% rate', 'wc-fix-shipping-tax'), $tax_rate),
                );
            }
        }
        
        if ($changes_made) {
            // Update tax line items
            $this->update_order_tax_items($order);
            
            // Restore original total - CRITICAL
            $order->set_total($original_total);
            $order->save();
            
            return array(
                'success' => true,
                'message' => sprintf(__('Shipping tax fix applied successfully (%d%%)', 'wc-fix-shipping-tax'), $tax_rate),
            );
        }
        
        return array(
            'success' => false,
            'message' => __('No changes were needed', 'wc-fix-shipping-tax'),
        );
    }
    
    /**
     * Update order tax line items
     * 
     * @param WC_Order $order
     */
    private function update_order_tax_items($order) {
        // Collect all taxes
        $tax_totals = array();
        
        // Product taxes
        foreach ($order->get_items('line_item') as $item) {
            $taxes = $item->get_taxes();
            if (!empty($taxes['total'])) {
                foreach ($taxes['total'] as $tax_id => $amount) {
                    if (!isset($tax_totals[$tax_id])) {
                        $tax_totals[$tax_id] = array(
                            'tax_total' => 0,
                            'shipping_tax_total' => 0,
                        );
                    }
                    $tax_totals[$tax_id]['tax_total'] += (float) $amount;
                }
            }
        }
        
        // Shipping taxes
        foreach ($order->get_items('shipping') as $item) {
            $taxes = $item->get_taxes();
            if (!empty($taxes['total'])) {
                foreach ($taxes['total'] as $tax_id => $amount) {
                    if (!isset($tax_totals[$tax_id])) {
                        $tax_totals[$tax_id] = array(
                            'tax_total' => 0,
                            'shipping_tax_total' => 0,
                        );
                    }
                    $tax_totals[$tax_id]['shipping_tax_total'] += (float) $amount;
                }
            }
        }
        
        // Fee taxes
        foreach ($order->get_items('fee') as $item) {
            $taxes = $item->get_taxes();
            if (!empty($taxes['total'])) {
                foreach ($taxes['total'] as $tax_id => $amount) {
                    if (!isset($tax_totals[$tax_id])) {
                        $tax_totals[$tax_id] = array(
                            'tax_total' => 0,
                            'shipping_tax_total' => 0,
                        );
                    }
                    $tax_totals[$tax_id]['tax_total'] += (float) $amount;
                }
            }
        }
        
        // Remove existing tax items
        foreach ($order->get_items('tax') as $item_id => $item) {
            $order->remove_item($item_id);
        }
        
        // Create new tax items
        foreach ($tax_totals as $tax_id => $totals) {
            $item = new WC_Order_Item_Tax();
            $item->set_rate($tax_id);
            $item->set_tax_total($totals['tax_total']);
            $item->set_shipping_tax_total($totals['shipping_tax_total']);
            $order->add_item($item);
        }
    }
    
    /**
     * Get tax rate ID for a given percentage
     * 
     * @param WC_Order $order
     * @param int $tax_rate_percent
     * @return int|false
     */
    private function get_tax_rate_id($order, $tax_rate_percent) {
        // First try to get from existing order items
        foreach ($order->get_items() as $item) {
            $taxes = $item->get_taxes();
            if (!empty($taxes['total']) && is_array($taxes['total'])) {
                $tax_ids = array_keys($taxes['total']);
                if (!empty($tax_ids)) {
                    // Verify this is the right rate
                    $tax_id = reset($tax_ids);
                    $rate = $this->get_tax_rate_by_id($tax_id);
                    if ($rate && abs($rate - $tax_rate_percent) < 1) {
                        return $tax_id;
                    }
                }
            }
        }
        
        // Try database lookup
        global $wpdb;
        $tax_id = $wpdb->get_var($wpdb->prepare(
            "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates 
             WHERE tax_rate = %s LIMIT 1",
            $tax_rate_percent . '.0000'
        ));
        
        return $tax_id ? (int) $tax_id : false;
    }
    
    /**
     * Get tax rate percentage by ID
     * 
     * @param int $tax_id
     * @return float|false
     */
    private function get_tax_rate_by_id($tax_id) {
        global $wpdb;
        $rate = $wpdb->get_var($wpdb->prepare(
            "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates 
             WHERE tax_rate_id = %d LIMIT 1",
            $tax_id
        ));
        
        return $rate ? (float) $rate : false;
    }
    
    /**
     * Get current shipping tax rate for an order
     * 
     * @param WC_Order $order
     * @return int Tax rate percentage
     */
    public function get_order_shipping_tax_rate($order) {
        foreach ($order->get_items('shipping') as $item) {
            $base = $item->get_total();
            $tax = $item->get_total_tax();
            
            if ($base > 0 && $tax > 0) {
                return round(($tax / $base) * 100);
            }
        }
        
        return 0;
    }
    
    /**
     * Get available tax rates
     * 
     * @return array
     */
    public function get_available_tax_rates() {
        return $this->tax_rates;
    }
}
