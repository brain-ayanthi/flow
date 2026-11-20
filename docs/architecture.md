# Architecture and MVP scope

## Product shape

One platform hosts multiple printing businesses with no hard-coded business-count limit. Each tenant (print shop/business) has members, storefronts/warehouses, contacts, products, location stock, jobs, quotes, purchases, invoices, payments, expenses and accounting. A shop's subscription is attached to its tenant; location-level invoice schemes/layouts and stock are separate inside that business. Platform administrators manage global plan definitions and can review manual payments. PC web is the primary client; Flutter is an optional companion. Both use the same Laravel backend, database, services, authorization and validation so a mobile action has the same business effect as the corresponding PC action.

The current `preview/index.html` is a PC-first design prototype based on the supplied print-management screenshots, with responsive layouts for smaller screens. Its sample data and demo actions are browser-local only; it is not the connected Laravel CRUD UI. See `ui-screen-map.md` for the page map.

## Stack

- **Web:** Laravel-rendered responsive dashboard (Blade or Inertia can be selected during implementation).
- **API:** Laravel JSON API, versioned under `/api/v1`, authenticated with Laravel Sanctum.
- **Mobile:** Flutter Android/iOS client using the same API.
- **Database:** MySQL or PostgreSQL; tenant-scoped tables carry `tenant_id` and composite indexes.
- **Queues/cache:** Redis-backed queues for report exports, notifications, and longer work; cache dashboard aggregates with short TTLs and clear them after relevant writes.
- **Files:** private object storage for artwork and invoice exports; deliver time-limited signed download links.
- **Payments:** PayHere sandbox then live credentials, plus bank transfer with pending/approved/rejected review states. Verify payment notifications server-side and make webhook processing idempotent.

## Initial functional modules

1. **Workspace and users:** tenant onboarding, invitations, roles (owner, manager, designer, production, cashier), current-shop selection.
2. **Customers:** contacts, addresses, notes, customer history and outstanding balance.
3. **Orders/jobs:** quote and job details, print type (digital, flex/large format, offset, signage), dimensions, stock/material, quantity, artwork, due date, priority, estimate/discount/tax/total, partial payment and notes.
4. **Production:** configurable stages such as New → Prepress/Proof → Printing → Finishing → Ready → Delivered; assign staff/machine, record progress and timestamps, flag late jobs.
5. **Invoices and payments:** numbered invoices, deposits/part-payments, cash/bank/card method, balance due, printable PDF and audit trail.
6. **Reports:** sales, receivables, order volume, production status, turnaround time and service mix; exports run in a queue for large datasets.
7. **SaaS billing:** editable plans, monthly/yearly periods, trials, limits/features, pending payment, active/past-due/cancelled/expired states, renewals and admin approval.
8. **Localization:** selectable Sinhala, English and Tamil strings, locale-aware dates/numbers, tenant currency/tax configuration.

## Subscription safeguards

- Store plan settings centrally and record a plan snapshot on each subscription so a price change does not rewrite existing history.
- Distinguish a print shop's **customer invoice payments** from that shop's **software subscription payment**.
- Never activate a PayHere subscription from a browser redirect alone; accept only a verified server notification and handle duplicate notifications safely.
- Store bank-transfer receipt metadata privately; require an authorized admin to approve it.
- Define grace period/read-only behavior and notify owners before/after renewal failures.

## Performance and data isolation

- Every tenant-owned record is queried under authenticated tenant context. Do not accept a user-supplied `tenant_id` as authorization.
- Composite indexes start with `tenant_id`, followed by common filters, e.g. `(tenant_id, status, due_at)`, `(tenant_id, created_at)` and `(tenant_id, customer_id)`.
- Paginate order/customer/invoice lists; avoid loading artwork blobs or full histories in dashboard queries.
- Cache small dashboard aggregates per tenant; invalidate after order/payment/status changes.
- Use eager loading for customer/order relations and queue PDF/report generation and email/SMS notifications.
- Add request throttling, strict validation, authorization policies, encrypted secrets, audit records, backups and error monitoring before launch.

## Pending business decisions for launch

- Final brand, logo, shop onboarding fields, plan names/prices, monthly/yearly discounts and trial duration.
- Exact quote/job fields, production steps and staff roles for the target print shops.
- VAT/tax invoice requirements, numbering format, refund/credit-note rules and invoice currency.
- PayHere merchant account and callback URL/domain, bank receipt process, email/SMS provider and hosting region.
