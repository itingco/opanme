# Non-System Items Design

## Goal
Allow stock-opname cycles to record physical items whose barcode/item does not exist in ERP, from checker scans or admin manual override, without polluting ERP master data.

## Approved behavior
- A barcode not found in ERP is no longer rejected outright.
- Checker receives a Non-System form with barcode prefilled.
- First discovery requires item name, quantity, UOM, smallest UOM, and conversion ratio when input UOM differs from smallest UOM.
- Defaults: Qty `1`, UOM `PCS`, Smallest UOM `PCS`, ratio `1`.
- Checker may change Qty and UOM.
- Physical smallest quantity = Qty x conversion ratio.
- The same barcode discovered again in the same cycle is recognized and its name/default conversion is reused; checker still confirms Qty/UOM for each scan.
- Admin can add a Non-System item during CLOSED review using manual/override entry with mandatory comment.
- Non-System items have ERP Opening `0`, Closing `0`, Movement `0`, and positive variance equal to Final Physical unless final physical is zero.
- CLOSED allows discovered-item override; FINALIZED makes discovered scans/overrides read-only like normal items.
- Summary, Excel export, scan detail, and Final PDF include Non-System items and clearly label them.
- Existing ERP item scan/override behavior remains unchanged.

## Data model
Use isolated local tables so existing ERP-oriented tables do not need nullable item IDs or altered uniqueness rules.

### `stock_opname_discovered_items`
Cycle-level local catalog for a barcode not found in ERP.
- `id`
- `cycle_id`
- `alias_code`
- `item_name`
- `default_uom_code`
- `smallest_uom_code`
- `default_ratio_to_smallest`
- `created_by`
- timestamps
- unique `(cycle_id, alias_code)`

### `discovered_scan_transactions`
Immutable checker scan events for discovered items.
- cycle, discovered item, session, warehouse, checker, location
- input qty/UOM, smallest UOM, ratio used, calculated smallest physical qty
- scanned_at

### `stock_opname_discovered_overrides`
Admin correction/manual final quantity per cycle + warehouse + discovered item.
- input qty/UOM + smallest UOM + ratio used
- calculated override qty in smallest UOM
- comment, updated_by, timestamps
- unique `(cycle_id, warehouse_id, discovered_item_id)`

## Scan flow
1. Validate active checker session and assignment.
2. Lookup barcode in ERP.
3. If ERP item exists, use existing normal scan flow unchanged.
4. If ERP item is absent, lookup local discovered item by cycle + barcode.
5. Return JSON `requires_non_system=true` with barcode and optional known-item defaults.
6. UI opens modal/form. First discovery requires name; known discovery reuses name.
7. Checker submits quantity/UOM/smallest UOM/ratio.
8. Backend rechecks cycle OPEN + assignment under DB transaction, creates/reuses discovered item, stores discovered scan transaction.

## Admin flow
On CLOSED summary, Admin gets `+ Barang Non-System` action. Form requires warehouse, barcode/code, name, Qty Override, UOM, Smallest UOM, conversion ratio, and comment. This creates/reuses the discovered catalog row and upserts a discovered override. Admin can edit/delete that override while CLOSED. FINALIZED rejects all changes.

## Summary model
`CycleSummaryService` returns a union-compatible result shape for normal and discovered rows. Each row exposes:
- `row_type`: `ERP` or `NON_SYSTEM`
- `row_key`: stable id string
- warehouse/item labels
- opening/closing/movement
- scan qty/count
- override/final/variance
- override audit fields

For Non-System rows: opening=0, closing=0, movement=0.

## Reporting
- Web summary shows a `NON-SYSTEM` badge.
- Excel adds row type and preserves audit fields.
- Final PDF portrait compact shows `NON-SYSTEM` in item identity/detail while keeping existing checker/finalizer signature pages.

## Safety and audit
- No data is inserted into ERP tables.
- All calculated quantities are stored in smallest UOM.
- Ratio must be >0; qty must be >=0; names/UOMs are mandatory.
- Barcode uniqueness is per cycle to prevent duplicate local identities.
- Backend status checks are authoritative; UI state is not trusted.
