# JhutLedger Request Flow Guide

JhutLedger uses the instructor's linear request style while retaining PDO prepared statements, CSRF protection,
role checks, ownership checks, password hashing, and database transactions.

```text
Display page -> HTML form -> POST action -> validation -> service function -> prepared query -> flash -> redirect
```

The browser never calls SQL directly. It submits a form to a small action file. That action validates the request and
calls a named PHP function. The function obtains the shared PDO connection through `db()`, prepares SQL, executes it,
and returns a result. The action then redirects the browser to a display page.

## Workflow map

| Display page | POST action | Important submitted fields | Service function | Query type and tables | Redirect |
|---|---|---|---|---|---|
| `Mixed/login.php` | `Mixed/actions/login.php` | email, password | `authenticateAccount()` | SELECT: `users` and subtype tables | Role dashboard |
| `Mixed/register.php` | `Mixed/actions/register.php` | identity, address, role, password | `registerAccount()` | INSERT: `users` and exactly one subtype | Login |
| `Mixed/profile.php` | `Mixed/actions/update-profile.php` | name, phone, address | `updateAccountProfile()` | UPDATE: `users` | Profile |
| Navigation header | `Mixed/actions/logout.php` | CSRF token | PHP session functions | No SQL | Login |
| `Farid/supplier/batches.php` | `Farid/supplier/actions/save-batch.php` | material, condition, quantity, cost, location | `saveSupplierBatch()` | INSERT/UPDATE: `textile_batch`; INSERT: `stock_transaction` for new stock | Batches |
| `Farid/supplier/batches.php` | `Farid/supplier/actions/archive-batch.php` | batch ID | `archiveSupplierBatch()` | UPDATE: `textile_batch` | Batches |
| `Farid/supplier/listings.php` | `Farid/supplier/actions/save-listing.php` | batch, channel, allocation, price terms | `saveSupplierListing()` | INSERT/UPDATE: `listing` and one channel subtype | Listings |
| `Farid/supplier/listings.php` | `Farid/supplier/actions/archive-listing.php` | listing ID | `archiveSupplierListing()` | UPDATE: `listing` | Listings |
| `Abir/marketplace.php` | `Abir/b2b/actions/create-quotation.php` | listing, quantity, proposed price, expiry | `createBuyerQuotation()` | INSERT: `quotation` | B2B quotations |
| `Abir/b2b/quotations.php` | `Abir/b2b/actions/accept-quotation.php` | quotation ID | `acceptQuotation()` | UPDATE/INSERT: `quotation`, `orders`, `order_item`, inventory, ledger | Quotations |
| `Abir/b2b/quotations.php` | `Abir/b2b/actions/cancel-quotation.php` | quotation ID | `cancelBuyerQuotation()` | UPDATE: `quotation` | Quotations |
| `Abir/supplier/quotations.php` | supplier quotation actions | quotation ID; counter price when needed | `acceptQuotation()`, `counterSupplierQuotation()`, `rejectSupplierQuotation()` | UPDATE/INSERT across quotation/order tables | Supplier quotations |
| `Abir/marketplace.php` | `Abir/b2c/actions/place-order.php` | listing ID, quantity | `placeB2cOrder()` | INSERT: `orders`, `order_item`; UPDATE inventory; INSERT ledger | B2C orders |
| `Abir/supplier/orders.php` | process/complete action | order ID | `advanceOrderStatus()` | UPDATE: `orders`; completion INSERTS `SOLD` ledger entry | Supplier orders |
| Buyer/supplier order pages | cancel action | order ID | `cancelOrder()` | UPDATE order/payment/inventory; INSERT release ledger | Order list |
| Buyer order pages | `Mixed/actions/repeat-order.php` | source order ID | `repeatPurchase()` | B2C order INSERT or B2B quotation INSERT | New order or quotation |
| `Mixed/return.php` | `Mixed/actions/return-order.php` | order ID | `returnOrder()` | UPDATE inventory/payment; INSERT `RETURNED` ledger entry | Order detail |
| `Shishir/payment.php` | `Shishir/actions/submit-payment.php` | order ID, method | `submitPayment()` | INSERT/UPDATE: `payment`; amount is read from `orders` | Order detail |
| `Shishir/admin/payments.php` | `Shishir/admin/actions/update-payment.php` | payment ID, status | `reviewPayment()` | UPDATE: `payment` | Admin payments |
| `Shishir/admin/users.php` | `Shishir/admin/actions/update-user-status.php` | user ID, status | `updateAccountStatus()` | UPDATE: `users` | Admin users |

Every action endpoint is POST-only. A direct GET returns HTTP 405. Missing CSRF tokens, invalid roles, and unrelated
records are rejected before a database change is allowed.

## Worked examples in plain language

### Login

`Mixed/login.php` -> user enters email and password -> POST to `Mixed/actions/login.php` ->
`authenticateAccount()` -> prepared SELECT reads the user and subtype -> `password_verify()` checks the stored hash ->
session ID is regenerated -> user is redirected to the correct dashboard.

The database connection starts when `bootstrap.php` loads `config/database.php`; calling `db()` returns the shared PDO
connection. The password itself is never placed inside the SQL query.

### Registration

`Mixed/register.php` -> user submits personal details, role, and password -> `Mixed/actions/register.php` validates them
-> `registerAccount()` hashes the password -> a transaction inserts one `users` row and exactly one subtype row ->
commit -> redirect to login. If the subtype insert fails, rollback removes the parent insert too.

### Batch creation

`Farid/supplier/batches.php` -> supplier enters stock details -> save-batch action -> `saveSupplierBatch()` -> prepared
INSERT creates `textile_batch` and a `STOCK_ADDED` ledger record in one transaction -> redirect to the batch list.

### Quotation acceptance

Quotation page -> buyer or supplier clicks Accept -> its accept action -> `acceptQuotation()` locks the quotation and
stock rows -> prepared queries create the order and item snapshot, reserve inventory, and add `RESERVED` -> redirect.

### B2C ordering

`Abir/marketplace.php` -> buyer enters a bundle-valid quantity -> place-order action -> `placeB2cOrder()` reads the
current server-side price and stock -> transaction creates the order and reserves stock -> redirect to order history.

### Payment

`Shishir/payment.php` -> buyer chooses a method -> submit-payment action -> `submitPayment()` reads the trusted total
from `orders` -> INSERT/UPDATE writes a simulated Pending payment -> redirect to order detail. The browser cannot submit
its own amount.

### Cancellation

Order page -> authorized participant clicks Cancel -> cancel action -> `cancelOrder()` locks the order, item, listing,
batch, and payment -> restores stock, updates statuses, and inserts `RESERVATION_RELEASED` -> commit -> redirect.

### Return

`Mixed/return.php` -> authorized participant confirms a completed-order return -> return action -> `returnOrder()`
checks that no earlier `RETURNED` marker exists -> restores the full quantity and refunds a Paid mock payment ->
redirect to order detail.

## Read-only POST exception

`Farid/supplier/pricing-assistant.php` submits to itself only to calculate a temporary price preview. It does not INSERT,
UPDATE, or DELETE anything. Publishing the suggestion still goes through the normal listing action and validation.
