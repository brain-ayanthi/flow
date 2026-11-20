# PressFlow Laravel backend foundation

## Product architecture

The **PC web dashboard is the primary client**. The Flutter app is optional/mobile and will call the same Laravel API for the same jobs, production changes, invoices, payment records and subscription status. Laravel is the single source of truth for validation, tenant isolation, roles, subscription checks and business rules. The PC login uses Laravel session auth; Sanctum is configured for PC SPA/session API access and Flutter bearer tokens.

This directory is the reusable PressFlow source/reference overlay. The complete Laravel 12 source tree is included at `../laravel-app/`, with `artisan`, `composer.json`, `public/`, `config/`, routes, migrations and PressFlow code. `.env.example` is included; `.env` is intentionally excluded from the archive so an existing shop's credentials and `APP_KEY` are not overwritten. The setup script copies the example only when `.env` is missing and generates an app key only when the value is empty. Composer dependencies (`vendor/`) are not bundled; run the root `setup-xampp-windows.bat` or `composer install` there. PHP/Composer are not installed in this sandbox, so migrations and runtime have not been tested here.

## What has been started

- PC session sign-in; the authenticated `/dashboard` shell with tenant-scoped dashboard summaries and recent jobcards served by the shared API.
- API registration/login/logout, current user, workspace listing and workspace switching.
- Tenant tables and fail-closed tenant scope for shop-owned models; base legacy role checks plus permission-based checks for the new Tax module.
- Multiple business data model with locations/storefronts/warehouses, per-location invoice schemes/layouts, location access and default business provisioning.
- Customer/supplier/both contact fields, payment terms and ledger schema; products/variations, brands/categories/units, SKU/barcode settings and location stock/adjustment/transfer schema.
- Customer Master list/create/edit is wired to the session-authenticated contacts API. Jobcard create/list/search/detail and production stage updates use the shared orders API from the PC UI; item/contact snapshots, server-calculated totals, stage history and audit records persist in the tenant database. Jobcard Pricing Masters/settings are also connected: owners/managers maintain tenant-scoped Flex Media, Fabrication, Digital/Paper categories and matrix rates, and Other Items. The wizard uses those masters and Laravel recalculates pricing; private artwork upload/download remains API-only.
- Invoice creation from orders, itemized invoice snapshots, part-payment recording and audit events.
- Estimates/quotations with server-calculated item totals, validated status transitions, accepted-quote-to-job conversion and audit events.
- Dedicated Tax Rates & Groups API/page plus an authenticated `/settings` page/API; tenant-specific business/tax/product/contact/sale/POS/display/purchase/payment/dashboard/system/module/reward/prefix/Jobcard preferences persist in tenant settings, and no legal tax rate is assumed or seeded.
- Purchase/payment, POS invoice, expense/category, contact-ledger and double-entry accounting schema foundations, all tenant- and location-scoped.
- Dashboard metrics and recent jobs, with bounded pagination and tenant/filter indexes.
- Editable subscription-plan API, trial support, expiry write-gating, monthly order-limit enforcement and bank-transfer receipt review/approval.

## Important integration status

- **PayHere checkout and signed notifications are not connected.** A PayHere request returns an explicit `501 payment_gateway_not_configured` until merchant credentials and a verified server adapter are installed.
- The authenticated `/dashboard` renders the supplied index.html-style navigation with the real user/workspace and Laravel logout. Customer Master, Jobcard Pricing Masters/settings, Jobcards/Orders, and the Job Status production board read/write through the session-authenticated tenant APIs. Flex pricing converts feet/inches using billing dimensions and adds configurable fabrication charges; Digital/Paper rates match Particular + Media + Size + Side; Other Items use their default rate. Allowed rate overrides and totals are validated/recalculated server-side, with stage history and audit records persisted. The dashboard summary and recent-job list are live. Billing & Finalization now creates an invoice and optional advance-payment receipt atomically with the jobcard; receipt references are stable (e.g. `RCP-000123`) and deposit accounts are tenant-scoped. Standalone invoice/receipt/payment lists, quotes, delivery challans, inventory and accounting remain prototype-only in the PC UI. WhatsApp sending is not configured. The banner links to separate authenticated `/taxes` and `/settings` pages.
- Inventory, purchases, POS finalization, stock movements, expenses, accounting and due-alert tables are now defined, but their Laravel services/endpoints, automatic ledger/stock posting, reminders and UI workflows still need implementation.
- Custom role tables/default Admin and Cashier roles and permission checks are started; role-management UI, invitations, permission coverage for all modules and location-level authorization remain.
- Attendance/report APIs, tenant-custom production stages, limits beyond monthly orders, invoice PDF/email/SMS and a full automated regression suite remain to be built. Order/production, quote, tax and Jobcard pricing/tenant-isolation feature tests have been authored but not run in this sandbox.
- Sample plan prices in `SubscriptionPlanSeeder` are draft values; confirm or change them before launch.
- A platform administrator must be provisioned manually by a trusted operator; there is deliberately no public API that grants this privilege.

## Run the included Laravel app

Recommended local environment: XAMPP with a PHP version supported by Laravel 12, Composer, and MySQL. The complete Laravel source is in `../laravel-app/`; no `vendor/` dependencies are packed.

1. Install XAMPP and Composer; configure Composer to use XAMPP's `php.exe`.
2. From the project bundle root, run `setup-xampp-windows.bat`. It runs `composer install` in `laravel-app/`, creates `.env` from `.env.example` only if missing, and generates an app key only if the current value is empty. It preserves an existing `.env` and `APP_KEY`. For manual setup, copy `.env.example` to `.env` only when `.env` does not exist, then run Composer and generate a key if needed.
3. **New empty install only:** start MySQL in XAMPP and create the `pressflow` database through phpMyAdmin or `laravel-app/database/create_database.mysql.sql`. If you are updating a shop database, keep it as-is—do not drop, recreate or seed it.
4. Review `laravel-app/.env` on a new install and adjust DB credentials to match your own MySQL setup. The archive excludes `.env`; the setup script preserves an existing file and key. Never replace an existing file or share/commit real secrets.
5. From `laravel-app/`, run `php artisan config:clear`. For a **brand-new empty database**, run `php artisan migrate --seed` and then `php artisan migrate:status`. For an **existing shop database**, run only `C:\xampp\php\php.exe artisan migrate` to apply pending additive migrations; do not run `migrate:fresh`.
6. Run `php artisan storage:link` if serving public business logos. For Apache, set the VirtualHost DocumentRoot to `laravel-app/public` only; see `docs/XAMPP_SETUP_SI.md`.
7. Run `php artisan test` and verify migrations, tenant isolation, permissions, file handling and payment states before production.

For an existing XAMPP database after extracting this update, run `C:\xampp\php\php.exe artisan migrate` from `laravel-app/` to apply pending additive migrations, including Jobcard snapshots/round-off and the tenant-scoped Jobcard master/rate tables; it does not reset shop data. The archive contains Laravel source, not third-party `vendor/` packages or a migrated database. Those are installed/created on the XAMPP machine. This sandbox cannot run PHP/Composer/MySQL, so Laravel runtime and feature tests remain unverified.

## Key route reference

See `docs/API.md` for the API request examples, permission model and endpoint list, `docs/DATABASE_SCHEMA.md` for the expanded business/location/tax/inventory/accounting schema, `docs/XAMPP_SETUP_SI.md` for Sinhala XAMPP steps, and `docs/MYSQL_SETUP_SI.md` for generic MySQL/Laravel setup. `database/create_database.mysql.sql` creates the empty MySQL database only; the Laravel migrations create the actual tables. The sample web login is `/login`; the signed-in UI is `/dashboard`. The current live PC slice is Customer Master + Jobcards/Orders + Production + jobcard Billing & Finalization; standalone invoice/payment UI and other module screens still show prototype records. Authenticated `/taxes` and `/settings` are separate API-backed pages in source.
