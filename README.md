# PressFlow — Printing press management starter

## Confirmed product direction

- PC web dashboard is the primary way to use the system; the mobile app is for when needed.
- Laravel is the single source of truth. The PC web client and the Flutter Android/iOS app share the same backend, data, permissions and business rules, so app users can do the same shop operations where appropriate.
- Multi-tenant SaaS: separate shop workspaces, roles, and subscription plans managed by a platform admin.
- PayHere is the intended online subscription payment gateway; manual bank-transfer review is also supported as an initial pilot flow.
- Sinhala, English and Tamil.

## Contents

- `preview/index.html` — interactive no-dependency design prototype, with sample data and navigation.
- `laravel-app/` — full Laravel 12 source tree with the PressFlow backend merged; run `composer install` to add dependencies (`vendor/` is not bundled).
- `backend/` — source/reference overlay, database setup scripts, API/schema docs and XAMPP instructions.
- `mobile/` — Flutter shell prepared for later API connection.
- `BUILD_PLAN_SI.md` — phased delivery plan and current readiness gaps.
- `docs/architecture.md`, `docs/ui-screen-map.md`, `backend/docs/API.md` and `backend/docs/DATABASE_SCHEMA.md` — architecture, screenshot-to-page map, endpoint reference and expanded database design.

## Backend status

`laravel-app/` contains the full Laravel 12 source tree, including the visible Laravel CLI entrypoint at `laravel-app/artisan`, `composer.json`, `public/`, configuration, migrations, PressFlow routes and views. The bundle includes `.env.example` but intentionally excludes `.env`; `setup-xampp-windows.bat` creates `.env` only when missing and preserves an existing `.env` and `APP_KEY`. Composer dependencies (`vendor/`) are not bundled. `backend/` remains the source/reference overlay and documentation. The authenticated `/dashboard` uses the `preview/index.html`-style navigation with the signed-in user/workspace and logout wired to Laravel. Customer Master, Jobcards/Orders, Production stage updates and the job-related dashboard summary now use the session-authenticated tenant APIs and persist to the workspace database. Jobcard Pricing Masters and Jobcard settings are tenant-scoped and connected to the wizard; Flex (feet/inches, chargeable billing dimensions and fabrication), Digital/Paper matrix rates, Other Items and permitted line-rate overrides are recalculated server-side. The Billing & Finalization step now creates the invoice atomically with a jobcard and records any advance against the selected tenant payment method, returning a stable receipt reference such as `RCP-000123`. Owners/managers maintain masters; no shop-specific prices are seeded. Standalone invoice/receipt/payment lists, quotes, delivery, inventory, accounting and other screens remain prototype data; only invoice and advance-payment creation inside Billing & Finalization is live. The visible WhatsApp option does not send until a provider is configured; the banner identifies the live slice and remaining preview modules. Separate authenticated `/taxes` and `/settings` pages/API remain available from the banner. Migrations define locations, roles/permissions, contacts, products, tax, stock, purchases, POS fields, expenses, accounting foundations and Jobcard Masters/rates. No live database exists in this sandbox.

**Not yet a finished production system:** Customer Master, Jobcards/Orders, Production and jobcard Billing & Finalization are database-backed; the latter atomically creates an invoice and an optional advance-payment/receipt record. Standalone invoice/receipt/payment screens and other prototype records/actions are not yet database-backed. Feature tests now cover auto-invoice plus advance receipt, Flex feet/inches and fabrication charges, Digital/Paper matrix and rate overrides, Other Item pricing, and tenant isolation, alongside the existing jobcard/production workflow. The sandbox has no PHP or Composer runtime, so Laravel tests and XAMPP runtime checks have not been executed here; the dashboard JavaScript syntax check passes. `/taxes` and `/settings` are wired in source to tenant APIs but should also be verified on XAMPP. Purchase/stock/POS/expense/accounting services, location and role-management APIs, due reminders, historical tax snapshots, remaining PC/API integrations, Flutter wiring, invoice PDFs, PayHere verification, and production security/load testing remain. `orders_per_month` is enforced for direct orders and quote conversion; other plan limits are not. PayHere is deliberately disabled until signed server-side notification verification is completed.

Default plan amounts in the seeder and preview are **draft/demo values**. Confirm plan pricing, trial duration, invoice/tax rules and production stages before launch.

## Preview / source

Open `preview/index.html` to review the clickable UI. It includes dashboard, jobcard creation, production/status, estimates, quotations, invoices, receipts, payments, delivery challans, purchases/inventory, customers/employees, attendance, a separate Tax Rates & Groups page with a tax preview calculator, accounting, reports, settings and master data. See `docs/ui-screen-map.md` for the screenshot-to-page mapping. `START_HERE_SI.md` is the Sinhala status note. The Laravel integration/setup instructions are in `backend/README.md`; XAMPP-specific steps are in `backend/docs/XAMPP_SETUP_SI.md`. Windows users can run `setup-xampp-windows.bat` after installing XAMPP and Composer to install dependencies for the included Laravel app and generate an app key only when one is missing; it preserves existing `.env` credentials and key. It does not create the MySQL database.
