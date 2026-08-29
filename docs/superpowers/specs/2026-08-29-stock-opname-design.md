# Stock Opname Web Design

## Goal
Build a mobile-first Laravel web application for fast physical stock checking using barcode scans, multiple concurrent checkers, multiple ERP warehouses in one cycle, free-text rack/location tracking, UOM ratio conversion, opening/closing ERP snapshots, and admin variance review.

## Architecture
- Application framework: Laravel 10, PHP ^8.1 (PHP 8.2 recommended to match the existing XAMPP82 environment).
- Primary application database: PostgreSQL on localhost for testing.
- ERP sources: SQL Server databases AS_INGCO and AS_SMI, read-only from the application.
- UI: Laravel Blade, mobile-first responsive CSS, camera barcode scanning via browser JavaScript with keyboard/manual fallback.
- Authentication roles: ADMIN and CHECKER.

## ERP Sources
Warehouse master is read from `IC_Warehouses` where `Disabled = 0`, ordered by `DisplaySequence`.
Barcode resolution uses `IC_Aliases` where `AliasType = 'Barcode'`, joined to `IC_Items` and `IC_UOM` based on Alias UOMLevel.
Opening and closing stock snapshots use `dbo.USP_Dashboard_SisaStokWarehouse` and the `Smallest On Hand` result per ItemID + WarehouseID.

## Cycle Rules
- Cycle number format: SO-YYYYMMDD-XXX.
- One cycle uses exactly one source ERP database and may contain multiple warehouses.
- Admin assigns one or more checkers to each warehouse; a checker may be assigned to multiple warehouses.
- Cycle statuses: DRAFT, OPEN, CLOSED.
- Opening stock is captured when the admin starts the cycle.
- Closing stock is captured when the admin closes the cycle.
- Net ERP movement is the difference between closing and opening stock; this is an indicator, not a full transaction-history audit.

## Checker Workflow
1. Login.
2. View only active assignments.
3. Select an assigned warehouse.
4. Enter a free-text rack/location.
5. Start scan session.
6. Scan continuously using mobile camera, HID scanner, or manual fallback.
7. Each valid barcode creates one scan transaction.
8. Changing location ends the old scan session and starts a new one.

Checker must not see ERP stock, cycle progress, variance, ratio values, physical totals, or other checker totals.

## Ratio Rules
- Master UOM ratio is stored in PostgreSQL and treated as stable master data.
- Unique key: source_database + item_id + uom_level.
- One scan always equals one unit of the barcode's UOM.
- physical_qty = ratio.
- If barcode is unknown, ambiguous, or ratio is missing, the scan is rejected.

## Audit Rules
Every accepted scan stores the cycle, warehouse, checker, location, alias code, item, UOM, ratio used, normalized physical quantity, and timestamp.
The same barcode may be scanned repeatedly because repeated scans represent repeated physical units.
Camera-side debounce prevents accidental multiple reads of the same code within a short interval; it must not block intentional later rescans.

## Admin Workflow
- Manage users.
- Manage UOM ratios.
- Create cycle and choose ERP database.
- Choose active ERP warehouses.
- Assign checkers per warehouse.
- Start cycle and capture opening snapshot.
- Monitor administrative scan activity.
- Close cycle and capture closing snapshot.
- Review summary by warehouse/item with opening stock, physical scanned stock, variance, net ERP movement indicator, and drill-down scan audit.

## Mobile Requirements
- Checker UI is mobile-first and optimized for one-handed warehouse use.
- Large touch targets and minimal chrome.
- Rear camera preferred.
- Camera scanning requires HTTPS outside localhost/secure development contexts.
- Manual/HID barcode input remains available when camera scanning is unsupported.
