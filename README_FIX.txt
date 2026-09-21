FIX AUTH ROUTE - OPNAME / OPANME
Tanggal: 21-09-2026

Masalah yang ditemukan:
- routes/web.php lokal tertimpa route dari project/modul lain.
- File tersebut memanggil App\Http\Controllers\AuthController, sedangkan project opanme memakai App\Http\Controllers\Auth\LoginController.
- Route lama juga memanggil AdminUserController dan SupervisorOtpController yang tidak ada pada project opanme.
- Login view project ini menggunakan route login.store, bukan login.submit.

File yang diperbaiki:
1. routes/web.php
   - dikembalikan ke struktur route asli project opanme.
   - login memakai Auth\LoginController.
   - password route dikembalikan.
   - semua route Admin/Checker/Gerai/Label existing dipertahankan.
   - root / ditambah redirect untuk ADMIN_GUDANG dan CHECKER_GUDANG.

2. routes/warehouse_sampling.php
   - duplicate route '/' dihapus.
   - hanya berisi route modul Sampling Gudang.

3. app/Providers/RouteServiceProvider.php
   - tetap mendaftarkan web.php dan warehouse_sampling.php tanpa duplicate root route.

Cara pasang:
- Copy folder routes dan app dari ZIP ini ke root project C:\xampp82\htdocs\opname
- Replace/overwrite file yang sama.

Setelah copy jalankan:
composer dump-autoload
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:list

Jika php artisan route:list tampil daftar route, lanjut:
npm.cmd run build
php artisan serve

Hasil pengujian paket ini:
- PHP syntax check: OK
- php artisan route:list: OK, 75 routes terdaftar
- login route: Auth\LoginController@create/store
- warehouse sampling routes: terdaftar

Catatan:
Di environment pemeriksaan Linux, php artisan optimize:clear menampilkan "DOMDocument not found" karena extension DOM pada PHP environment pemeriksaan tidak aktif. Hal ini terpisah dari masalah AuthController. php artisan route:list tetap berhasil.
