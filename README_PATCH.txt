OPANME - Modul Hak Akses Menu per Role
======================================
Patch ini dibuat berdasarkan itingco/opanme (Laravel 10) dan patch sebelumnya.

1. Backup file dan database terlebih dahulu.
2. Copy folder app/, config/, routes/, resources/, database/ ke root Laravel (overwrite).
3. Jalankan: php artisan migrate
4. Jalankan: php artisan optimize:clear
5. Login sebagai ADMIN -> menu "Hak Akses Menu".
6. Centang hak akses setiap role, kemudian klik Simpan Hak Akses.

Catatan:
- Semua endpoint dengan route name dalam satu modul mengikuti hak akses modul tersebut.
- ADMIN selalu bisa mengakses halaman Hak Akses Menu (tidak bisa dimatikan via matriks).
- Izin awal mengikuti role lama, setelah disimpan bisa berubah.
- Ganti Password, login/logout dan route yang bukan modul daftar ini tetap diatur oleh middleware lama.
- Jika memberi role lintas departemen, pastikan data/scope gudang tetap dilindungi oleh controller masing-masing. Role permission tidak mematikan pemeriksaan ownership atau assignment.
- Tanpa migration, default role lama dipakai. Setelah migration, pengaturan tersimpan dalam role_menu_permissions.
- Perubahan ini untuk per-menu, bukan CRUD granular.
- routes/warehouse_sampling.php harus memang sudah diload oleh project (seperti versi repository ini).
