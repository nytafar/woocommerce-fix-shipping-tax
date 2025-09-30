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

        // Add hook for background processing
        add_action('wcfst_process_orders_batch', array($this, 'process_orders_batch'), 10, 1);
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

        $item_calculations = array();
        $tax_decimal = $this->tax_rates[$tax_rate]['decimal'];
        $tax_multiplier = 1 + $tax_decimal;

        foreach ($order->get_items('shipping') as $item_id => $item) {
            $current_base = $item->get_total();
            $current_total_tax = $item->get_total_tax();
            $total_incl = $current_base + $current_total_tax;

            if ($total_incl <= 0) continue;

            $new_base = round($total_incl / $tax_multiplier, 2);
            $new_vat = round($total_incl - $new_base, 2);
            $needs_update = (abs($new_base - $current_base) > 0.01) || (abs($new_vat - $current_total_tax) > 0.01);

            $item_calculations[$item_id] = array(
                'shipping_method' => $item->get_method_title(),
                'current_base' => $current_base,
                'current_vat' => $current_total_tax,
                'current_total' => $total_incl,
                'new_base' => $new_base,
                'new_vat' => $new_vat,
                'new_total' => $new_base + $new_vat,
                'needs_update' => $needs_update,
                'totals_match' => abs(($new_base + $new_vat) - $total_incl) < 0.01,
            );
        }

        if (empty($item_calculations)) {
            return array();
        }

        // --- Preview Calculation ---
        $preview = array(
            'before' => array('shipping' => $order->get_shipping_total(), 'taxes' => array()),
            'after' => array('shipping' => 0, 'taxes' => array()),
        );

        // Get current tax totals
        foreach ($order->get_taxes() as $tax) {
            $rate_id = $tax->get_rate_id();
            $preview['before']['taxes'][$rate_id] = array(
                'label' => $tax->get_label(),
                'total' => $tax->get_tax_total() + $tax->get_shipping_tax_total(),
            );
            // Initialize after state
            $preview['after']['taxes'][$rate_id] = array(
                'label' => $tax->get_label(),
                'total' => $tax->get_tax_total(), // Start with non-shipping tax
            );
        }

        // Calculate new shipping total and new shipping taxes
        $new_shipping_total = 0;
        $new_shipping_tax_total = 0;
        $new_target_rate_id = $this->get_tax_rate_id($order, $tax_rate);

        foreach ($item_calculations as $calc) {
            $new_shipping_total += $calc['new_base'];
            $new_shipping_tax_total += $calc['new_vat'];
        }
        $preview['after']['shipping'] = $new_shipping_total;

        // Add the new shipping tax to the correct tax rate in the 'after' preview
        if (isset($preview['after']['taxes'][$new_target_rate_id])) {
            $preview['after']['taxes'][$new_target_rate_id]['total'] += $new_shipping_tax_total;
        } else { // The target tax rate might not exist on the order yet
            $preview['after']['taxes'][$new_target_rate_id] = array(
                'label' => WC_Tax::get_rate_label($new_target_rate_id),
                'total' => $new_shipping_tax_total,
            );
        }

        return array(
            'items' => $item_calculations,
            'preview' => $preview,
        );
    }
    
    public function apply_shipping_tax_fix($order, $tax_rate) {
        if (!$order instanceof WC_Order) {
            $this->log('Invalid order object provided to apply_shipping_tax_fix');
            return array('success' => false, 'message' => __('Invalid order object', 'wc-fix-shipping-tax'));
        }

        $calculation_data = $this->calculate_shipping_tax_fix($order, $tax_rate);
        if (empty($calculation_data) || empty($calculation_data['items'])) {
            $this->log('No shipping items found for tax fix');
            return array('success' => false, 'message' => __('No shipping items found', 'wc-fix-shipping-tax'));
        }

        $calculations = $calculation_data['items'];
        $original_total = $order->get_total();
        $this->log("Original order total: " . wc_price($original_total));

        $changes_made = false;
        foreach ($calculations as $item_id => $calc) {
            if (!$calc['needs_update']) continue;

            $shipping_item = $order->get_item($item_id);
            if (!$shipping_item) continue;

            $shipping_item->set_total($calc['new_base']);
            $tax_rate_id = $this->get_tax_rate_id($order, $tax_rate);

            if ($tax_rate_id) {
                $shipping_item->set_taxes(array('total' => array($tax_rate_id => $calc['new_vat'])));
                $shipping_item->save();
                $changes_made = true;
            } else {
                $this->log("Could not find tax rate ID for {$tax_rate}% rate");
                return array('success' => false, 'message' => sprintf(__('Could not find tax rate ID for %d%% rate', 'wc-fix-shipping-tax'), $tax_rate));
            }
        }

        if ($changes_made) {
            // Force a refresh of the order object to get the latest item data
            $order = wc_get_order($order->get_id());

            // Manually recalculate all totals
            $shipping_total = 0;
            $shipping_tax_total = 0;
            $order_tax_total = 0;
            $tax_rate_breakdown = array();

            // Initialize breakdown with all possible tax rates in the order to handle removals
            foreach ($order->get_taxes() as $tax) {
                $tax_rate_breakdown[$tax->get_rate_id()] = ['cart_tax' => 0, 'shipping_tax' => 0];
            }

            foreach ($order->get_items(['line_item', 'fee', 'shipping']) as $item) {
                $item_taxes = $item->get_taxes()['total'];
                foreach ($item_taxes as $rate_id => $tax) {
                    if (!isset($tax_rate_breakdown[$rate_id])) {
                        $tax_rate_breakdown[$rate_id] = ['cart_tax' => 0, 'shipping_tax' => 0];
                    }
                    if ($item->is_type('shipping')) {
                        $tax_rate_breakdown[$rate_id]['shipping_tax'] += $tax;
                    } else {
                        $tax_rate_breakdown[$rate_id]['cart_tax'] += $tax;
                    }
                }
                if ($item->is_type('shipping')) {
                    $shipping_total += $item->get_total();
                    $shipping_tax_total += $item->get_total_tax();
                }
            }

            foreach ($tax_rate_breakdown as $totals) {
                $order_tax_total += $totals['cart_tax'] + $totals['shipping_tax'];
            }

            // Update tax summary items
            $order->remove_order_items('tax');
            foreach ($tax_rate_breakdown as $rate_id => $totals) {
                if ($totals['cart_tax'] + $totals['shipping_tax'] > 0) {
                    $item = new WC_Order_Item_Tax();
                    $item->set_rate_id($rate_id);
                    $item->set_tax_total($totals['cart_tax']);
                    $item->set_shipping_tax_total($totals['shipping_tax']);
                    $item->set_label(WC_Tax::get_rate_label($rate_id));
                    $order->add_item($item);
                }
            }

            // Direct DB updates for order totals
            update_post_meta($order->get_id(), '_order_shipping', wc_format_decimal($shipping_total));
            update_post_meta($order->get_id(), '_order_shipping_tax', wc_format_decimal($shipping_tax_total));
            update_post_meta($order->get_id(), '_order_tax', wc_format_decimal($order_tax_total));
            update_post_meta($order->get_id(), '_order_total', wc_format_decimal($original_total));

            // Clear caches
            wp_cache_delete($order->get_id(), 'post_meta');
            $order->read_meta_data(true);
            $order->save();

            // Update the meta field for the order list column
            $this->update_order_meta($order->get_id());

            $this->log("Shipping tax fix applied successfully, total preserved: " . wc_price($original_total));
            return array('success' => true, 'message' => sprintf(__('Shipping tax fix applied successfully (%d%%)', 'wc-fix-shipping-tax'), $tax_rate));
        }

        $this->log('No changes were needed for shipping tax fix');
        return array('success' => false, 'message' => __('No changes were needed', 'wc-fix-shipping-tax'));
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
             WHERE ROUND(tax_rate) = %d LIMIT 1",
            $tax_rate_percent
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
     * @return int Tax rate percentage, -1 for no shipping
     */
    public function get_order_shipping_tax_rate($order) {
        $shipping_items = $order->get_items('shipping');

        if (empty($shipping_items)) {
            return -1; // No shipping
        }

        $total_base = 0;
        $total_tax = 0;

        foreach ($shipping_items as $item) {
            $total_base += $item->get_total();
            $total_tax += $item->get_total_tax();
        }

        if ($total_base > 0) {
            return round(($total_tax / $total_base) * 100);
        }

        return 0; // Shipping with no cost
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
     * Schedule background meta update
     */
    public function schedule_meta_update() {
        // Get the 200 latest order IDs
        $query = new WC_Order_Query(array(
            'limit' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
        ));
        $order_ids = $query->get_orders();

        // Store them in a transient
        set_transient('wcfst_orders_to_process', $order_ids, DAY_IN_SECONDS);

        // Schedule the first batch if not already scheduled.
        if (function_exists('as_next_scheduled_action') && !as_next_scheduled_action('wcfst_process_orders_batch')) {
            as_schedule_single_action(time() + 10, 'wcfst_process_orders_batch', array('is_first' => true), 'wcfst');
        }
    }

    /**
     * Process a batch of orders to update meta
     */
    public function process_orders_batch($args = array()) {
        $is_first = isset($args['is_first']) && $args['is_first'];

        if ($is_first) {
            $this->log('Starting batch processing of the 200 latest orders.');
        }

        // Get orders from transient
        $order_ids = get_transient('wcfst_orders_to_process');

        if (empty($order_ids)) {
            $this->log('No more orders to process. Batch processing complete.');
            delete_transient('wcfst_orders_to_process');
            return;
        }

        // Get a batch of 50
        $batch_ids = array_splice($order_ids, 0, 50);

        $this->log('Found ' . count($batch_ids) . ' orders to process in this batch.');

        foreach ($batch_ids as $order_id) {
            $this->update_order_meta($order_id);
        }

        // Update the transient with the remaining IDs
        set_transient('wcfst_orders_to_process', $order_ids, DAY_IN_SECONDS);

        // Schedule the next batch if there are more orders
        if (!empty($order_ids)) {
            as_schedule_single_action(time() + 10, 'wcfst_process_orders_batch', array('is_first' => false), 'wcfst');
            $this->log('Finished a batch. Scheduled the next one.');
        }
    }

    /**
     * Update meta for a single order
     * 
     * @param int $order_id
     */
    public function update_order_meta($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $rate = $this->get_order_shipping_tax_rate($order);
            $this->log("Updating order {$order_id} with shipping tax rate: {$rate}");
            update_post_meta($order_id, '_wcfst_shipping_tax_rate', $rate);
        }
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