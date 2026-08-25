# JhutLedger BD — Faculty Milestone

A database-driven university project built with PHP 8.2, MariaDB/MySQL, PDO, HTML/CSS, and Bootstrap. It implements the complete 13-table schema, authentication and role authorization, supplier inventory and listing management, buyer marketplace search, B2B quotations, B2C orders, fulfilment, simulated payments, transactional cancellation, stock-ledger tracing, order timelines, printable invoices, sales reporting, admin exception monitoring, profile editing, and database health checks.

The 25 August expansion adds four modules without changing the schema: full-order returns with automatic mock-payment refunds, B2C reorder/B2B repeat quotation, a supplier pricing and margin assistant, and unit-safe textile-recirculation analytics with CSV export.

## Database diagrams

- [Relational schema diagram](docs/schema-diagram.png)
- [EER diagram](docs/eer-diagram.png)

## Requirements

- XAMPP 8.2 with Apache, PHP, and MySQL/MariaDB
- PHP extensions `PDO` and `pdo_mysql`

The configured machine uses XAMPP at `D:\Softwares\XAMPP` and exposes this repository through `D:\Softwares\XAMPP\htdocs\jhutledger` (plus the compatibility path `C:\xampp`).

## Setup

### One-click setup on another Windows computer

Extract the repository, then double-click `install-jhutledger.bat`. The installer detects or installs XAMPP 8.2, copies the application to `htdocs\jhutledger`, starts Apache and MySQL, imports `schema.sql` followed by `seed.sql`, runs the database smoke test, and opens the application. A fresh XAMPP installation with the default empty `root` database password is expected.

### Manual setup

1. Start MySQL and Apache from XAMPP Control Panel, or run:

   ```powershell
   D:\Softwares\XAMPP\mysql_start.bat
   D:\Softwares\XAMPP\apache_start.bat
   ```

2. Import the database files in order:

   ```powershell
   Get-Content database\schema.sql -Raw | D:\Softwares\XAMPP\mysql\bin\mysql.exe -u root
   Get-Content database\seed.sql -Raw | D:\Softwares\XAMPP\mysql\bin\mysql.exe -u root
   ```

   The same files can be imported through phpMyAdmin.

3. Visit `http://localhost/jhutledger/`.

Environment overrides are supported through `JHUTLEDGER_DB_HOST`, `JHUTLEDGER_DB_PORT`, `JHUTLEDGER_DB_NAME`, `JHUTLEDGER_DB_USER`, `JHUTLEDGER_DB_PASSWORD`, `JHUTLEDGER_BASE_URL`, and the comma-separated `JHUTLEDGER_ADMIN_EMAILS`.

## Demo accounts

All demo accounts use password `Demo@123`.

| Access | Email | Academic subtype |
|---|---|---|
| Admin overlay | `admin@jhutledger.local` | B2B Buyer |
| Supplier | `supplier@jhutledger.local` | Supplier |
| B2B Buyer | `b2b@jhutledger.local` | B2B Buyer |
| B2C Buyer | `b2c@jhutledger.local` | B2C Buyer |

Admin access is a server-side email allowlist overlay. It does not add an Administrator subtype or change the academic EER model.

## Validation

Run PHP syntax and database smoke tests:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { D:\Softwares\XAMPP\php\php.exe -l $_.FullName }
D:\Softwares\XAMPP\php\php.exe tests\database_smoke.php
```

See [docs/VIVA_CHECKLIST.md](docs/VIVA_CHECKLIST.md) for the recommended faculty demonstration.

Before the four-feature demonstration, prepare non-destructive reusable records with:

```powershell
D:\Softwares\XAMPP\php\php.exe scripts\prepare_faculty_demo.php
```

The command reuses suitable Confirmed orders when available; otherwise it creates quotation-backed demo orders for completion/payment and cancellation without deleting existing data.

## Marketplace workflow

- Suppliers create and edit textile batches without deleting historical data.
- Suppliers allocate available batch quantities to B2B or B2C listings.
- Each listing belongs to exactly one channel and displays its listing ID, source batch ID, allocation, and channel-specific terms. One batch may fund separate B2B and B2C allocations.
- Buyers search and filter active listings by material, district, and price.
- B2B buyers submit offers; suppliers accept, counter, or reject them.
- B2C buyers place bundle-sized orders directly.
- Accepted quotations and direct orders create `orders` and `order_item` records, reduce both listing and batch availability, and add a `RESERVED` stock transaction inside one database transaction.
- Suppliers advance orders from Confirmed to Processing and Completed; completion records a `SOLD` ledger entry.
- Buyers submit simulated payments and administrators mark Pending submissions as Paid or Failed.
- Buyers or suppliers can cancel before processing, restoring stock and recording `RESERVATION_RELEASED` atomically.
- Supplier and administrator reports calculate completed-order revenue and gross profit from historical order-item snapshots and support CSV export.
- Authorized buyers, suppliers, and administrators can inspect a shared order timeline and print an invoice or paid/refunded receipt from immutable order snapshots.
- Supplier stock-ledger filters explain positive, negative, and neutral movements without subtracting `SOLD` stock twice.
- The Admin exception monitor derives six live attention queues without adding notification tables.
- Authenticated workspaces use grouped mobile navigation, stacked priority tables, task cues, accessible status markers, reduced-motion support, and repeated-submit prevention.

Real payment gateways, shipment tracking, delivery management, persistent notifications, and multi-item carts remain future extensions.
