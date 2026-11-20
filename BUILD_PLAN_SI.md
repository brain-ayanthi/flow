# PressFlow සම්පූර්ණ පද්ධතිය — build plan

## ඉලක්කය

`preview/index.html` හි නව PC-first UX එක production Laravel SaaS එකක් බවට පත් කිරීම. Laravel එකම data, permissions, calculations, tenant boundaries සහ business rules සඳහා source of truth වේ. Flutter Android/iOS app එකත් ඒ shared API එකම භාවිත කරයි—mobile සඳහා වෙනම business logic එකක් නොවේ.

## දැනට ඇති පදනම

- Responsive, navigable PC prototype: core print workflow plus separate **Tax Rates & Groups** and screenshot-driven **Settings** pages.
- Complete Laravel 12 source tree included at `laravel-app/` with `artisan`, `composer.json`, framework configuration and the PressFlow overlay. The supplied `index.html` UI shell is loaded at authenticated `/dashboard`; Customer Master, Jobcard Pricing Masters/settings, Jobcard/Order create/list/detail, Production stage updates and job-related dashboard data now persist/read through session-authenticated tenant APIs. Flex feet/inches and fabrication, Digital/Paper matrix, Other Item and permitted rate-override calculations are server-side. Billing & Finalization now atomically creates an invoice with the jobcard and records an optional advance receipt; standalone invoice/payment and other module screens remain demo-only. Composer dependencies and runtime verification remain pending.
- දැන් එකතු කළ additive migrations: unlimited businesses, locations/warehouses, roles/permissions, customer/supplier contacts, tax rates/groups, product variations, location stock, purchases, POS invoices, expenses සහ accounting foundations.
- Tax focus: tenant-specific rate/group schema, Tax CRUD/calculation APIs, inclusive/exclusive/compound preview, default Admin/Cashier tax permissions, and tests written. Legal tax values are intentionally not seeded.
- Screenshot-based settings: authenticated tenant API/page for business profile, tax registration, product/contact/sale/POS/display/purchase/payment/dashboard/system/module/reward and prefix preferences, plus persisted Jobcard dimension-unit and rate-override defaults; runtime verification is pending.

## අදියරෙන් අදියර

### 1. Database + tax backend එක run කර පරීක්ෂා කිරීම — දැන්

- Included `laravel-app/` එකේ Composer dependencies install කර, XAMPP PHP + MySQL (හෝ වෙනත් supported DB) environment එකක migrations/seeds run කර verify කිරීම.
- Migration/schema errors, tenant foreign-key isolation, location defaults, Admin/Cashier permissions සහ Walk-In Customer seeding පරීක්ෂා කිරීම.
- Authenticated `/taxes` PC page + API source සකස් කර ඇත; environment එකේ run කර code/name/type/rate, effective dates, compounding order, inclusive/exclusive groups, permission gates සහ archive/reactivation behavior verify කිරීම.
- Supplied settings screenshots අනුව `/settings` page/API persistence, date/currency/time/stock defaults, tax registrations, product/contact/sale/POS/purchase/payment controls, module flags, rewards, logo upload සහ number prefixes verify කිරීම.
- Jobcard Masters, digital rate matrix, Jobcard dimension/override settings සහ Flex/Digital/Other server calculations XAMPP මත feature tests සමඟ verify කිරීම; cross-tenant master rejection ද පරීක්ෂා කිරීම.
- VAT/SSCL වැනි නිල rates tax page එකේ owner විසින් සකසන තුරු sample values පමණක් පෙන්වීම; tax history snapshots final transaction සමඟ සුරැකීම.
- Tax exclusive/inclusive, fixed-rate, compound, discounts, rounding සහ effective dates සඳහා automated feature tests.

**පිළිගැනීම:** එක් business එකක tax/location/customer/product data වෙනත් business එකකට නොපෙනීම; location අනුව stock/numbering වෙනස් වීම; tax preview හා final server calculation එක සමාන වීම; finalized invoice/purchase වල tax snapshot නොවෙනස්ව පැවතීම.

### 2. Core workflow services + permissions

- Jobcard typed line pricing (Flex, Digital/Paper, Other Items) සහ master validation source එකේ සකසා ඇත; quote-to-job pricing parity, production-specific material use, POS invoices, receipts/payments, supplier purchases සහ stock movements සඳහා ඉතිරි server-side rules/transactions සකස් කිරීම.
- Stock adjustments/transfers, sale/purchase posting, account journal posting සහ due-date reminders සඳහා transaction-safe services.
- All APIs සඳහා roles/permissions, tenant/location tests, audit, pagination/search, subscription checks.

### 3. Preview එක Laravel PC app එකට සම්බන්ධ කිරීම — Customer + Jobcards/Orders + Production + Billing Finalization live; invoice lists ඉදිරියට

- Login සහ authenticated UI shell/dashboard ඇත; workspace/location selector ඉදිරියට.
- Customer Master create/list/edit සහ Jobcard/Order create/list/search/detail Laravel tenant API එකට සම්බන්ධ කර ඇත; itemized prices server-side ගණනය වේ. Jobcard Pricing Masters සහ Jobcard settings ද tenant API වෙත සම්බන්ධය; owners/managers Flex Media, Fabrication, Digital Particular/Media/Size/Side, Digital Price Matrix සහ Other Items පවත්වාගෙන යයි.
- Job Status board එක API එකෙන් load කරයි; valid production-stage transitions, history events සහ audit log database එකට යයි. Role middleware controls writes.
- Dashboard open jobs, stage distribution, due-date buckets, recent jobcards live endpoint එකෙන් ලැබේ.
- Jobcard Billing & Finalization එක save කරන විට invoice එකක් සාදා, optional advance payment එකක් selected tenant payment method එකට ගිණුම්ගත කර receipt reference එකක් ලබා දෙයි; order + invoice + payment එක transaction එකකින් සුරැකේ. Standalone invoice/receipt/payment lists, quotes, POS, delivery challan, purchases, inventory, expenses සහ accounting screens තවම preview/demo data ය; WhatsApp යැවීම provider integration අවශ්‍ය වේ.
- Order list එකේ loading/error/empty states, pagination, search, stage filter ඇත; non-integrated module interactions තවම preview-only ය.

**පිළිගැනීම:** refresh/login පසුව jobcard/items/stage history database එකෙන්ම පෙන්වීම; Flex feet/inches + billing-area/fabrication, Digital/Paper matrix සහ Other Item totals server-side ගණනය වීම; invalid stage transition ප්‍රතික්ෂේප වීම; customer/order/event/master data වෙනත් tenant එකකට නොපෙනීම.

### 3A. ඉදිරි PC/API slice — Invoice & Payment list/details

- Jobcard save සමඟ invoice + optional advance payment/receipt record transaction-safe ලෙස නිර්මාණය කර ඇත; cash, bank transfer, manual card සහ cheque modes, tenant-scoped deposit accounts සහ overpayment rejection backend මගින් වලංගු කරයි.
- ඉදිරියට: Invoice list/details, existing invoice balance/status refresh, additional part-payment UI, loading/error/empty states සහ receipt display/PDF සම්බන්ධ කිරීම.
- Browser refresh/login පසුව invoice/payment records පෙන්වීම සහ PC/API එකම rules භාවිත කිරීම end-to-end XAMPP test කිරීම.

**පිළිගැනීම:** invoice/payment පසුවත් browser refresh/login එකේ database දත්ත පෙන්වීම; partial payment balance එක සමානව ගණනය වීම; PC සහ shared API එකම rules භාවිත කිරීම.

### 4. Print-shop operations පුළුල් කිරීම

- Product/media/fabrication/payment-mode masters.
- Purchases, stock movement, reorder alerts සහ job material usage.
- Employee/roles/invitations, work assignment සහ attendance import.
- Delivery challan, customer history, follow-up tasks.

### 5. Accounting සහ business intelligence

- Cash/bank books, journal/contra, chart of accounts, day closing.
- P&L, trial balance, balance sheet, receivables aging, sales/profit, production/delivery reports.
- CSV/Excel/PDF exports; විශාල reports queue jobs ලෙස.

### 6. SaaS subscriptions සහ payment gateways

- Platform admin UI: plan prices, features, limits, trials, payment review.
- `orders_per_month` enforcement දැනට ඇත; team/storage/features ඇතුළු අනෙකුත් limits සම්පූර්ණ කිරීම.
- Manual bank-transfer flow harden කිරීම.
- PayHere sandbox checkout + signature-verified, idempotent server notification පරීක්ෂා කළ පසුව පමණක් සක්‍රීය කිරීම. දැනට PayHere operational නොවේ.

### 7. Flutter, localization සහ launch

- API-backed Flutter auth/workspace selector සහ එකම business modules.
- Sinhala/English/Tamil UI strings, date/number formatting, accessibility.
- Security review, backups, monitoring, queue/cache, load tests, staging then production release.

## දැන් අවශ්‍ය දේ

Backend code එක run කිරීමට clean Laravel project එකක්, PHP/Composer, MySQL/PostgreSQL සහ `.env` config අවශ්‍යය. මෙම build sandbox එකේ PHP/Composer නොමැති නිසා migrations/framework tests මෙහි run කළ නොහැක; source-level syntax scan සහ review පමණක් කළ හැක. Production සඳහා අවසන් කිරීමට plan prices/trial, tax/invoice rules සහ shop එකේ නිශ්චිත production stages ඔබෙන් තහවුරු කළ යුතුයි. ඒ තුරු preview values draft ලෙස තබමු.
