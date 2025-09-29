# WooCommerce Fix Shipping Tax

A comprehensive WooCommerce plugin for fixing shipping tax calculations in existing orders. This plugin ensures proper VAT distribution for shipping costs while maintaining order totals and providing a clean, professional admin interface.

## 🎯 Features

### ✅ Core Functionality
- **Tax Rate Correction**: Fix shipping tax calculations to 15% or 25% VAT rates
- **Order Total Preservation**: Maintains original order totals while correcting tax distribution
- **Visual Comparison**: Before/after preview of tax calculations
- **Bulk Operations**: Apply fixes to multiple orders simultaneously

### ✅ Admin Interface
- **Order Meta Box**: Tabbed interface showing current vs. proposed tax calculations
- **Order Actions**: Preview and Apply buttons for 15% and 25% tax rates
- **Order List Column**: Display current shipping tax rates in the orders list
- **Filter Dropdown**: Filter orders by shipping tax rate
- **Bulk Actions**: Preview and apply fixes to multiple orders

### ✅ Order Management
- **Complete Order Editability**: All orders remain editable regardless of status
- **Security**: Proper capability checks and nonce verification
- **Error Handling**: Comprehensive error reporting and logging
- **Clean Architecture**: Modular, maintainable code structure

## 📋 Requirements

- **WordPress**: 5.6 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher

## 🚀 Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/woocommerce-fix-shipping-tax/`
3. Activate the plugin through the WordPress admin
4. Navigate to any order to see the "Fix Shipping Tax" meta box

## 📖 Usage

### Single Order Fix

1. Go to **WooCommerce > Orders**
2. Click on any order to edit it
3. Look for the **"Fix Shipping Tax"** meta box on the right side
4. Choose between **15%** or **25%** tax rate tabs
5. Review the **current vs. proposed** values
6. Click **"Apply [Rate]% Fix"** to apply the correction

### Bulk Operations

1. Go to **WooCommerce > Orders**
2. Select multiple orders using checkboxes
3. Choose **"Apply Shipping Tax Fix (15%)"** or **"Apply Shipping Tax Fix (25%)"** from Bulk Actions
4. Click **"Apply"** to process multiple orders

### Order List Filtering

1. Go to **WooCommerce > Orders**
2. Use the **"Shipping Tax Rate"** filter dropdown
3. Filter by **15%**, **25%**, **Other**, or **None**

## 🏗️ Architecture

### Modular Structure

```
woocommerce-fix-shipping-tax/
├── woocommerce-fix-shipping-tax.php     # Main plugin file
├── includes/
│   ├── class-wcfst-core.php             # Core business logic
│   └── admin/
│       ├── class-wcfst-admin.php        # Admin coordination
│       ├── class-wcfst-order-meta-box.php # Single order UI
│       ├── class-wcfst-order-list.php   # Order list enhancements
│       └── class-wcfst-bulk-actions.php # Bulk operations
└── assets/
    ├── css/
    │   └── admin.css                    # Admin styles
    └── js/
        └── admin.js                     # Admin scripts
```

### Key Classes

- **`WCFST_Core`**: Handles all tax calculations and order manipulations
- **`WCFST_Admin`**: Coordinates admin functionality and hooks
- **`WCFST_Order_Meta_Box`**: Manages single order UI
- **`WCFST_Order_List`**: Handles order list enhancements
- **`WCFST_Bulk_Actions`**: Manages bulk operations

## 🔧 Technical Details

### Tax Calculation Logic

The plugin uses the following approach:

1. **Calculate Target Values**: Determine correct base + VAT for target rate
2. **Preserve Order Total**: Store original total before making changes
3. **Apply Tax Fix**: Update shipping items with correct base and VAT
4. **Update Tax Items**: Recreate tax line items with proper distribution
5. **Restore Total**: Set order total back to original amount

### Database Impact

- **No order total changes**: Original totals are preserved
- **Tax line updates**: Product and shipping taxes properly separated
- **Meta data**: Optional storage of processing information
- **No data loss**: All operations are reversible

## 🛡️ Security

- **Capability Checks**: All actions require `edit_shop_orders` capability
- **Nonce Verification**: CSRF protection on all AJAX requests
- **Input Sanitization**: All user input properly validated
- **SQL Injection Prevention**: Prepared statements for database queries

## 🔍 Troubleshooting

### Common Issues

**Orders not editable after plugin activation:**
- Ensure the plugin is properly activated
- Check that user has `edit_shop_orders` capability
- Verify no other plugins are interfering with order editing

**Tax calculations showing incorrect values:**
- Verify WooCommerce tax rates are properly configured
- Check that the order has valid shipping items
- Review browser console for JavaScript errors

**Bulk actions not working:**
- Ensure user has proper permissions
- Check that orders are not locked by other processes
- Verify AJAX endpoints are accessible

### Debug Mode

Enable debug logging by adding to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📝 Changelog

### Version 2.0.0
- Complete rewrite with modular architecture
- Added comprehensive error handling
- Improved security with nonce verification
- Added order list filtering and columns
- Enhanced bulk operations
- Better user interface with tabbed design

### Version 1.1.0 (Legacy)
- Original implementation
- Basic tax fixing functionality
- Simple admin interface

## 🧪 Development

### Adding New Tax Rates

To add support for additional tax rates:

1. Update the `$tax_rates` array in `WCFST_Core`
2. Add corresponding UI elements in the meta box
3. Update order actions and bulk actions
4. Test thoroughly with various order types

### Extending Functionality

The modular architecture makes it easy to extend:

- **New calculation methods**: Add to `WCFST_Core`
- **Additional UI components**: Create new admin classes
- **Custom order actions**: Extend `WCFST_Admin`
- **New bulk operations**: Enhance `WCFST_Bulk_Actions`

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This plugin is released under the GPL v2 or later.

## 🆘 Support

For support and questions:
- Check the troubleshooting section above
- Review WooCommerce and WordPress logs
- Ensure all requirements are met
- Test with a minimal setup if issues persist

---

**Made with ❤️ for WooCommerce store owners**
