# JhutLedger BD — Faculty Milestone

A database-driven university project built with PHP 8.2, MariaDB/MySQL, PDO, HTML/CSS, and Bootstrap. It implements the complete 13-table schema, authentication and role authorization, supplier inventory and listing management, buyer marketplace search, B2B quotations, B2C orders, transactional stock reservations, profile editing, admin controls, and database monitoring.

## Database diagrams

- [Relational schema diagram](docs/schema-diagram.png)
- [EER diagram](docs/eer-diagram.png)

## Requirements

- XAMPP 8.2 with Apache, PHP, and MySQL/MariaDB
- PHP extensions `PDO` and `pdo_mysql`

The configured machine uses XAMPP at `D:\Softwares\XAMPP` and exposes this repository through `D:\Softwares\XAMPP\htdocs\jhutledger` (plus the compatibility path `C:\xampp`).

## Setup

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

## Marketplace workflow

- Suppliers create and edit textile batches without deleting historical data.
- Suppliers allocate available batch quantities to B2B or B2C listings.
- Buyers search and filter active listings by material, district, and price.
- B2B buyers submit offers; suppliers accept, counter, or reject them.
- B2C buyers place bundle-sized orders directly.
- Accepted quotations and direct orders create `orders` and `order_item` records, reduce both listing and batch availability, and add a `RESERVED` stock transaction inside one database transaction.

Payment capture, shipment fulfilment, cancellations with stock release, and reporting remain future extensions.
