=== WooCommerce Fix Shipping Tax ===
Contributors: lassejellum
Tags: woocommerce, tax, vat, norwegian, accounting
Requires at least: 5.6
Tested up to: 8.0
Stable tag: 3.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive tool for correcting WooCommerce shipping tax distribution on existing orders while preserving order totals.

**⚠️ NOT HPOS-COMPATIBLE — do not activate on a store using High-Performance Order Storage.**

The plugin is written for legacy post-based order storage only:

* `WCFST_Core` writes `_order_shipping`, `_order_shipping_tax`, `_order_tax` and `_order_total` with `update_post_meta()`, then calls `$order->save()`. On HPOS the totals live in `wc_orders` columns, so the direct writes are ignored and `save()` persists the old totals — the tax items get rewritten but the totals don't, leaving the order internally inconsistent.
* `_wcfst_shipping_tax_rate` / `_wcfst_last_fix_applied` are written with `update_post_meta()` and bypass order sync (on myrvann.no this left 253 orders out of sync until a re-migrate on 2026-09-25).
* The meta box, bulk action and order-list column hook the legacy `shop_order` / `edit-shop_order` screens and don't appear on HPOS (`woocommerce_page_wc-orders`).
* It declares no HPOS compatibility either way, so WooCommerce won't warn you.

To make it compatible: use `$order->set_shipping_total()` / `set_shipping_tax()` / `set_cart_tax()` / `set_total()` and `$order->update_meta_data()` + `save()`, hook the HPOS screens too, and add the `before_woocommerce_init` → `FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true)` declaration.

== Description ==

WooCommerce Fix Shipping Tax provides essential tooling for store owners, especially in regions with multiple VAT rates like Norway (15% and 25%). It allows you to correct the shipping tax on completed orders without altering the grand total, ensuring your accounting is accurate.

The plugin provides a full suite of tools to handle this, from a detailed preview on individual orders to bulk actions and a filterable order list column. All operations are designed to be safe, with detailed logging and order notes to provide a clear audit trail.

== Features ==

*   **Corrects Shipping Tax**: Recalculates and applies the correct 15% or 25% VAT to the shipping cost on any existing order.
*   **Preserves Order Grand Total**: The order's final price is never changed, ensuring consistency with payment gateway records.
*   **Detailed Previews**: Before applying a fix, you can see a detailed preview of the changes to the shipping item and the order totals.
*   **Single Order Fixing**: A meta box on the order edit screen allows for easy, one-click fixing.
*   **Bulk Actions**: Apply fixes to multiple orders at once from the WooCommerce order list.
*   **Order List Integration**: Adds a "Shipping Tax" column to the order list, showing the calculated rate for each order.
*   **Filter by Shipping Tax**: A new filter allows you to find orders with a specific shipping tax rate (15%, 25%, 0%, or none).
*   **Configurable Settings**: All features can be configured from the WooCommerce settings panel.
*   **Detailed Order Notes**: When a fix is applied, a comprehensive note is added to the order, detailing all the changes.
*   **Background Processing**: A tool is provided to process orders in the background to populate the data for the order list column.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/woocommerce-fix-shipping-tax` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Navigate to **WooCommerce > Settings > Tax > Shipping Tax Fix** to configure the plugin.

== Usage ==

### Enabling the Order List Column

By default, the "Shipping Tax" column is disabled for performance reasons. To enable it:
1.  Go to **WooCommerce > Settings > Tax > Shipping Tax Fix**.
2.  Check the "Enable Order List Column" checkbox and save.

### Populating the Shipping Tax Data

To make the order list column and filtering work for your existing orders, you need to run the background processing tool:
1.  Go to **WooCommerce > Settings > Tax > Shipping Tax Fix**.
2.  Under the "Tools" section, select a date range for the orders you want to process (or leave it blank to process all orders).
3.  Click "Start Processing". The process will run in the background.

### Fixing a Single Order

1.  Navigate to the edit screen for any WooCommerce order.
2.  On the right side, you will find the **Fix Shipping Tax** meta box.
3.  The box shows the current calculated shipping tax rate.
4.  You can switch between the "15% Tax Rate" and "25% Tax Rate" tabs to see a preview of the fix.
5.  The preview shows the changes to the shipping item and the main order totals.
6.  If you are happy with the preview, click the **Apply Fix** button.

### Fixing Orders in Bulk

1.  Go to the **WooCommerce > Orders** list page.
2.  Select the orders you want to fix using the checkboxes.
3.  From the "Bulk actions" dropdown, choose one of the "Apply Shipping Tax Fix" options.
4.  Click "Apply".

== Changelog ==

= 3.0.0 =
*   Complete rewrite of the plugin with a modern, modular architecture.
*   Moved settings to a subtab under WooCommerce > Settings > Tax.
*   Added a date range picker for the background processing tool.
*   Added a setting to enable/disable the order list column.
*   Improved the order note to be more detailed and have a dynamic title.
*   Fixed numerous bugs related to total calculations and data refreshing.

= 2.0.0 =
*   Initial release of the new version.