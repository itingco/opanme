V13 — Perbaikan Route Kamera Checker (route not defined)
=====================================================
1. Extract semua isi ZIP ke C:\xampp82\htdocs\opanme, sejajar artisan.
2. Backup project sebelum perubahan.
3. Jalankan PowerShell:
   cd C:\xampp82\htdocs\opanme
   php FIX_CAMERA_ROUTES_V13.php
   php artisan migrate
   php artisan optimize:clear
   php artisan route:list --name=camera
4. Harus ada route checker.camera-manual.store dan checker.camera-ai.read.
5. Buka ulang /checker/sessions/{session}/scan lalu Ctrl+F5.

Perbaikan ini menambahkan require eksplisit ke routes/web.php, serta file khusus routes/checker_camera_v13.php.
Installer tidak mengganti controller yang sudah ada; hanya menyalin jika file hilang.
Jika validasi route:list gagal, baca pesan error terminal. Jangan menganggap patch berhasil.
Jika route masih tidak terdaftar, periksa apakah Anda menjalankan php pada direktori project yang sama dengan server artisan.
Satu catatan: URL 127.0.0.1:8000 hanya mengacu ke komputer tempat browser dijalankan; pastikan server Laravel yang aktif berasal dari project yang Anda perbarui.
