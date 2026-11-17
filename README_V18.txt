PATCH V18 — Bulk Override berdasarkan master IC_Items database cycle

Perubahan:
- Validasi ItemCode dari IC_Items AS_INGCO atau AS_SMI, bukan snapshot warehouse.
- Warehouse tetap dipilih sebagai tujuan override, bukan syarat item eksis.
- Menambahkan metadata item agar item yang tidak ada di snapshot warehouse tetap terlihat di Summary.
- Opening ERP tetap 0 jika belum ada snapshot; tidak membuat stok awal palsu.
- Maksimum 2.000 item per batch, format lama dan QR KODE~QTY.
- Seluruh baris divalidasi sebelum transaksi; upsert dalam 1 transaksi.
- Cycle OPEN/CLOSED diizinkan; FINALIZED tidak.

INSTALL:
1. Backup aplikasi dan database. Jalankan dahulu di staging.
2. Extract ZIP di root Laravel, sejajar artisan. Timpa hanya file yang ada di ZIP.
3. php INSTALL_V18.php
4. php artisan migrate
5. php artisan optimize:clear
6. Test 00621 dengan warehouse yang sebelumnya gagal; lihat Summary dan pastikan item muncul.

Jika error ERP connection, baca storage/logs/laravel.log. SQL Server harus dapat diakses oleh app.
PENTING: Migration baru dibutuhkan. Jangan lupa migrate sebelum mencoba upload.
