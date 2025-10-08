# Fiken Integration - Order Item Price Rounding

## Overview

This feature eliminates rounding errors when transferring WooCommerce orders to Fiken or similar accounting systems by saving order item prices with consistent decimal precision.

## The Problem

When WooCommerce uses high-precision internal calculations (e.g., 6 decimals) but displays prices with 2 decimals, rounding discrepancies can occur. These small differences accumulate and cause issues when exporting orders to accounting systems like Fiken, which require exact 2-decimal precision.

## The Solution

The plugin now includes an **Order Item Price Rounding** feature that:

1. Rounds order item unit prices to a configurable decimal precision during checkout
2. Calculates order totals based on these rounded prices
3. Ensures consistency between displayed prices and stored values

## Configuration

### Settings Location
Navigate to: **WooCommerce → Settings → Tax → Shipping Tax Fix**

### Required Settings for Fiken

1. **Enable Order Item Price Rounding**: Check this box
2. **Decimal Precision**: Set to **2** (Fiken requirement)
3. **Override Rounding Precision**: Enable and set to **2**

The **Item Price Decimal Precision** field is automatically synchronized with the **Decimal Precision** setting to ensure consistency across all calculations.

## How It Works

When a customer completes checkout, the plugin:

1. Gets the unit price excluding tax for each product
2. Rounds it to the configured decimal precision (2 for Fiken)
3. Calculates the line item total: `rounded_unit_price × quantity`
4. Saves these values to the order

### Code Implementation

```php
// Hook: woocommerce_checkout_create_order_line_item
public function round_order_item_prices($item, $cart_item_key, $values, $order) {
    $precision = 2; // Configurable via settings
    $qty = $item->get_quantity();
    
    // Round unit price to 2 decimals
    $unit_price_excl_tax = round($values['data']->get_price_excluding_tax(), $precision);
    
    // Calculate totals based on rounded unit price
    $subtotal = $unit_price_excl_tax * $qty;
    
    // Set subtotal and total for item (excluding tax)
    $item->set_subtotal($subtotal);
    $item->set_total($subtotal);
}
```

## Trade-offs

### Benefit
✅ **Eliminates rounding errors** when exporting to Fiken and similar accounting systems

### Trade-off
⚠️ **Order totals may show prices like 1308,01** instead of round numbers like 1308,00

This happens because:
- Item prices are rounded to 2 decimals
- Taxes are calculated on these rounded prices
- The final total reflects these 2-decimal calculations

This is **expected behavior** and ensures accuracy in your accounting system.

## Technical Details

### Files Modified

1. **`includes/admin/class-wcfst-settings.php`**
   - Added new settings section: "Fiken Integration Settings"
   - Added checkbox: "Enable Order Item Price Rounding"
   - Added synchronized precision field
   - Added automatic sync on settings save

2. **`includes/class-wcfst-core.php`**
   - Added `init_item_rounding()` method
   - Added `round_order_item_prices()` method
   - Integrated with existing settings system

3. **`assets/js/admin-settings.js`**
   - Added JavaScript to sync precision values in real-time
   - Made item precision field read-only with visual indication

### Hooks Used

- `woocommerce_checkout_create_order_line_item` (priority 20)
- `woocommerce_update_options_tax_wcfst` (for settings sync)

## Testing

To verify the feature is working:

1. Enable the setting and set precision to 2
2. Create a test order with products that have prices like 99.99
3. Check the order details in WooCommerce admin
4. Verify that line item prices are stored with exactly 2 decimals
5. Export to Fiken and confirm no rounding errors

## Debugging

Enable debug logging in the plugin settings to see detailed information about price rounding:

```
Rounded order item price: Product Name (qty: 2, unit price: $99.99, precision: 2)
```

## Compatibility

- **WooCommerce**: 5.0+
- **PHP**: 7.4+
- **Fiken**: All versions
- **Other accounting systems**: Any system requiring 2-decimal precision

## Support

For issues or questions about this feature, check the plugin's debug logs or contact support.
