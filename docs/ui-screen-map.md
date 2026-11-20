# UI screen map — video + supplied references

The static prototype applies a new PressFlow visual system (dark navy navigation, blue/cyan actions, light workspace, responsive PC-first layout) while retaining the features and fields shown in the screenshots.

| Reference area | New prototype page/workflow |
|---|---|
| Print MIS overview with KPI tiles, live-job status and due-date chart | Dashboard with six KPI cards, status donut, due-date bars, Task & Call list and outstanding reminders |
| New Jobcard — customer information | Three-step New Jobcard wizard: customer, order-by/contact details and previous outstanding bills |
| Flex / Digital-Paper / Other job item tabs | Item editor with Add/Remove line actions; flex dimensions/billing area, digital/paper particulars/media/side, and other items/size/quantity/rate |
| Billing & Finalization | Screenshot-matched totals, discount, round-off, advance/payment mode/deposit account/reference, comment, WhatsApp option, delivery date/time, always-on auto invoice; save creates the invoice and optional advance receipt atomically (WhatsApp sending awaits provider setup) |
| Job and production status overview | Job Status board, job-card list and designer/printer work lists |
| Invoice and payment lists | Invoice, receipt and payment tables with filters, balances and export/print actions |
| Tax rates/groups management | Separate Tax Rates & Groups page with rate/group tabs, inclusive/exclusive choice, tax calculator and a warning that sample rates are not configured legal values |
| Multiple storefronts/warehouses | Location-specific stock/purchase/sale/invoice data is represented in Laravel migrations; location selector/workflows remain to be connected to the other PC pages |
| Master Data screen | Cards for employee/customer/bank/media/digital/other/fabrication/rates/status/machine/purchase/material/outsource masters, including a Tax Rates & Groups shortcut |
| Reports screen | 24 report cards including P&L, trial balance, jobcard, fabrication, aging, delivery, status and day closing |
| Attendance management | Employee/date filters, import/refresh and attendance list layout |
| Company/business settings reference screenshots | Business profile/financial defaults, tax registrations and inline-tax toggle, product/contact/sale/POS/display/purchase/payment/dashboard/system options, module switches, reward-point earning/redemption and document prefixes. Dedicated invoice numbering/layout still stays location-specific. |
| Print workflow described in the YouTube demo | Estimates, orders/jobs, production progress, delivery challans, invoices, receipts, payments and reports |

The static preview still uses sample in-browser data. Its tax page is a demo; an authenticated Laravel `/taxes` page is also in the backend source and calls the tenant-scoped Tax API. PHP/Composer are unavailable here, so that Laravel page and its database migrations have not been run in this environment.
