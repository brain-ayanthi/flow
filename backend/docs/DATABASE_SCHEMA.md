# PressFlow Laravel database schema — expanded print-shop scope

## Tenant and business boundary

A row in `tenants` is one business/shop. Each business can belong to many users, and a user can belong to many businesses through `tenant_user`; there is no hard-coded business-count limit. Every business-owned record in the new schema carries `tenant_id`. Queries and writes must use the authenticated tenant context—not a client-supplied tenant id. Inventory, purchases, sales, expenses, accounting and reports are therefore isolated by business.

## Business, locations and access

- `tenants`: currency, locale, timezone, financial-year start, business start date, default pricing margin, contact/tax-registration details and validated JSON preferences for the settings sections shown in the supplied reference screenshots.
- `locations`: storefront, branch or warehouse; location code, address, active state and defaults for invoice scheme/layout.
- `invoice_schemes`, `invoice_layouts`: per-location numbering sequences and printable invoice layout/options; final invoice records retain references and snapshots.
- `roles`, `permissions`, `role_permissions`, `tenant_user.role_id`: custom permission-bearing roles, with Admin and Cashier defaults provisioned for new businesses. The legacy `tenant_user.role` value remains for existing API compatibility during migration.
- `location_user`: optional per-user location access.

## Contacts and balances

The existing `customers` table remains the compatibility table for business contacts and now supports `is_customer`, `is_supplier`, or both. It stores payment terms, credit limit, opening debit/credit, tax number, billing/shipping address, and the per-business Walk-In Customer flag. `contact_ledger_entries` records debit/credit history, source reference, due date and reminder status; customer and supplier balances should be derived from opening balance plus ledger entries, not overwritten counters. Invoice and purchase due dates have reminder timestamps so a daily scheduled job can queue alerts a week before due.

## Jobcards and production

`print_orders` remains the tenant-owned jobcard/order header; `print_order_items` stores priced item lines and specifications, and `print_order_events` stores the initial `new` event plus each validated production-stage change. The additive migration `2026_10_12_000001_add_jobcard_snapshots_and_round_off.php` adds job date and customer-contact snapshots (`contact_name`, `contact_phone`, `whatsapp_phone`) plus `round_off` on orders and invoices. The additive migration `2026_10_13_000001_create_jobcard_masters_and_pricing.php` creates `jobcard_masters` and `jobcard_digital_rates` with tenant-scoped composite foreign keys; the digital matrix is unique per Particular + Media + Size + Side. `tenants.settings.jobcard` persists the default Flex unit and rate-override permission. Server pricing writes a snapshot to each item's specifications so later master changes do not rewrite saved lines.

## Products, tax and stock

- `brands`, hierarchical `categories`, `units`, `variation_templates`, `products`, `product_variations` support single and variable products, reusable attribute templates, SKU/barcode, cost, selling price, margin, tax group and low-stock settings.
- `number_sequences` supports safe, locked SKU/reference generation with tenant/location-specific prefixes.
- `product_location_stocks` stores on-hand/reserved quantities and location overrides. `stock_movements` is the append-only audit trail for purchase, sale, adjustment, transfer and production use.
- `stock_adjustments` + items and `stock_transfers` + items record counted quantities and location-to-location movements.
- `barcode_label_settings` stores reusable label sizes, sheet dimensions and printed fields.

### Tax is a first-class business page

Tax is not buried in general settings. The standalone **Tax Rates & Groups** page uses:

- `tax_rates`: business-scoped rate code/name, percentage or fixed calculation, compound flag, validity period and active state.
- `tax_groups` + `tax_group_rates`: ordered rate combinations, including tax-inclusive/exclusive treatment.
- `products.tax_group_id`: default product tax group; transaction lines may override it.
- `invoices`, invoice items, purchases, purchase items and expenses retain tax snapshots so editing a rate later never changes a finalized historical transaction.

The `POST /api/v1/tax/preview` calculator is intended for the UI preview. Finalization must recalculate on the server. Discounts are applied before tax; inclusive groups extract tax from the gross amount. Applicable tax registrations, tax types, and whether a particular rate is inclusive must be configured by the business before production. No tax rate is silently assumed or seeded.

## Purchasing, selling, expenses and accounting

- `purchases`, `purchase_items`, `purchase_payments`: location-specific supplier purchases, drafts/finalized state, paid/partial/due balance, tax/discount snapshots and due alerts.
- POS/direct sales reuse `invoices` with `sale_channel=pos`, `document_status=draft|final`, `location_id`, invoice scheme/layout, contact, item rows and tax snapshots. Jobcard invoices use `sale_channel=jobcard`. Payment status (`unpaid|partial|paid`) remains separate from document status.
- `payment_methods`: configurable cash/bank/card/cheque/other methods by business/location.
- `expenses`, `expense_categories`: expense posting and location/category reporting.
- `accounts`, `accounting_periods`, `journal_entries`, `journal_entry_lines`: business-scoped chart of accounts and balanced journal foundations. Posting services must enforce debit=credit and locked-period rules.

## Migration order

1. Existing core: tenant/auth, customers, jobs, invoices, subscriptions, payments.
2. Existing job extensions and quotes.
3. `2026_10_11_000002_create_business_locations_and_access_tables.php`
4. `2026_10_11_000003_create_tax_catalog_and_inventory_tables.php`
5. `2026_10_11_000004_create_purchases_expenses_and_accounting_tables.php`
6. `2026_10_11_000005_add_business_start_date_to_tenants.php`
7. `2026_10_12_000001_add_jobcard_snapshots_and_round_off.php`
8. `2026_10_13_000001_create_jobcard_masters_and_pricing.php`

Business defaults create a main storefront, invoice scheme/layout, basic payment methods, Walk-In Customer, Admin/Cashier roles and the permission catalog. Tax rates are deliberately left blank pending confirmation of the business's actual tax treatment.

## Implementation status

These migrations define the expanded schema. Customer Master, Jobcard Masters and Jobcard settings, Jobcard/Order create/list/detail, the Production board/stage changes, and dashboard job summaries are connected to the session-authenticated Laravel APIs in the PC app. Typed Jobcard item lines are priced server-side for Flex (feet/inches and fabrication), Digital/Paper matrix rates, and Other Items; master/rate operations are tenant-scoped and restricted to owners/managers. Feature tests cover representative pricing and tenant isolation. Invoice, receipt/payment and other module UI screens remain demo-only. The Tax Rates & Groups API/page and authenticated Laravel Settings page/API are implemented in source; the Settings page covers the supplied business/tax/product/contact/sale/POS/display/purchase/payment/dashboard/system/module/reward/prefix controls plus Jobcard defaults. Purchase, stock, POS finalization, automatic journal posting, reminders and location-management APIs still need business services, validation, permissions, tests and connected PC pages. PHP/Composer are not available in this workspace, so migrations and feature tests must be run in a real Laravel/XAMPP environment before production use.
