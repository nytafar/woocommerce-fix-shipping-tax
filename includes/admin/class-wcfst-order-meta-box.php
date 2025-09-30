<?php
/**
 * Order Meta Box class
 * 
 * Handles the UI for single order pages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WCFST_Order_Meta_Box {
    
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
        // Add meta box
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        
        // AJAX handlers
        add_action('wp_ajax_wcfst_apply_fix', array($this, 'ajax_apply_fix'));
    }
    
    /**
     * Add meta box to order page
     */
    public function add_meta_box() {
        add_meta_box(
            'wcfst-shipping-tax',
            __('Fix Shipping Tax', 'wc-fix-shipping-tax'),
            array($this, 'render_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }
    
    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            echo '<p>' . __('Order not found', 'wc-fix-shipping-tax') . '</p>';
            return;
        }
        
        // Get current shipping tax rate
        $current_rate = $this->core->get_order_shipping_tax_rate($order);
        
        // Get calculations for both rates
        $calc_15 = $this->core->calculate_shipping_tax_fix($order, 15);
        $calc_25 = $this->core->calculate_shipping_tax_fix($order, 25);
        
        if (empty($calc_15) && empty($calc_25)) {
            echo '<p>' . __('No shipping items found', 'wc-fix-shipping-tax') . '</p>';
            return;
        }
        
        ?>
        <div class="wcfst-meta-box">
            <p>
                <strong><?php _e('Current Shipping Tax Rate:', 'wc-fix-shipping-tax'); ?></strong> 
                <?php echo esc_html($current_rate); ?>%
            </p>
            
            <div class="wcfst-tabs">
                <ul class="wcfst-tab-nav">
                    <li class="active">
                        <a href="#wcfst-tab-15"><?php _e('15% Tax Rate', 'wc-fix-shipping-tax'); ?></a>
                    </li>
                    <li>
                        <a href="#wcfst-tab-25"><?php _e('25% Tax Rate', 'wc-fix-shipping-tax'); ?></a>
                    </li>
                </ul>
                
                <!-- 15% Tab -->
                <div id="wcfst-tab-15" class="wcfst-tab-content active">
                    <?php $this->render_tab_content($order, 15, $calc_15); ?>
                </div>
                
                <!-- 25% Tab -->
                <div id="wcfst-tab-25" class="wcfst-tab-content">
                    <?php $this->render_tab_content($order, 25, $calc_25); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tab content
     */
    private function render_tab_content($order, $tax_rate, $calculations) {
        if (empty($calculations) || empty($calculations['items'])) {
            echo '<p>' . __('No shipping items found or no changes needed.', 'wc-fix-shipping-tax') . '</p>';
            return;
        }

        $item_calculations = $calculations['items'];
        $preview = $calculations['preview'];
        $needs_update = false;
        foreach ($item_calculations as $calc) {
            if ($calc['needs_update']) {
                $needs_update = true;
                break;
            }
        }
        ?>
        
        <h4><?php _e('Shipping Item Changes', 'wc-fix-shipping-tax'); ?></h4>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Method', 'wc-fix-shipping-tax'); ?></th>
                    <th><?php _e('Current', 'wc-fix-shipping-tax'); ?></th>
                    <th><?php printf(__('Fixed (%d%%)', 'wc-fix-shipping-tax'), $tax_rate); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($item_calculations as $calc) : ?>
                    <tr>
                        <td><?php echo esc_html($calc['shipping_method']); ?></td>
                        <td>
                            <?php _e('Base:', 'wc-fix-shipping-tax'); ?> <?php echo wc_price($calc['current_base']); ?><br>
                            <?php _e('VAT:', 'wc-fix-shipping-tax'); ?> <?php echo wc_price($calc['current_vat']); ?><br>
                            <strong><?php _e('Total:', 'wc-fix-shipping-tax'); ?> <?php echo wc_price($calc['current_total']); ?></strong>
                        </td>
                        <td>
                            <?php _e('Base:', 'wc-fix-shipping-tax'); ?> <?php echo wc_price($calc['new_base']); ?><br>
                            <?php _e('VAT:', 'wc-fix-shipping-tax'); ?> <?php echo wc_price($calc['new_vat']); ?><br>
                            <strong><?php _e('Total:', 'wc-fix-shipping-tax'); ?> <?php echo wc_price($calc['new_total']); ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($needs_update && !empty($preview)) : ?>
            <h4 style="margin-top: 15px;"><?php _e('Order Totals Preview', 'wc-fix-shipping-tax'); ?></h4>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Total', 'wc-fix-shipping-tax'); ?></th>
                        <th><?php _e('Current', 'wc-fix-shipping-tax'); ?></th>
                        <th><?php _e('After Fix', 'wc-fix-shipping-tax'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php _e('Shipping', 'wc-fix-shipping-tax'); ?></td>
                        <td><?php echo wc_price($preview['before']['shipping']); ?></td>
                        <td><?php echo wc_price($preview['after']['shipping']); ?></td>
                    </tr>
                    <?php foreach ($preview['before']['taxes'] as $rate_id => $tax) : ?>
                        <tr>
                            <td><?php echo esc_html($tax['label']); ?></td>
                            <td><?php echo wc_price($tax['total']); ?></td>
                            <td><?php echo wc_price($preview['after']['taxes'][$rate_id]['total'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="wcfst-actions">
                <button type="button" 
                        class="button button-primary wcfst-apply-fix" 
                        data-order-id="<?php echo esc_attr($order->get_id()); ?>" 
                        data-tax-rate="<?php echo esc_attr($tax_rate); ?>">
                    <?php printf(__('Apply %d%% Fix', 'wc-fix-shipping-tax'), $tax_rate); ?>
                </button>
                <span class="spinner"></span>
            </div>
        <?php else : ?>
            <p class="wcfst-no-changes">
                <?php printf(__('No changes needed. Shipping tax is already at %d%%.', 'wc-fix-shipping-tax'), $tax_rate); ?>
            </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * AJAX handler for applying fix
     */
    public function ajax_apply_fix() {
        // Check nonce
        if (!check_ajax_referer('wcfst-nonce', 'nonce', false)) {
            wp_send_json_error(__('Invalid nonce', 'wc-fix-shipping-tax'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(__('Insufficient permissions', 'wc-fix-shipping-tax'));
        }
        
        // Get parameters
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $tax_rate = isset($_POST['tax_rate']) ? absint($_POST['tax_rate']) : 0;
        
        if (!$order_id || !in_array($tax_rate, array(15, 25))) {
            wp_send_json_error(__('Invalid parameters', 'wc-fix-shipping-tax'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(__('Order not found', 'wc-fix-shipping-tax'));
        }
        
        // Apply fix
        $result = $this->core->apply_shipping_tax_fix($order, $tax_rate);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'redirect' => admin_url('post.php?post=' . $order_id . '&action=edit'),
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
}
