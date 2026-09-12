OPNAME + LABEL - LOCAL TEST
===========================

Project ini adalah source Opname branch main dengan modul Label ditambahkan secara terisolasi.
Logic existing Stock Opname tidak diganti.

Cara pakai di Windows / XAMPP:
1. Extract folder "opname" ke C:\xampp82\htdocs\opname (atau folder lain).
2. Copy .env dari instalasi Opname lokal Anda yang sudah berjalan. Jika instalasi baru, copy .env.example menjadi .env lalu isi database.
3. Gunakan PHP 8.2 dan pastikan extension PostgreSQL serta SQL Server (sqlsrv / pdo_sqlsrv) yang dipakai Opname sudah aktif.
4. Jika APP_KEY kosong: php artisan key:generate
5. Jalankan: php artisan optimize:clear
6. Jalankan aplikasi seperti biasa.
7. Login ke Opname lalu buka /label.

Modul Label membaca IC_Items melalui koneksi existing "erp_smi".
Vendor dan hasil Vite build disertakan. node_modules sengaja tidak disertakan.
