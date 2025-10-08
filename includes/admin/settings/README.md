# Settings Architecture

This directory contains the modular settings classes for the WooCommerce Fix Shipping Tax plugin.

## Structure

The settings are organized into separate, focused classes:

### Base Settings (`class-wcfst-settings-base.php`)
- Coordinates all settings sections
- Handles general plugin settings (order list column, debug logging, etc.)
- Manages script enqueuing
- Provides backward-compatible `get_settings()` method

### Precision Settings (`class-wcfst-settings-precision.php`)
- Manages decimal precision for **backend display only**
- Applies WooCommerce internal rounding precision override
- **Does NOT affect order item prices saved to database**
- Renders precision status information

### Fiken Integration Settings (`class-wcfst-settings-fiken.php`)
- Handles order item price rounding configuration
- **Controls actual order item prices saved to database**
- Independent from backend display precision
- Fiken requires 2 decimal precision

### Order Editing Settings (`class-wcfst-settings-order-editing.php`)
- Controls whether completed orders can be edited
- **Disabled by default** for safety
- Enables editing of orders regardless of status when activated

### Tools Settings (`class-wcfst-settings-tools.php`)
- Manages batch processing tools
- Handles date range picker for order processing
- Processes AJAX actions for tools

## Key Distinctions

### Backend Display Precision vs Order Item Precision

**Backend Display Precision** (`class-wcfst-settings-precision.php`):
- Affects how WooCommerce displays calculations in admin area
- Uses `woocommerce_internal_rounding_precision` filter
- Does NOT change order item prices in database
- Optional feature for visual consistency

**Order Item Precision** (`class-wcfst-settings-fiken.php`):
- Affects actual order item prices saved during checkout
- Uses `woocommerce_checkout_create_order_line_item` hook
- Changes database values permanently
- Required for Fiken integration

These are **separate, independent settings** by design.

## Adding New Settings

To add a new settings section:

1. Create a new class file: `class-wcfst-settings-{name}.php`
2. Implement a `get_settings()` method that returns WooCommerce settings array
3. Include the file in `woocommerce-fix-shipping-tax.php`
4. Instantiate and merge settings in `class-wcfst-settings-base.php`

Example:

```php
// In class-wcfst-settings-base.php
public function add_tax_settings($settings, $current_section) {
    if ('wcfst' === $current_section) {
        $wcfst_settings = array();
        
        // Add your new settings section
        $new_settings = new WCFST_Settings_NewSection();
        $wcfst_settings = array_merge($wcfst_settings, $new_settings->get_settings());
        
        return $wcfst_settings;
    }
    return $settings;
}
```

## Backward Compatibility

The original `includes/admin/class-wcfst-settings.php` now extends `WCFST_Settings_Base` for backward compatibility. All existing code that references `WCFST_Settings` will continue to work.
