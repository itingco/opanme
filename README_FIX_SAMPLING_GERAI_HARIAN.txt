FIX SAMPLING GERAI HARIAN - 2026-09-30

Masalah:
Setelah role dipisah menjadi ADMIN_GERAI / CHECKER_GERAI / ADMIN_GUDANG / CHECKER_GUDANG,
Laporan Sampling Gerai Harian masih berada di route ADMIN lama sehingga menu dan halaman tidak muncul untuk ADMIN_GERAI.

Perbaikan:
1. Tambah menu "Sampling Gerai Harian" untuk ADMIN_GERAI.
2. Tambah route:
   GET /gerai-admin/sampling-harian
   GET /gerai-admin/sampling-harian/export-pdf
3. ADMIN_GERAI hanya melihat hasil sampling dari gudang yang di-assign kepadanya.
4. Filter Database/Gudang juga hanya menampilkan gudang assignment ADMIN_GERAI.
5. Checker pada filter dibatasi ke Checker Gerai yang punya assignment gudang terkait.
6. Super Admin tetap menggunakan route lama dan bisa melihat semua data.
7. Export PDF mengikuti scope/filter yang sama.

File berubah:
- app/Http/Controllers/Admin/SamplingReportController.php
- app/Services/SampleReportService.php
- resources/views/admin/sampling/index.blade.php
- resources/views/layouts/app.blade.php
- routes/web.php

Cara pasang:
1. Copy semua isi folder update ke root C:\xampp82\htdocs\opname dan Replace.
2. Jalankan:
   composer dump-autoload
   php artisan route:clear
   php artisan view:clear
   php artisan optimize:clear
   php artisan route:list --path=gerai-admin
3. Login sebagai ADMIN_GERAI dan refresh Ctrl + F5.

Tidak ada migration database baru.
Tidak perlu npm / vite.
