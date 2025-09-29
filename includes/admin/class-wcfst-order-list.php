<?php
/**
 * Order List class
 * 
 * Handles the order list page functionality
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Order_List {
    
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
        // Add column
        add_filter('manage_edit-shop_order_columns', array($this, 'add_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_column'), 10, 2);
        
        // Make column sortable
        add_filter('manage_edit-shop_order_sortable_columns', array($this, 'make_column_sortable'));
        add_filter('request', array($this, 'handle_column_sorting'));
        
        // Add filter dropdown
        add_action('restrict_manage_posts', array($this, 'add_filter_dropdown'));
        add_action('pre_get_posts', array($this, 'filter_orders'));
    }
    
    /**
     * Add shipping tax column
     */
    public function add_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            
            // Add after order status
            if ('order_status' === $key) {
                $new_columns['shipping_tax_rate'] = __('Shipping Tax', 'wc-fix-shipping-tax');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render column content
     */
    public function render_column($column, $post_id) {
        if ('shipping_tax_rate' !== $column) {
            return;
        }
        
        $order = wc_get_order($post_id);
        if (!$order) {
            echo '—';
            return;
        }
        
        $rate = $this->core->get_order_shipping_tax_rate($order);
        
        if ($rate > 0) {
            $class = '';
            if (abs($rate - 15) < 1) {
                $class = 'wcfst-rate-15';
            } elseif (abs($rate - 25) < 1) {
                $class = 'wcfst-rate-25';
            } else {
                $class = 'wcfst-rate-other';
            }
            
            printf(
                '<span class="wcfst-rate %s">%d%%</span>',
                esc_attr($class),
                $rate
            );
        } else {
            echo '<span class="wcfst-rate-none">—</span>';
        }
    }
    
    /**
     * Make column sortable
     */
    public function make_column_sortable($columns) {
        $columns['shipping_tax_rate'] = 'shipping_tax_rate';
        return $columns;
    }
    
    /**
     * Handle column sorting
     */
    public function handle_column_sorting($vars) {
        if (isset($vars['orderby']) && 'shipping_tax_rate' === $vars['orderby']) {
            $vars = array_merge($vars, array(
                'meta_key' => '_wcfst_shipping_tax_rate',
                'orderby' => 'meta_value_num',
            ));
        }
        
        return $vars;
    }
    
    /**
     * Add filter dropdown
     */
    public function add_filter_dropdown() {
        global $typenow;
        
        if ('shop_order' !== $typenow) {
            return;
        }
        
        $current = isset($_GET['wcfst_tax_filter']) ? $_GET['wcfst_tax_filter'] : '';
        
        ?>
        <select name="wcfst_tax_filter">
            <option value=""><?php _e('All shipping tax rates', 'wc-fix-shipping-tax'); ?></option>
            <option value="15" <?php selected('15', $current); ?>><?php _e('15% tax rate', 'wc-fix-shipping-tax'); ?></option>
            <option value="25" <?php selected('25', $current); ?>><?php _e('25% tax rate', 'wc-fix-shipping-tax'); ?></option>
            <option value="other" <?php selected('other', $current); ?>><?php _e('Other tax rates', 'wc-fix-shipping-tax'); ?></option>
            <option value="none" <?php selected('none', $current); ?>><?php _e('No shipping', 'wc-fix-shipping-tax'); ?></option>
        </select>
        <?php
    }
    
    /**
     * Filter orders by shipping tax rate
     */
    public function filter_orders($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ('shop_order' !== $query->get('post_type')) {
            return;
        }
        
        if (empty($_GET['wcfst_tax_filter'])) {
            return;
        }
        
        $filter = $_GET['wcfst_tax_filter'];
        
        // Store shipping tax rate in meta for filtering
        // This would need to be populated on order save
        $meta_query = array();
        
        switch ($filter) {
            case '15':
                $meta_query[] = array(
                    'key' => '_wcfst_shipping_tax_rate',
                    'value' => array(14, 16),
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN',
                );
                break;
                
            case '25':
                $meta_query[] = array(
                    'key' => '_wcfst_shipping_tax_rate',
                    'value' => array(24, 26),
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN',
                );
                break;
                
            case 'other':
                $meta_query[] = array(
                    'relation' => 'AND',
                    array(
                        'key' => '_wcfst_shipping_tax_rate',
                        'value' => 0,
                        'type' => 'NUMERIC',
                        'compare' => '>',
                    ),
                    array(
                        'key' => '_wcfst_shipping_tax_rate',
                        'value' => array(14, 16),
                        'type' => 'NUMERIC',
                        'compare' => 'NOT BETWEEN',
                    ),
                    array(
                        'key' => '_wcfst_shipping_tax_rate',
                        'value' => array(24, 26),
                        'type' => 'NUMERIC',
                        'compare' => 'NOT BETWEEN',
                    ),
                );
                break;
                
            case 'none':
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_wcfst_shipping_tax_rate',
                        'value' => 0,
                        'type' => 'NUMERIC',
                        'compare' => '=',
                    ),
                    array(
                        'key' => '_wcfst_shipping_tax_rate',
                        'compare' => 'NOT EXISTS',
                    ),
                );
                break;
        }
        
        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }
}
