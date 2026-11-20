# API v1 (backend pilot)

Base URL: `/api/v1`. Authenticated routes accept a Sanctum bearer token (Flutter) or a Sanctum stateful session cookie (PC browser). All tenant endpoints resolve the workspace from the authenticated user's membership and `current_tenant_id`; clients must not submit `tenant_id` as authority.

## Authentication / workspace

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/auth/register` | Create an owner, shop workspace and initial trial when the Starter plan is seeded |
| POST | `/auth/login` | Issue a mobile token |
| GET | `/auth/me` | Current user, workspace and role |
| GET | `/auth/workspaces` | Workspaces the user belongs to |
| PUT | `/auth/workspaces/current` | Select a workspace using `{ "tenant_id": 1 }` |
| POST | `/auth/logout` | Revoke the current Sanctum token |

Registration example:

```json
{
  "name": "Shop Owner",
  "email": "owner@example.lk",
  "phone": "0771234567",
  "password": "a-long-password",
  "password_confirmation": "a-long-password",
  "shop_name": "Example Print House",
  "locale": "en"
}
```

## Shop operations

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/dashboard` | Tenant-scoped summary and recent jobs |
| GET / PATCH | `/settings` | Read and update tenant business, tax registration, product, sales, POS, display, purchase, payment, dashboard, system, module, rewards, prefix and Jobcard settings |
| POST | `/settings/logo` | Upload the business logo to the public business-logo disk; requires `settings.manage` |
| GET / POST / PATCH | `/contacts`, `/contacts/{customerId}` | Manage customer/supplier/both contacts, contact type filters and payment terms |
| GET / POST / PATCH | `/customers`, `/customers/{customerId}` | Compatibility aliases for customer flows; `include_walk_in=1` includes the per-business POS default |
| GET / POST / PATCH | `/jobcard-masters`, `/jobcard-masters/{masterId}` | Read/manage tenant Jobcard masters; create/update writes are restricted to owners/managers |
| POST / PATCH | `/jobcard-masters/digital-rates`, `/jobcard-masters/digital-rates/{rateId}` | Manage Digital/Paper price combinations keyed by Particular + Media + Size + Side; writes are restricted to owners/managers |
| GET / POST / PATCH | `/quotes`, `/quotes/{quoteId}` | List/detail, create and edit draft estimates/quotations with priced item lines |
| PATCH | `/quotes/{quoteId}/status` | Mark a quote sent, accepted, rejected or expired using validated transitions |
| POST | `/quotes/{quoteId}/convert` | Convert an accepted quote to a jobcard (plan order limit applies) |
| GET / POST / PATCH | `/orders`, `/orders/{orderId}` | List/detail, create jobs with line items, edit job details |
| PATCH | `/orders/{orderId}/stage` | Move job through the validated production workflow |
| POST / GET | `/orders/{orderId}/artwork` | Upload/download private artwork (authorized tenant members only) |
| GET | `/invoices`, `/invoices/{invoiceId}` | List and view invoices |
| GET / POST / PATCH / DELETE | `/tax/rates`, `/tax/rates/{rateId}` | Separate tax-page rate management; DELETE deactivates, preserving history |
| GET / POST / PATCH / DELETE | `/tax/groups`, `/tax/groups/{groupId}` | Manage inclusive/exclusive ordered rate groups; DELETE deactivates |
| POST | `/tax/preview` | Server-side tax calculation preview for amount, quantity and tax group |
| POST | `/orders/{orderId}/invoice` | Create an invoice from a print job |
| POST | `/invoices/{invoiceId}/payments` | Record cash, bank transfer, manual card or cheque part-payment |

Quote creation accepts `customer_id`, `title`, `items[]` (`description`, `quantity`, `unit_price`, optional `unit` and `specifications`), optional `discount`, `tax`, `expiry_at` and `notes`. Line totals and quote totals are calculated server-side; discount cannot exceed subtotal. Draft quotes can be edited, and accepted quotes convert transactionally to jobs while retaining the item and price snapshot. The quote can only be converted once; the `orders_per_month` limit also applies at conversion.

Example `POST /quotes` body:

```json
{
  "customer_id": 12,
  "title": "Shop-front banner",
  "expiry_at": "2026-10-25",
  "items": [
    {
      "description": "Flex banner 10 × 5 ft",
      "quantity": 2,
      "unit": "piece",
      "unit_price": 12500,
      "specifications": {"print_type": "flex", "width_ft": 10, "height_ft": 5}
    }
  ],
  "discount": 500,
  "tax": 0,
  "notes": "Customer requested eyelet finishing"
}
```

Set a quote to `sent` or `accepted` with `PATCH /quotes/{quoteId}/status` and `{ "status": "accepted" }`, then call `POST /quotes/{quoteId}/convert` to create the jobcard.

Order creation accepts `customer_id`, `title`, `print_type`, optional `job_date`, contact-name/phone/WhatsApp snapshots, `due_at`, notes, priority and either `items[]` (`description`, `quantity`, `unit`, `unit_price`, optional item specifications) or a subtotal. Totals are calculated server-side from item lines, discount, tax and optional `round_off`; discount cannot exceed the calculated subtotal and the final total cannot be negative. The server assigns the tenant and job number. Jobcard items, customer/contact snapshot, prices and due date are stored transactionally; the initial `new` stage is also written to production history. An itemized invoice is automatically created in the same transaction for every newly created jobcard; API clients may omit `auto_invoice` (default `true`), and an explicit `false` is rejected. Optional `advance_amount` must not exceed the final total and requires `payment_method` (`cash`, `bank_transfer`, `card_manual` or `cheque`) plus an active same-tenant `payment_method_id` (one of `GET /jobcard-masters` → `payment_methods`); `payment_reference` is optional. The resulting payment is stored with status `succeeded` and exposes a stable derived receipt number such as `RCP-000123`. The WhatsApp checkbox is currently presentation-only; no outbound message is sent.

### Jobcard masters and server pricing

`GET /jobcard-masters` returns the current tenant's master lists, Digital/Paper rate matrix, Jobcard defaults and active tenant-scoped payment methods for the deposit-account selector. Owners/managers can create or update master types `flex_media`, `fabrication`, `digital_particular`, `digital_media`, `digital_size`, `digital_side` and `other_item`; matrix rates can be added/edited through `/jobcard-masters/digital-rates`. Master writes are tenant-scoped, and matrix foreign keys require all four selected masters to belong to the same tenant and be active. Archiving one of those digital masters deactivates matrix combinations that depend on it. Prices are entered by each print shop; no sample business rates are seeded.

For typed Jobcard items, `items[].specifications.category` is `flex`, `digital` or `other`. Flex lines send the selected `media_id`, optional `fabrication_id`, dimension unit (`ft` or `in`), production/job-sheet `width`/`height`, chargeable `billing_width`/`billing_height`, piece `quantity`, and optional `rate_override`. The web wizard initially auto-fills Billing W/H from W/H; the billing fields can then be adjusted separately. The server stores both pairs, but computes billable area only from billing dimensions (inches are divided by 144), multiplies area by the selected media rate, and adds fabrication based on its configured `none`, `per_piece`, `per_sq_ft` or `flat_per_line` mode. A separate `fabrication_rate_override` / `fabrication_rate` can override that charge when workspace settings permit. Digital lines require `particular_id`, `media_id`, `size_id` and `side_id`; the server matches the active matrix rate and multiplies by quantity. Other lines require `other_item_id` and use its master unit rate times quantity. For Digital and Other, `rate_override` permits a Jobcard-level unit-rate override only when the workspace allows it. A missing active matrix combination or invalid/inactive master is a validation error, not a silent zero-price item. The saved item specification retains master IDs/names and the calculated pricing snapshot. Legacy/manual order API items that do not specify one of these typed categories continue to use their explicit unit price.

Jobcard defaults live in `GET/PATCH /settings` under `category: "jobcard"`: `default_flex_dimension_unit` (`ft` or `in`) and `allow_rate_override` (boolean). This default is applied server-side when a Flex API client omits the dimension unit; each explicit line can still select feet or inches. Updating settings requires the existing `settings.manage` permission; the master CRUD endpoints specifically allow only `owner` and `manager` roles.

Stage transitions currently allowed:

`new → prepress → proof_approved → printing → finishing → ready → delivered`

A job can be cancelled before delivery; finishing can return to printing, and ready can return to finishing. Each transition updates the order status/stage and adds a tenant-scoped event plus audit log; invalid transitions are rejected. The Job Status board reads the live orders and exposes only valid next stages. The stage values are MVP defaults and should become tenant-configurable after confirmation of each shop's real workflow.

Tax rates and groups remain a dedicated module/page; the separate Settings → Tax tab stores business registration labels/numbers and the inline purchase/sale tax preference. Rates support percentage/fixed calculations, compound order, validity dates and active/archive states; groups combine ordered rates and specify whether entered prices include tax. The preview endpoint handles inclusive tax extraction and refuses an active group with inactive/out-of-date rates rather than silently undercharging; invoice/purchase finalization must call the same server calculator and store the returned rate snapshot. Rates are not seeded by default: confirm the business's applicable tax rules first. Tax endpoints require `taxes.view` or `taxes.manage` role permissions.

## Business settings

Settings are stored per business in the existing tenant `settings` JSON document, while core identity/preferences (business name, start date, currency, timezone, financial year and default margin) use typed tenant columns. Prefix settings also update the tenant-level `number_sequences` rows. The API accepts one validated settings section at a time:

```json
{
  "category": "sale",
  "settings": {
    "default_sale_discount": 10,
    "default_tax_group_id": null,
    "allow_overselling": false
  }
}
```

`GET /settings` returns values plus same-tenant units, active tax groups, payment methods and currency options. `POST /settings/logo` accepts a JPG/PNG/WebP image up to 3 MB. Writes require `settings.manage`. Module and rewards settings are persisted, but workflow enforcement must be completed as the corresponding modules are implemented. Host database/backups and gateway secrets are intentionally not editable in tenant settings.

## SaaS subscriptions / platform administration

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/subscription/plans` | Public active plan catalogue |
| GET | `/subscription/current` | Current/latest tenant subscription and write-access status |
| GET | `/subscription/payments` | Shop's subscription payment history |
| POST | `/subscription/requests` | Request a plan; bank-transfer pilot is available |
| POST | `/subscription/payments/{paymentId}/receipt` | Upload private bank-transfer proof |
| GET | `/platform/subscription-plans` | Platform-admin plan list |
| POST / PUT | `/platform/subscription-plans`, `/platform/subscription-plans/{planId}` | Platform-admin plan management |
| GET | `/platform/subscription-payments/pending` | Platform-admin manual review queue |
| GET | `/platform/subscription-payments/{paymentId}/receipt` | Authorized private receipt download |
| POST | `/platform/subscription-payments/{paymentId}/approve` | Approve transfer and activate the subscription |
| POST | `/platform/subscription-payments/{paymentId}/reject` | Reject transfer with a reason |

**PayHere is intentionally not active yet.** The plan request endpoint responds with `501 payment_gateway_not_configured` for PayHere rather than pretending a payment succeeded. A server-side checkout/notification adapter must be configured and verified before enabling it. Customer invoice payments and software-subscription payments are separate records.

## Roles and write access

- `owner`, `manager`, `cashier`, `designer`, `production` are tenant roles in the initial middleware.
- Reads remain available when a plan expires; writes are blocked with HTTP `402 subscription_required` until a trial or paid period is valid.
- The `orders_per_month` limit from the subscription plan snapshot is checked before order creation and returns `402 plan_limit_reached` when full. Team, storage and feature limits are schema-ready but not yet enforced.
- Platform admin routes use a separate `is_platform_admin` flag and do not rely on a shop's tenant context.
- Private files are stored on Laravel's local private disk. Do not expose their paths as public URLs.
