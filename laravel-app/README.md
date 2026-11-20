# PressFlow Laravel application

This is the Laravel 12 source tree for local XAMPP testing. The Laravel entrypoint is included at **`laravel-app/artisan`** (this directory's `artisan` file), together with `composer.json`, `public/`, configuration, bootstrap, migrations, routes, views and PressFlow code. Composer dependencies (`vendor/`) are intentionally not bundled; install them on the Windows machine with Composer.

## XAMPP setup

1. Start Apache and MySQL in XAMPP. Install Composer and configure it to use your XAMPP `php.exe`; check your actual PHP version against Laravel 12's requirements rather than assuming a version.
2. From this folder run `composer install`. Review `.env` before connecting: set the MySQL host, database, username and password to match your own XAMPP installation. Do not assume a blank MySQL password, and do not replace an existing shop's `.env` or `APP_KEY`.
3. For a **new empty database only**, create the `pressflow` database in phpMyAdmin (utf8mb4), or run `database/create_database.mysql.sql`. For an existing shop database, keep its data and use additive migrations only.
4. In this folder, run `C:\xampp\php\php.exe artisan config:clear`. For a brand-new, empty database only, run `C:\xampp\php\php.exe artisan migrate --seed`. For an existing shop database, run `C:\xampp\php\php.exe artisan migrate` only. Never use `migrate:fresh` against data you need to keep.
5. If needed, generate an `APP_KEY` only for a new installation with no existing key: `C:\xampp\php\php.exe artisan key:generate`. Run `C:\xampp\php\php.exe artisan storage:link` for public business logos.
6. For a quick check run `C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000`; open `http://127.0.0.1:8000`. For Apache, set the VirtualHost document root to this app's `public/` folder only.

## Jobcard setup and pricing

Owners and managers can manage the tenant's **Jobcard Masters & Pricing** page: Flex Media, Fabrication, Digital Particular/Media/Size/Side, the Digital/Paper price matrix, and Other Items. Enter the shop's own rates—no business prices are seeded. W/H are stored as production/job-sheet dimensions and auto-fill Billing W/H; billing dimensions determine the square-foot chargeable area and can be adjusted separately. Flex adds the selected fabrication charge. Digital/Paper prices match Particular + Media + Size + Side. Other Item prices use the selected master. Jobcard settings control the default Flex unit and whether line-rate overrides are allowed; Laravel recalculates item and job totals when saving.

## Billing & Finalization

The final jobcard step mirrors the supplied Billing & Finalization screenshot: billing summary, discount and optional round-off, advance received, payment mode, tenant-scoped deposit account, optional reference, comment, WhatsApp option, delivery date/time and always-on auto-invoice indicator. Step 1 defaults to the tenant's Walk-In Customer; the lookup provisions that system contact if it is missing from an existing workspace. Walk-In jobs require advance equal to the final bill; creating or selecting a named customer is required to leave a balance due, and the orders/invoice services enforce the same rule. Saving creates the jobcard, invoice and optional advance payment in one database transaction. On save, a separate browser print window opens a single-page A5 landscape Job Card styled after the supplied sample and launches the browser print dialog. The card includes customer/job/material/delivery details, payment summary, stage tracking and print-finish panels. Print typography is increased for legibility (main text at least 5pt, with checkbox glyphs kept to their fixed boxes) while retaining the single-page A5 landscape layout. Flex sizes use production W/H (not billing W/H), and item summaries, customer contact/address, due date, notes and advance/payment method are populated when available. A Print Job Card action is also available from jobcard details if the popup is blocked or the job is reprinted. The payment row exposes a stable receipt reference such as `RCP-000123`; invoice and payment IDs/status are returned with the created jobcard and shown in its details. Clicking Paid Amount in Job Cards opens a database-backed Payment Receipts modal with receipt date/time, number, amount, mode, status and recording user for the job's invoice payments. An advance must not exceed the final bill and requires both a payment mode and active deposit account. The WhatsApp checkbox is displayed but not sent because a WhatsApp provider is not configured. The standalone invoice/receipt/payment lists remain prototype previews.

The migration `2026_10_13_000001_create_jobcard_masters_and_pricing.php` creates tenant-scoped master and digital-rate tables. For an existing database, run `artisan migrate` to apply it without resetting existing data.

## Checks

Run the feature suite from this folder with `C:\xampp\php\php.exe artisan test`. Tests cover auto-invoice and advance-receipt creation, Walk-In lookup and full-payment enforcement, Job Cards list and Payment Receipts metadata, safe defaults for legacy Jobcard settings, Flex feet/inches and fabrication pricing, Digital/Paper matrix and override pricing, Other Item pricing, and tenant isolation. This sandbox has no PHP or Composer executable, so PHP tests and XAMPP runtime checks have not been run here.

The XAMPP guide is at `../backend/docs/XAMPP_SETUP_SI.md`. Do not use local development settings in production, and do not share database credentials.
