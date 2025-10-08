# Order Editing Feature

## Overview

The plugin includes functionality to enable editing of completed orders in WooCommerce. This feature is now **disabled by default** and can be enabled through the plugin settings.

## Purpose

This feature allows administrators to edit orders regardless of their status (including completed orders). This is particularly useful for:

- Applying shipping tax fixes to historical orders
- Correcting order data after completion
- Making adjustments for accounting purposes

## Configuration

### Settings Location
Navigate to: **WooCommerce → Settings → Tax → Shipping Tax Fix → Order Editing Settings**

### Setting Details

**Enable Editing of Completed Orders**
- **Type**: Checkbox
- **Default**: Disabled (unchecked)
- **Description**: Allow editing of orders regardless of their status (including completed orders)

**Important Note**: Use with caution as editing completed orders can affect your records and accounting.

## How It Works

When enabled, the plugin applies three filters to WooCommerce:

1. **`wc_order_is_editable`** (priority 999)
   - Makes all orders editable regardless of status
   - Returns `true` for all orders

2. **`woocommerce_admin_order_should_be_locked`** (priority 999)
   - Prevents orders from being locked
   - Returns `false` to disable locking

3. **`woocommerce_valid_order_statuses_for_payment`** (priority 999)
   - Expands valid order statuses
   - Includes all possible order statuses

## Technical Implementation

### Files Modified

**`includes/admin/settings/class-wcfst-settings-order-editing.php`** (New)
- Dedicated settings class for order editing configuration
- Follows the modular settings architecture

**`includes/admin/class-wcfst-admin.php`**
- Added settings reference
- Made order editing hooks conditional
- Only applies filters when setting is enabled

**`includes/admin/settings/class-wcfst-settings-base.php`**
- Added order editing settings to the settings array
- Included in `get_settings()` method

**`woocommerce-fix-shipping-tax.php`**
- Added require statement for new settings class

### Code Example

```php
// In class-wcfst-admin.php
private function init_hooks() {
    // ... other hooks ...
    
    // Make orders editable regardless of status (only if enabled in settings)
    if (!empty($this->settings['enable_order_editing'])) {
        add_filter('wc_order_is_editable', array($this, 'make_orders_editable'), 999, 2);
        add_filter('woocommerce_admin_order_should_be_locked', '__return_false', 999);
        add_filter('woocommerce_valid_order_statuses_for_payment', array($this, 'expand_valid_order_statuses'), 999, 2);
    }
}
```

## Safety Considerations

### Why Disabled by Default?

Editing completed orders can have several implications:

1. **Accounting Impact**: Changes to completed orders may affect financial reports
2. **Customer Communication**: Customers may have already received confirmation emails
3. **Inventory Management**: Stock levels may have already been adjusted
4. **Payment Processing**: Payment records may be out of sync with order data

### Best Practices

When using this feature:

1. **Enable Only When Needed**: Turn on the setting only when you need to edit completed orders
2. **Document Changes**: Use order notes to document any changes made
3. **Verify Impact**: Check that changes don't affect other systems (accounting, inventory, etc.)
4. **Disable After Use**: Consider disabling the feature after completing your edits

## Use Cases

### Applying Shipping Tax Fixes

The primary use case is applying shipping tax fixes to historical orders:

1. Enable order editing in settings
2. Navigate to a completed order
3. Use the "Apply Shipping Tax Fix" order action
4. The order remains editable, allowing the fix to be applied
5. Disable order editing when done

### Bulk Operations

Combined with the bulk actions feature:

1. Enable order editing
2. Select multiple completed orders
3. Apply bulk shipping tax fix
4. All selected orders can be edited and fixed
5. Disable order editing when complete

## Compatibility

- **WooCommerce**: 5.0+
- **PHP**: 7.4+
- **WordPress**: 5.6+

## Troubleshooting

### Orders Still Not Editable

If orders are still not editable after enabling the setting:

1. Clear WordPress cache
2. Verify the setting is saved (check database: `wcfst_enable_order_editing`)
3. Check for conflicting plugins that may override these filters
4. Enable debug logging to see if filters are being applied

### Unexpected Behavior

If you experience unexpected behavior:

1. Disable the setting immediately
2. Check order notes for any automated changes
3. Review WooCommerce logs for errors
4. Contact support if issues persist

## Migration Note

**Previous Behavior**: Order editing was always enabled by default.

**New Behavior**: Order editing is disabled by default and must be explicitly enabled.

If you were relying on this functionality, you'll need to enable it in the settings after updating the plugin.
