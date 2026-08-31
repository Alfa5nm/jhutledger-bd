# JhutLedger BD — Faculty Milestone

A database-driven university project built with PHP 8.2, MariaDB/MySQL, PDO, HTML/CSS, and Bootstrap. It implements the complete 13-table schema, authentication and role authorization, supplier inventory and listing management, buyer marketplace search, B2B quotations, B2C orders, fulfilment, simulated payments, transactional cancellation, stock-ledger tracing, order timelines, printable invoices, sales reporting, admin exception monitoring, profile editing, and database health checks.

The 25 August expansion adds four modules without changing the schema: full-order returns with automatic mock-payment refunds, B2C reorder/B2B repeat quotation, a supplier pricing and margin assistant, and unit-safe textile-recirculation analytics with CSV export.

Marketplace, Supplier inventory, and order-detail screens also use locally bundled representative textile photographs selected automatically from each batch's material and composition. These defaults require no image column, upload storage, or network access.

## Database diagrams

- [Relational schema diagram](Mixed/docs/schema-diagram.png)
- [EER diagram](Mixed/docs/eer-diagram.png)

## Team ownership

| Folder | Primary owner | Modules |
|---|---|---|
| `Farid/` | Farid | Supplier dashboard, batches, listings, stock ledger, and pricing assistant |
| `Abir/` | Abir | Buyer workspaces, marketplace, quotations, and order processing |
| `Shishir/` | Shishir | Administration, payments, reports, and sustainability analytics |
| `Mixed/` | Joint work | Authentication, shared order features, services, assets, database, tests, and documentation |

The root `index.php` only launches the jointly owned landing page. This mapping records primary responsibility; shared infrastructure remains in `Mixed/` rather than being duplicated.

## Requirements

- XAMPP 8.2 with Apache, PHP, and MySQL/MariaDB
- PHP extensions `PDO` and `pdo_mysql`

The configured machine uses XAMPP at `D:\Softwares\XAMPP` and exposes this repository through `D:\Softwares\XAMPP\htdocs\jhutledger` (plus the compatibility path `C:\xampp`).

## Setup

### Manual setup

1. Install XAMPP 8.2 and place the repository at `<xampp>\htdocs\jhutledger`.

2. Start MySQL and Apache from the XAMPP Control Panel. On this development machine, the equivalent commands are:

   ```powershell
   D:\Softwares\XAMPP\mysql_start.bat
   D:\Softwares\XAMPP\apache_start.bat
   ```

3. Import `Mixed/database/schema.sql`, followed by `Mixed/database/seed.sql`, through phpMyAdmin. PowerShell can also perform the import when XAMPP is installed at `D:\Softwares\XAMPP`:

   ```powershell
   Get-Content Mixed\database\schema.sql -Raw | D:\Softwares\XAMPP\mysql\bin\mysql.exe -u root
   Get-Content Mixed\database\seed.sql -Raw | D:\Softwares\XAMPP\mysql\bin\mysql.exe -u root
   ```

4. Visit `http://localhost/jhutledger/`.

The former machine-specific batch installer has been removed. Manual setup keeps the repository portable across computers where XAMPP may be installed in different locations.

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
D:\Softwares\XAMPP\php\php.exe Mixed\tests\database_smoke.php
D:\Softwares\XAMPP\php\php.exe Mixed\tests\readability_check.php
D:\Softwares\XAMPP\php\php.exe Mixed\tests\request_flow_check.php
```

See [Mixed/docs/VIVA_CHECKLIST.md](Mixed/docs/VIVA_CHECKLIST.md) for the recommended faculty demonstration.
See [Mixed/docs/REQUEST_FLOW_GUIDE.md](Mixed/docs/REQUEST_FLOW_GUIDE.md) for the beginner-readable path from each form
to its POST action, service function, prepared query type, and redirect.

Before the four-feature demonstration, prepare non-destructive reusable records with:

```powershell
D:\Softwares\XAMPP\php\php.exe Mixed\scripts\prepare_faculty_demo.php
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
