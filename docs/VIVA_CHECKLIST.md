# JhutLedger BD viva checklist

## 25 August four-feature preparation

1. Run `D:\Softwares\XAMPP\php\php.exe scripts\prepare_faculty_demo.php` and note the three printed IDs.
2. Use the live B2B listing for inventory/channel and quotation demonstrations.
3. Use the payment order for Admin verification, Supplier processing, completion, ledger, and receipt.
4. Use the separate cancellation order to prove atomic stock restoration.
5. Run the preparation command again only if the prepared orders have already been completed or cancelled; it never deletes existing records.

## Core milestone demonstration

1. Open `http://localhost/jhutledger/` and choose **Create an account**.
2. Register a new Supplier, B2B Buyer, or B2C Buyer.
3. Point out the success message: PHP committed the `users` row and exactly one subtype row in one transaction.
4. Log in with the new account. Explain that PHP retrieves the password hash, calls `password_verify()`, joins the subtype tables, and starts a regenerated session.
5. Show the role-specific dashboard and its live database counts.
6. Edit the profile to demonstrate a prepared `UPDATE` statement.
7. Log in as `admin@jhutledger.local` / `Demo@123`.
8. Open **DB Status** to show the PDO connection, MariaDB version, all 13 tables, and subtype counts.
9. Open **Users**, search for the new account, deactivate it, and show that it can no longer log in.

## Marketplace demonstration

1. Log in as the Supplier and create a textile batch.
2. Create a B2B or B2C listing without exceeding the batch's available quantity.
   Explain that one listing belongs to exactly one channel: B2B uses minimum quantity and wholesale quotation terms, while B2C uses bundle quantity and fixed retail pricing. The same batch can supply both only through separate listing IDs and stock allocations.
3. Log in as the matching buyer and filter the Marketplace.
4. For B2B, submit a quotation, counter it as Supplier, then accept it as Buyer. For B2C, place a bundle-sized direct order.
5. Submit a simulated buyer payment and use the Admin payment screen to mark it Paid or Failed.
6. As Supplier, move an order from Confirmed to Processing and Completed, then show the `SOLD` stock entry and sales report.
7. Place another order and cancel it before processing; demonstrate the restored quantities and `RESERVATION_RELEASED` entry.
8. Filter the supplier or platform report and download the matching CSV.
9. Show the confirmed order, reduced listing quantity, reduced batch availability, and new `RESERVED` stock transaction.
10. Explain that the order operation uses one transaction plus `SELECT ... FOR UPDATE` to prevent partial writes and overselling.

## Role workspace upgrade demonstration

1. As Supplier, open **Stock ledger**, filter by batch/type/date, and explain why `RESERVED` is negative, `RESERVATION_RELEASED` is positive, and `SOLD` is neutral.
2. Open **View details** on an order. Show participant ownership, immutable price/cost snapshots, payment, quotation, ledger activity, and the Confirmed → Processing → Completed timeline.
3. Open **Print invoice** and use the browser print preview. Point out that Paid becomes a receipt, while unpaid, refunded, and cancelled documents are clearly labelled.
4. As Admin, open **Exceptions** and explain the six derived queues: pending payments, overdue confirmations, expired quotations, low stock, zero-quantity active listings, and inactive users with open orders.
5. Narrow the browser window and demonstrate the grouped hamburger menu, stacked order cards, visible focus state, and Escape-to-close behavior.
6. Explain that these features reuse the approved 13 tables; no schema change or notification table was needed.

## Database points to explain

- `users` is the supertype; `supplier`, `b2b_buyer`, and `b2c_buyer` are total/disjoint subtypes enforced by the registration transaction.
- `listing` and its B2B/B2C subtype tables avoid duplicated common fields.
- `order_item` uses the composite key `(order_id, line_no)`.
- `quotation_id` and `payment.order_id` are unique, enforcing at most one converted order and one consolidated payment.
- Order completion and cancellation use row locks and database transactions so status, stock, payment, and ledger records remain consistent.
- Revenue and gross profit come from `order_item` snapshots, preserving historical values when current batch costs or listing prices change.
- Historical records use restrictive foreign keys and status changes rather than destructive deletion.
- Money and quantities use `DECIMAL`, not floating-point values.
- Every user-input query uses PDO prepared statements; forms use CSRF tokens and escaped output.
