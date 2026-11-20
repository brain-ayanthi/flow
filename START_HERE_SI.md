# PressFlow — ආරම්භක සටහන

ඔබ පැහැදිලි කළ භාවිත රටාවට අනුව **PC web පද්ධතිය ප්‍රධානයි; app එක අවශ්‍ය විට පමණක්**. Laravel backend එක shared source of truth ලෙස තබා ඇත. PC web login එක session auth භාවිත කරන අතර Flutter app එක Sanctum Bearer token API භාවිත කරයි; දෙකම එකම customer/order/production/invoice/payment/subscription data සහ rules භාවිත කළ හැකි ලෙස endpoints සකස් කර ඇත.

## Backend එකේ සකස් කළ කොටස්

- Unlimited business/workspace separation; නව DB schema තුළ locations/storefronts/warehouses, per-location invoice scheme/layout
- User memberships, Admin/Cashier default roles, custom roles/permissions සඳහා tables
- Web session login + `index.html` ආකාරයේ authenticated `/dashboard` UI shell. Customer Master, Jobcard Pricing Masters/settings, Jobcards/Orders create/list/detail, Production Job Status board සහ job-related dashboard summaries shared tenant-scoped Laravel API එකට සම්බන්ධයි; අනෙක් module screens බොහොමයක් තවම demo data.
- API registration/login/workspace select/logout
- Customers, orders + line items, server-side subtotal/discount/tax/total calculations
- Tenant-scoped Jobcard Masters: Flex Media, Fabrication, Digital Particular/Media/Size/Side, Digital/Paper price matrix and Other Items. Flex feet/inches use billing dimensions; fabrication and allowed media/Digital/Other overrides are priced by Laravel. No sample shop rates are seeded.
- Estimates/quotations API: priced items, draft editing, status workflow සහ accepted quote එක jobcard බවට convert කිරීම
- Dedicated Tax Rates & Groups page/API plus authenticated `/settings` page/API source. Settings cover the supplied screenshots: Business, Tax, Product, Contact, Sale, POS, Display Screen, Purchases, Payment, Dashboard, Jobcards, System, Modules, Reward Points and Prefixes. Preferences are tenant-scoped; module behavior still needs implementation (runtime-untested).
- Products/variations, brands/categories/units, location stock, adjustments/transfers, purchases, POS invoices, expenses, accounting සහ contact ledgers සඳහා tenant-scoped database migrations
- Production stage transitions සහ audit history
- Private artwork upload/download
- Order එකකින් invoice සාදීම සහ invoice එකකට කොටස් ගෙවීම් වාර්තා කිරීම
- Subscription plans, trial/write gating, monthly orders limit enforcement, bank-transfer receipt සහ platform-admin approve/reject flow
- PC සහ app එකම Laravel API එකට සම්බන්ධ විය හැකි ලෙස Sanctum සැලසුම

API endpoint ලැයිස්තුව: `backend/docs/API.md`. DB schema: `backend/docs/DATABASE_SCHEMA.md`. XAMPP setup: `backend/docs/XAMPP_SETUP_SI.md`. සම්පූර්ණ Laravel 12 app එක `laravel-app/` තුළ ඇත; command-line entrypoint එක `laravel-app/artisan` ය. Bundle එකේ `.env.example` ඇත; `.env` ඇතුළත් නොවන නිසා පවතින shop credentials/`APP_KEY` overwrite නොවේ. Root එකේ `setup-xampp-windows.bat` dependencies install කරන්නේ `.env` නොමැති විට පමණක් එය සාදයි, key එක හිස් නම් පමණක් app key generate කරයි (DB එක තනා migrations ධාවනය කිරීම වෙනම කළ යුතුය). පවතින XAMPP database එකකට නව bundle එක දමාගත් පසු `laravel-app` folder එකෙන් `C:\xampp\php\php.exe artisan migrate` ධාවනය කරන්න; pending additive migrations jobcard contact snapshot/round-off columns සහ Jobcard Master/Digital rate tables එක් කරයි, database reset නොකරයි. `migrate:fresh` ධාවනය නොකරන්න.

## තවම සම්බන්ධ නොකළ දේ

මෙම sandbox එකේ PHP/Composer නොමැති බැවින් migrations/framework tests run කර නැහැ; එබැවින් **live database එකක් මෙහි තවම නිර්මාණය කර නැත**. Laravel migration files සහ `DATABASE_SCHEMA.md` මගින් schema source එක සකස් කර ඇත; ඒවා clean Laravel/MySQL හෝ PostgreSQL environment එකක `php artisan migrate` සහ `php artisan db:seed` මගින් පරීක්ෂා කළ යුතුයි. PayHere checkout සහ signed notification adapter සත්‍යව සම්බන්ධ කර නැහැ; තෝරන විට API `501 payment_gateway_not_configured` දෙයි.

දැන් Laravel login පසු `preview/index.html` ආකාරයේ navigation සහ screens `/dashboard` තුළ පෙන්වයි. Customer Master, Jobcard Pricing Masters/settings සහ Jobcards/Orders UI එක database-backed ය: owners/managers tenant-specific masters හා තමන්ගේම prices ඇතුළත් කරයි; Flex billing area/fabrication, Digital/Paper matrix සහ Other Item rates Laravel විසින් validate කර නැවත ගණනය කරයි. Jobcard/item lines, contact snapshot, discount/round-off සහ due date ද server-side validate කර save වේ. Job Status board එක backend-validated production stages පෙන්වා, stage event history හා audit logs සුරකියි. Dashboard එකේ live job summary සහ recent jobs ද shared API එකෙන් ලැබේ. Billing & Finalization step එක jobcard save කරන විට invoice එක transaction එකක් තුළ සාදයි; advance එකක් තිබේ නම් tenant deposit account/payment mode සමඟ payment receipt reference එකක් (උදා: RCP-000123) ද සටහන් කරයි. Standalone Invoices/Payments list UI, quotes, delivery, purchases/inventory/accounting සහ වෙනත් screens තවම demo-only ය; WhatsApp checkbox එකද provider එකක් සම්බන්ධ කරන තුරු message නොයවයි. තවම අවශ්‍ය: XAMPP මත මේ නව migration සහ order/production/billing flow runtime පරීක්ෂා කිරීම; invoice/receipt list/detail UI shared API එකට සම්බන්ධ කිරීම; purchases/POS/stock/expenses/accounting services, module toggle enforcement, reward-point transactions, finalized tax snapshots, automatic ledger/stock posting, due-date reminders; සියලු modules සඳහා role/permission enforcement; Flutter API wiring, invoice PDF, PayHere verification සහ deployment/security/load tests. `orders_per_month` limit එක order creation සහ accepted quote conversion වෙලාවේ check කරයි. Tax rate values කිසිවක් නිල ලෙස seed කර නැහැ; business එකේ tax treatment තහවුරු කර tax page එකෙන් සකස් කළ යුතුය. Authenticated Laravel `/taxes` සහ `/settings` pages/API source ඇත; sandbox එකේ PHP/Composer නොමැති නිසා runtime පරීක්ෂාව තවම කර නැහැ.

## UI පෙරදසුන

`preview/index.html` තවමත් standalone clickable design preview එකයි. Laravel authenticated `/dashboard` තුළ Customer Master, Jobcard Pricing Masters/settings, jobcard create/list/details සහ production stages live database APIs භාවිත කරයි. Billing & Finalization එක invoice/advance receipt save කරයි; standalone Invoices/Payments lists සහ අනෙක් screens තුළ sample records/actions තවම database එකට save නොවේ. XAMPP හි `/dashboard`, Customers, Orders/Production, `/taxes`, `/settings` runtime එකෙන් පරීක්ෂා කරන්න. Screenshot එකෙන් page එකට ඇති mapping එක `docs/ui-screen-map.md` හි ඇත.
