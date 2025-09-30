Plugin Overview
woocommerce-fix-shipping tax/ provides tooling for correcting WooCommerce shipping tax distribution while preserving order totals. The system targets Norway (15% and 25% VAT) but remains extensible.

Project Goals
Ensure tax integrity: Guarantee shipping VAT matches configured rates without altering order totals.
Auditability: Record adjustments via notes and logging when enabled (
WCFST_Core::apply_shipping_tax_fix()
 in 
includes/class-wcfst-core.php
).
Operational efficiency: Offer single-order previews/actions, order-list insights, and bulk actions for faster remediation.
Safe workflows: Preserve order editability and provide guardrails (permissions, nonces, optional backups).
Key Functionality
Single Order Fixing
includes/admin/class-wcfst-order-meta-box.php
 renders a tabbed box with before/after calculations and buttons to preview/apply 15% or 25% fixes using 
WCFST_Core::calculate_shipping_tax_fix()
 and 
WCFST_Core::apply_shipping_tax_fix()
.
Order Actions
includes/admin/class-wcfst-admin.php
 registers preview/apply actions. Actions validate capability (edit_shop_orders), refresh metadata, and leave order notes.
Bulk Operations
includes/admin/class-wcfst-bulk-actions.php
 adds bulk dropdown entries and processes selected orders sequentially, aggregating success/errors.
Order List Enhancements
includes/admin/class-wcfst-order-list.php
 adds the “Shipping Tax” column, sortable metadata (_wcfst_shipping_tax_rate), filter dropdown, and persistent meta updates (rates + _wcfst_has_shipping). A background pass (
maybe_update_all_orders()
) retrofits existing orders.
Core Logic
includes/class-wcfst-core.php
 handles calculations, tax line updates, metadata, logging (WooCommerce logger when enable_logging), and defensive checks.
Settings UI
includes/admin/class-wcfst-settings.php
 provides WooCommerce submenu for toggling debug logging and automatic order backup notes (auto_backup). Settings are loaded early and influence core behavior.
Assets
assets/css/admin.css
 and 
assets/js/admin.js
 style the admin UI (badges, tabs, notices) and enable JS interactions (tab switching, async preview).
Design Principles
Modular separation: Core logic (
WCFST_Core
) is UI-agnostic; admin components are thin wrappers around it.
State persistence: Shipping tax data cached in meta for quick list rendering and filtering.
Idempotence: Fix routines preserve totals, only adjust shipping lines, and skip when already compliant.
Safety first: Capability checks, nonce validation, optional order backups, and comprehensive logging.
Extendability: Tax rates stored in $tax_rates for easy future expansion; additional admin modules plug into the loader in 
woocommerce-fix-shipping-tax.php
.
Repository Layout
woocommerce-fix-shipping tax/
├── README.md                              # High-level documentation
├── woocommerce-fix-shipping-tax.php       # Bootstrapper & module loader
├── includes/
│   ├── class-wcfst-core.php               # Business logic
│   └── admin/
│       ├── class-wcfst-admin.php          # Hooks & order actions
│       ├── class-wcfst-order-meta-box.php # Single-order UI
│       ├── class-wcfst-order-list.php     # Column/filter integration
│       ├── class-wcfst-bulk-actions.php   # Bulk processing
│       └── class-wcfst-settings.php       # Settings page
└── assets/
    ├── css/admin.css                      # Admin styling
    └── js/admin.js                        # Admin scripting
Development Guidelines
Coding standards: Follow WordPress PHP standards; keep classes self-contained and tested via WP-CLI (wp eval/wp shell) where possible.
Testing: Exercise order flows on staging—single action, bulk job, filter checks (especially “No shipping tax”).
Logging: Use plugin settings to toggle logs. Guard against sensitive data in notes/logs.
Versioning: Commit meaningful history (initial commit already staged). Tag future releases with semantic versions.
Extending: To add new rates, modify WCFST_Core::$tax_rates, extend UI components, and ensure bulk/actions align.
Onboarding Checklist
Environment: Use GridPane symlink structure (/root/sites/...), ensure plugin symlink points at woocommerce-fix-shipping tax/.
Activation: Managed via WP-CLI (wp plugin activate "woocommerce-fix-shipping tax").
Review: Start with 
README.md
, then inspect 
WCFST_Core
 and admin classes.
Primary Focus: Keep zero-tax filtering accurate (
WCFST_Order_List::filter_orders()
), preserve totals, and maintain meta consistency.