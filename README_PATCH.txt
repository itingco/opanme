PATCH: Perbaikan error "Gagal" saat mengambil Warehouse aktif di Buat Cycle

File berubah:
- app/Http/Controllers/Admin/ErpLookupController.php
- resources/views/admin/cycles/create.blade.php

INSTALASI:
1. Backup kedua file lama dan extract ZIP ke root Laravel.
2. php artisan optimize:clear
3. Login dan buka /admin/cycles/create, pilih AS_INGCO atau AS_SMI.
4. Jika terjadi error, salin Kode error ERP-WH-XXXXXXXX yang tampil dan cari di storage/logs/laravel.log.

PENTING: Patch ini memperbaiki penanganan dan diagnosis error, bukan menjamin koneksi SQL Server yang gagal otomatis dapat terhubung. Bila database masih gagal, pastikan environment koneksi, driver sqlsrv, izin SELECT, dan query IC_Warehouses valid.
