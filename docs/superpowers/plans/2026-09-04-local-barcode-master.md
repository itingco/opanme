# Local Barcode Master Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a global local barcode master and make stock-opname scanning use it instead of ERP `IC_Aliases`.

**Architecture:** PostgreSQL owns `Barcode -> ItemCode + UOM` and `ItemCode + UOM -> Ratio`. The selected ERP database is only used to resolve ItemCode + UOM to ERP ItemID/ItemName/UOMLevel and for the existing stock snapshot processes.

**Tech Stack:** Laravel 10, PHP 8.1/8.2, PostgreSQL, Microsoft SQL Server, Blade, XLSX XML/ZIP writer without a new Composer dependency.

**Spec:** `docs/superpowers/specs/2026-09-04-local-barcode-master-design.md`

## Global Constraints

- One barcode maps to exactly one ItemCode + one UOM.
- One ItemCode + UOM may have many barcodes.
- Barcode master is global across AS_INGCO and AS_SMI.
- Ratio master stays global by ItemCode + UOM.
- Scanner must not depend on ERP `IC_Aliases`.

---

### Task 1: Local barcode persistence and admin maintenance

**Files:** migration, `ItemBarcode` model, controller, Blade page, routes, layout navigation.

- [x] Add `item_barcodes` table with unique `barcode` and ItemCode/UOM index.
- [x] Add CRUD/list admin page and Barcode navigation.
- [x] Add import template and import service.
- [x] Add XLSX export service.

### Task 2: Scan resolution

**Files:** `ScanService.php`, `ErpCatalogService.php`.

- [x] Resolve scanned barcode from local `item_barcodes`.
- [x] Add ERP resolver for ItemCode + UOM.
- [x] Resolve local ratio by ItemCode + UOM.
- [x] Make missing local barcode enter the existing Non-System flow without querying ERP barcode aliases.

### Task 3: Regression coverage

**Files:** feature tests and static contract test.

- [x] Cover unique barcode master behavior.
- [x] Cover CSV import.
- [x] Cover local barcode scan and no `findBarcode` dependency.
- [x] Update ratio/privacy scan tests for the new lookup flow.
