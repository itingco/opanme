FIX SEARCH GUDANG - 2026-09-23

Masalah:
- Counter hasil search sudah berubah (mis. 1 dari 43), tetapi semua baris gudang tetap tampil.
- Penyebab: class .ws-warehouse-check memakai display:grid sehingga atribut hidden tidak cukup menjadi aturan visual yang konsisten.

Perbaikan:
- JavaScript menambah class .ws-filter-hidden pada baris yang tidak cocok.
- CSS memaksa .ws-filter-hidden { display:none !important; }.
- Scroll list database otomatis kembali ke atas setiap pencarian berubah.
- Checkbox "Pilih tampil" tetap hanya bekerja pada hasil yang terlihat.

File berubah:
1. resources/views/warehouse_sampling/admin/index.blade.php
2. public/assets/css/warehouse-sampling.css

Sesudah overwrite:
php artisan view:clear
php artisan optimize:clear

Kemudian Ctrl+F5 di browser.
