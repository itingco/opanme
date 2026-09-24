UPDATE SAMPLING GUDANG - ITEM GABUNG + VALIDASI PROPORSIONAL
Tanggal: 23 September 2026

TUJUAN
1. Pemilihan item tidak lagi dibedakan berdasarkan gudang.
2. ItemCode + UOM yang sama dari semua database/gudang periode hanya tampil SATU KALI.
3. Total Stok Gabungan = jumlah stok item tersebut dari seluruh gudang yang dipilih.
4. Saat item disimpan, snapshot stok per database/gudang tetap disimpan di belakang.
5. Checker mengisi satu Qty Fisik Total gabungan.
6. Saat Admin melakukan validasi, Qty Fisik Total otomatis dibagi PROPORSIONAL ke tiap gudang berdasarkan stok sistem masing-masing gudang.
7. Pembulatan 4 desimal; gudang terakhir menerima penyesuaian pembulatan agar total distribusi = Qty Fisik Checker.

CONTOH
G-ACEK = 10
G-TOKO = 20
G-HO   = 5
Total sistem = 35

Pada daftar pemilihan hanya tampil satu baris:
ITEM A | Total Stok Gabungan 35

Jika checker mendapatkan fisik total 33, validasi otomatis menjadi kira-kira:
G-ACEK  10/35 x 33 = 9.4286
G-TOKO  20/35 x 33 = 18.8571
G-HO     5/35 x 33 = 4.7143
Total distribusi = 33.0000

FILE DALAM UPDATE
- app/Http/Controllers/Admin/WarehouseSamplingController.php
- app/Services/WarehouseSamplingMultiStockService.php
- resources/views/warehouse_sampling/admin/show.blade.php

CARA INSTALL
Copy isi folder update ke root project:
C:\xampp82\htdocs\opname
lalu Replace/Overwrite.

Jalankan:
composer dump-autoload
php artisan view:clear
php artisan optimize:clear

Lalu refresh browser dengan Ctrl + F5.

Tidak ada migration baru.
Tidak memerlukan npm / Vite.
