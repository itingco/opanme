OPANME - FITUR GANTI PASSWORD USER

Cara pakai:
1. Backup project local Anda.
2. Extract ZIP ini ke root project opanme.
3. Izinkan overwrite/replace file yang sudah ada.
4. File baru PasswordController.php dan change-password.blade.php akan ikut dibuat.
5. Jalankan:
   php artisan optimize:clear

File dalam paket:
- app/Http/Controllers/Auth/PasswordController.php
- resources/views/auth/change-password.blade.php
- resources/views/layouts/app.blade.php
- routes/web.php

Tidak ada perubahan database / migration.
