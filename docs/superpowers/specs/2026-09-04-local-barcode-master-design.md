# Local Barcode Master Design

## Goal

Stock opname must no longer depend on ERP `IC_Aliases` to identify scanned barcodes. Barcode mapping is maintained in the application database and applies globally to both AS_INGCO and AS_SMI.

## Master Data

### Item Barcode

Unique key: `barcode`.

Fields:
- `item_code`
- `barcode`
- `uom_code`

Rules:
- One barcode may point to exactly one ItemCode + UOM.
- One ItemCode + UOM may have multiple different barcodes.
- Barcode is stored as text so leading zeroes are preserved.
- ItemCode and UOM are normalized to uppercase.

### UOM Ratio

Existing global master remains:
- `item_code`
- `uom_code`
- `ratio`

Unique key: ItemCode + UOM.

## Scan Flow

1. Checker scans barcode.
2. Application looks up `item_barcodes.barcode` locally.
3. If missing, use existing Non-System flow. ERP barcode aliases are not consulted.
4. If found, application has ItemCode + UOM.
5. Resolve ItemCode + UOM against the ERP database selected by the cycle to obtain ItemID, ItemName and UOMLevel.
6. Resolve ItemCode + UOM against local `uom_ratios`.
7. Save scan using the local scanned barcode, ERP ItemID/details, and local ratio.

## Admin

Add Master Barcode page with:
- search, sort and pagination
- manual add/edit/delete
- template download
- Excel/CSV import using `ItemCode | Barcode | UOM`
- XLSX export using the same three columns

## Compatibility

Existing ERP barcode lookup methods remain available for legacy/admin use, but checker scan no longer calls them.
