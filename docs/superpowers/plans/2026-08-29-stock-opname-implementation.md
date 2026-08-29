# Stock Opname Web Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a runnable Laravel 10 stock-opname web project with PostgreSQL persistence, SQL Server ERP read-only integration, mobile camera scanning, role-based workflows, stock snapshots, and variance summary.

**Architecture:** Laravel owns authentication, workflows, validation, persistence, and Blade rendering. PostgreSQL stores all application/master/transaction data. Two configured SQL Server connections provide ERP catalogs and stock snapshots through focused services.

**Tech Stack:** PHP ^8.1 (PHP 8.2 recommended), Laravel 10, PostgreSQL, SQL Server PDO driver, Blade, vanilla CSS, Vite, @zxing/browser.

**Spec:** `docs/superpowers/specs/2026-08-29-stock-opname-design.md`

## Global Constraints
- One PostgreSQL application database.
- ERP connections are read-only.
- Roles are ADMIN and CHECKER only.
- Cycle format is SO-YYYYMMDD-XXX.
- Location/rack is free text.
- One accepted scan creates one row and normalizes quantity via master UOM ratio.
- Checker UI is blind to stock, progress, ratio, totals, and variance.
- Mobile-first camera scanning with manual/HID fallback.

---

### Task 1: Application skeleton and schema
**Files:** composer.json, bootstrap/app.php, config/database.php, migrations, models, seeders, tests.
**Interfaces:** Produces Eloquent models and database constraints used by all later tasks.
- [ ] Write model/schema behavior tests.
- [ ] Verify tests fail before migrations/models exist.
- [ ] Add Laravel skeleton, migrations, models, seed data.
- [ ] Verify PHP syntax and tests once dependencies are installed.

### Task 2: ERP read services
**Files:** app/Services/ErpConnectionResolver.php, ErpCatalogService.php, ErpStockService.php, service tests.
**Interfaces:** Produces warehouse listing, barcode lookup, opening/closing stock snapshot methods.
- [ ] Write tests for connection mapping, barcode ambiguity, and snapshot normalization.
- [ ] Add services using configured sqlsrv connections.
- [ ] Verify syntax and service contracts.

### Task 3: Authentication and authorization
**Files:** login controller/views, role middleware, bootstrap middleware aliases, feature tests.
**Interfaces:** Produces authenticated ADMIN/CHECKER route protection.
- [ ] Write access-control tests.
- [ ] Implement session login/logout and role middleware.
- [ ] Verify route protection.

### Task 4: Admin master data
**Files:** user controller/views, ratio controller/views, routes, tests.
**Interfaces:** Produces checker accounts and stable ratio master records.
- [ ] Write validation/unique-key tests.
- [ ] Implement CRUD needed for users and UOM ratios.
- [ ] Verify server-side validation.

### Task 5: Cycle creation and assignments
**Files:** cycle controller, assignment controller, admin views, routes, tests.
**Interfaces:** Produces DRAFT cycles, selected warehouses, checker assignments, cycle numbering.
- [ ] Write cycle-number and assignment tests.
- [ ] Implement warehouse retrieval, cycle creation, assignment matrix.
- [ ] Verify only active ERP warehouses can be selected.

### Task 6: Cycle start/close snapshots and summary
**Files:** CycleSnapshotService.php, CycleSummaryService.php, controllers/views/tests.
**Interfaces:** Produces opening_system_qty, closing_system_qty, movement_qty, physical_qty, variance.
- [ ] Write summary arithmetic tests.
- [ ] Implement opening snapshot capture on start and closing snapshot capture on close.
- [ ] Implement union summary over snapshots and scans.
- [ ] Verify movement flag means non-zero net ERP stock change.

### Task 7: Checker scan workflow
**Files:** checker controller, ScanService.php, scanner Blade/JS/CSS, tests.
**Interfaces:** Produces scan sessions and one-row-per-scan audit records.
- [ ] Write tests for assignment, open cycle, location, barcode, ratio, and repeated scans.
- [ ] Implement warehouse/location/session flow.
- [ ] Implement atomic scan persistence.
- [ ] Add camera scanner with debounce and manual/HID fallback.
- [ ] Verify checker response never exposes stock/ratio/variance values.

### Task 8: Packaging and verification
**Files:** README.md, .env.example, project archive.
**Interfaces:** Produces installation/testing instructions and downloadable source archive.
- [ ] Run PHP lint over all PHP files.
- [ ] Run static structure checks for routes/migrations/views.
- [ ] Attempt framework tests; document dependency/network blocker if vendor is unavailable.
- [ ] Zip project without vendor/node_modules.
