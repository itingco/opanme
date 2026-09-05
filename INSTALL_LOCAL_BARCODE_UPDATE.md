# Install Local Barcode Master Update

Update ini dibuat untuk project `itingco/opanme` yang sudah memakai Global UOM Ratio.

## Konsep akhir

Master Barcode:

```text
ItemCode | Barcode | UOM
ITEMA    | 899001  | PCS
ITEMA    | 899002  | KTK
ITEMA    | 899003  | KRTN
```

Master Ratio tetap:

```text
ItemCode | UOM  | Ratio
ITEMA    | PCS  | 1
ITEMA    | KTK  | 20
ITEMA    | KRTN | 120
```

Scan `899002` -> local barcode memberi `ITEMA / KTK` -> local ratio memberi `20` -> ERP hanya mencari detail `ITEMA / KTK` pada database cycle.

## Instalasi

1. Backup database PostgreSQL.
2. Extract ZIP ini ke root project `opanme`, overwrite file yang sama.
3. Jalankan:

```bash
php artisan optimize:clear
php artisan migrate
```

4. Cek migration:

```bash
php artisan migrate:status
```

Pastikan migration berikut berstatus `Ran`:

```text
2026_09_04_000009_repair_global_uom_ratio_schema
2026_09_04_000010_create_item_barcodes_table
```

`000009` disertakan kembali supaya paket ini aman bila hotfix ratio sebelumnya belum sempat dicopy. Jika migration tersebut sudah pernah dijalankan, Laravel tidak akan menjalankannya lagi.

## Test

```bash
php artisan test --filter=LocalBarcodeMasterTest
php artisan test --filter=LocalBarcodeScanTest
php artisan test --filter=GlobalUomRatioTest
php artisan test --filter=ScanPrivacyTest
php artisan test --filter=PrefixedXlsxReaderTest
php tests/static/local_barcode_contract.php
```

## Cara pakai

Admin -> **Barcode**.

- Download Template: format `ItemCode | Barcode | UOM`
- Import Excel: insert/update master barcode
- Export Excel: download seluruh master barcode dalam format yang dapat di-import kembali
- Tambah Barcode: input manual

## Aturan

- Satu barcode hanya memiliki satu mapping ItemCode + UOM.
- ItemCode + UOM boleh memiliki banyak barcode berbeda.
- Barcode pada Excel sebaiknya format **Text** untuk mempertahankan angka 0 di depan.
- Database tidak perlu diisi karena master berlaku untuk AS_INGCO dan AS_SMI.

## Perubahan scan

Barcode ERP di `IC_Aliases` tidak lagi dibutuhkan untuk proses scan checker.

Jika barcode tidak ada di Master Barcode lokal, aplikasi masuk ke flow **Non-System** yang sudah ada.

Jika barcode ada di Master Barcode tetapi ItemCode/UOM tidak ada pada ERP database cycle, scan ditolak dengan pesan yang jelas agar master diperbaiki.
