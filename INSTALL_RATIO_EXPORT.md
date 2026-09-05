# Hotfix - Export Excel Master Ratio

Update ini menambahkan tombol **Export Excel** pada halaman **Admin > Ratio**.

## Isi file export

Kolom yang dihasilkan:

- ItemCode
- UOM
- Ratio

Export mengambil seluruh data Master Ratio dan mengurutkannya berdasarkan ItemCode lalu UOM.

## Instalasi

1. Backup project terlebih dahulu.
2. Extract ZIP ini.
3. Copy seluruh isi folder ke root project `opanme` dan overwrite file dengan path yang sama.
4. Jalankan:

```bash
php artisan optimize:clear
```

Tidak ada migration database baru untuk update ini.

## Test

```bash
php artisan test --filter=RatioExportTest
php tests/static/ratio_export_contract.php
```

Lalu login sebagai Admin > Ratio. Di bagian kanan atas akan muncul tombol **Export Excel** di samping **Download Template**.
