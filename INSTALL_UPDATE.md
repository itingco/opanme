# Update Master UOM Ratio Global

Perubahan ini membuat master ratio berlaku global untuk AS_INGCO dan AS_SMI.

## Konsep baru

ERP tetap menentukan:

`Barcode -> ItemCode -> UOM`

Database aplikasi hanya menyimpan:

`ItemCode + UOM -> Ratio ke smallest UOM`

Contoh:

| ItemCode | UOM | Ratio |
|---|---|---:|
| ITEMA | PCS | 1 |
| ITEMA | KTK | 20 |
| ITEMA | KRTN | 120 |

Artinya 1 KTK ITEMA = 20 PCS dan 1 KRTN ITEMA = 120 PCS.

## Cara pasang ke project existing

1. Backup project dan database PostgreSQL terlebih dahulu.
2. Extract isi ZIP update ini ke root project `opanme` dan overwrite file yang sama.
3. Jalankan:

```bash
php artisan optimize:clear
php artisan migrate
```

4. Jalankan test:

```bash
php artisan test --filter=GlobalUomRatioTest
php tests/static/global_uom_ratio_contract.php
```

5. Login Admin -> Ratio -> Download Template.
6. Isi Excel dengan kolom `ItemCode`, `UOM`, `Ratio`.
7. Import file dan lakukan test scan barcode PCS/KTK/KRTN.

## Catatan migration

Migration baru mempertahankan ratio lama dengan cara menggabungkan data berdasarkan `ItemCode + UOM`.
Jika sebelumnya ada data ItemCode + UOM yang sama di AS_INGCO dan AS_SMI, record yang paling terakhir di-update akan dipakai.

Rollback otomatis migration ini sengaja tidak disediakan karena field lama (`source_database`, `item_id`, `uom_level`) tidak dapat direkonstruksi dengan aman setelah penyederhanaan. Karena itu backup database sebelum migrate wajib dilakukan.

## Format Excel baru

```text
ItemCode | UOM | Ratio
ITEMA    | PCS | 1
ITEMA    | KTK | 20
ITEMA    | KRTN| 120
ITEMB    | PCS | 1
ITEMB    | BOX | 12
```

Tidak perlu lagi kolom Database atau Barcode.
