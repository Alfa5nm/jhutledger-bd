# JhutLedger BD viva checklist

## Two-minute demonstration

1. Open `http://localhost/jhutledger/` and choose **Create an account**.
2. Register a new Supplier, B2B Buyer, or B2C Buyer.
3. Point out the success message: PHP committed the `users` row and exactly one subtype row in one transaction.
4. Log in with the new account. Explain that PHP retrieves the password hash, calls `password_verify()`, joins the subtype tables, and starts a regenerated session.
5. Show the role-specific dashboard and its live database counts.
6. Edit the profile to demonstrate a prepared `UPDATE` statement.
7. Log in as `admin@jhutledger.local` / `Demo@123`.
8. Open **DB Status** to show the PDO connection, MariaDB version, all 13 tables, and subtype counts.
9. Open **Users**, search for the new account, deactivate it, and show that it can no longer log in.

## Database points to explain

- `users` is the supertype; `supplier`, `b2b_buyer`, and `b2c_buyer` are total/disjoint subtypes enforced by the registration transaction.
- `listing` and its B2B/B2C subtype tables avoid duplicated common fields.
- `order_item` uses the composite key `(order_id, line_no)`.
- `quotation_id` and `payment.order_id` are unique, enforcing at most one converted order and one consolidated payment.
- Historical records use restrictive foreign keys and status changes rather than destructive deletion.
- Money and quantities use `DECIMAL`, not floating-point values.
- Every user-input query uses PDO prepared statements; forms use CSRF tokens and escaped output.

