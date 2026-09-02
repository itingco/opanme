# Non-System Items Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Add audited Non-System physical item handling to scan, admin override, summary, exports, and final PDF.

**Architecture:** Keep ERP rows untouched and store discovered items/scans/overrides in dedicated PostgreSQL tables. Merge the two domains only at the summary/report layer using a shared row shape. Checker scan discovery is a two-step API: detect unknown barcode, then submit Non-System quantity/UOM conversion.

**Tech Stack:** Laravel 10, PHP 8.1+, PostgreSQL, Blade, vanilla JavaScript/Vite, existing custom PDF/XLSX services.

**Spec:** `docs/superpowers/specs/2026-09-02-non-system-items-design.md`

## Global Constraints
- Do not write discovered items into ERP.
- Existing ERP scan/override behavior must remain unchanged.
- Qty defaults to 1, UOM and Smallest UOM default to PCS, ratio defaults to 1.
- Checker may change Qty/UOM; conversion ratio must be >0.
- CLOSED permits admin Non-System override; FINALIZED is permanently read-only.

---

### Task 1: Persistence and quantity conversion
**Files:**
- Create: `database/migrations/2026_09_02_000007_create_discovered_stock_opname_tables.php`
- Create: `app/Models/StockOpnameDiscoveredItem.php`
- Create: `app/Models/DiscoveredScanTransaction.php`
- Create: `app/Models/StockOpnameDiscoveredOverride.php`
- Create: `app/Services/NonSystemQtyService.php`
- Test: `tests/static/non_system_qty_test.php`

**Interfaces:**
- Produces `NonSystemQtyService::toSmallest($qty, $ratio): string`.
- Produces Eloquent models used by scan/admin/summary tasks.

- [x] Write a failing pure-PHP test that requires positive ratio and computes 2 KRTN x 20 = 40 smallest units.
- [x] Run `php tests/static/non_system_qty_test.php` and verify failure because the service does not exist.
- [x] Implement migration, models, and conversion service.
- [x] Run the pure-PHP test and PHP syntax checks.

### Task 2: Checker unknown-barcode scan flow
**Files:**
- Modify: `app/Services/ScanService.php`
- Modify: `app/Http/Controllers/Checker/ScanController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/checker/scan.blade.php`
- Modify: `resources/js/app.js`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/NonSystemScanTest.php`
- Test: `tests/static/non_system_contract.php`

**Interfaces:**
- `ScanService::scan()` returns `requires_non_system=true` instead of an error for unknown ERP barcode.
- `ScanService::storeNonSystemScan()` stores an OPEN-cycle discovered scan.

- [x] Add failing contract/feature tests for unknown barcode detection, first discovery, repeated barcode reuse, conversion, and privacy.
- [x] Run static contract and verify expected failure.
- [x] Implement backend endpoint and service behavior.
- [x] Implement scanner modal/form with editable Qty/UOM/smallest UOM/ratio and first-time name.
- [x] Build frontend assets and rerun static checks.

### Task 3: Admin manual Non-System and override
**Files:**
- Modify: `app/Http/Controllers/Admin/CycleController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/cycles/summary.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/NonSystemOverrideTest.php`

**Interfaces:**
- Admin can create/reuse discovered item and upsert discovered override while CLOSED.
- Admin can edit/delete discovered override while CLOSED; FINALIZED rejects changes.

- [x] Add failing tests for CLOSED create/update/delete and FINALIZED rejection.
- [x] Implement controller methods and routes.
- [x] Add summary action/modal and row-level discovered override editor.
- [x] Run syntax/static verification.

### Task 4: Unified summary, details, Excel and PDF
**Files:**
- Modify: `app/Services/CycleSummaryService.php`
- Modify: `app/Http/Controllers/Admin/CycleController.php`
- Modify: `app/Services/CycleSummaryExcelService.php`
- Modify: `app/Services/CycleFinalPdfService.php`
- Modify: `resources/views/admin/cycles/summary.blade.php`
- Modify: `resources/views/admin/cycles/scan-detail.blade.php`
- Test: `tests/Unit/NonSystemSummaryArithmeticTest.php`
- Test: `tests/static/non_system_contract.php`

**Interfaces:**
- Summary rows include `row_type`, `row_key`, and a consistent numeric/audit shape.
- Non-System row values: Opening=0, Closing=0, Movement=0, Final=override-or-scan, Variance=Final.

- [x] Add failing contract/arithmetic assertions for Non-System summary shape.
- [x] Merge normal and discovered results into one query/collection-compatible summary path.
- [x] Update scan detail routing/view for discovered scan history.
- [x] Add Row Type to Excel and Non-System marker to portrait Final PDF.
- [x] Run all available static/pure-PHP verification and render a PDF smoke test.

### Task 5: Final verification and packaging
**Files:**
- Verify all changed PHP/Blade/JS/CSS/migration files.
- Package updated project ZIP.

- [x] Run `php -l` over application/migration/test PHP files.
- [x] Run `php tests/static/non_system_qty_test.php` and `php tests/static/non_system_contract.php`.
- [x] Run existing static tests.
- [x] Run frontend build if platform dependencies allow; otherwise rebuild after clean npm install.
- [x] Validate ZIP integrity and report any environment-limited PHPUnit coverage explicitly.


## Verification note
PHPUnit feature tests are included but could not be executed in this sandbox because the bundled PHP runtime lacks required extensions (`dom`, `mbstring`, `xmlwriter`, and PDO database drivers). Static contract tests, pure-PHP quantity tests, PHP syntax checks, JavaScript syntax checks, PDF smoke rendering, and XLSX smoke generation are used as executable verification here.
