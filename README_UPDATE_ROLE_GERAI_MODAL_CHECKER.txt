UPDATE 2026-09-24
ROLE GERAI + MULTI WAREHOUSE ASSIGNMENT + MODAL CHECKER GUDANG

PERUBAHAN UTAMA
1. Role akses eksplisit:
   - ADMIN_GERAI
   - CHECKER_GERAI
   - ADMIN_GUDANG
   - CHECKER_GUDANG
   Role ADMIN tetap sebagai Super Admin.
   Role GERAI lama tetap dapat login sebagai Checker Gerai untuk backward compatibility.

2. Assignment User Gerai sekarang multi database + multi gudang.
   - Satu user ADMIN_GERAI / CHECKER_GERAI dapat dipilihkan beberapa gudang.
   - Assignment boleh sekaligus dari AS_INGCO dan AS_SMI.
   - User Management menampilkan checkbox gudang per database dengan pencarian.
   - Binding lama single gudang otomatis dibackfill ke tabel assignment baru saat migrate.

3. Flow Gerai dipisah:
   - ADMIN_GERAI membuat cycle, memilih gudang dari assignment miliknya, lalu assign CHECKER_GERAI.
   - CHECKER_GERAI hanya melihat cycle yang ditugaskan kepadanya.
   - Checker hanya dapat membuka cycle jika gudang cycle masih termasuk assignment user tersebut.

4. Checker Gudang kembali menjadi save per item melalui modal:
   - Klik KODE BARANG -> modal terbuka.
   - Isi Qty Fisik Total dan Komentar Checker.
   - Simpan Draft Item satu per satu.
   - Draft dapat dibuka/edit kembali selama belum final.
   - Setelah semua item memiliki Qty, klik Finalisasi Semua Item.
   - Setelah finalisasi, Qty dan komentar terkunci; backend juga menolak edit.

INSTALASI
1. Copy seluruh isi folder update ke root project, contoh:
   C:\xampp82\htdocs\opname
   lalu pilih Replace/Overwrite.

2. Jalankan:
   cd C:\xampp82\htdocs\opname
   composer dump-autoload
   php artisan optimize:clear
   php artisan migrate
   php artisan route:list

3. Browser: Ctrl + F5

PENTING
- Ada migration baru: 2026_09_24_000016_create_user_warehouse_assignments.php
- Tidak perlu npm, Vite, npm install, atau npm run build.
- Setelah migrate, buka ADMIN > User & Akses untuk mengubah/membuat user ADMIN_GERAI dan CHECKER_GERAI dan pilih assignment gudangnya.
- Role GERAI lama tidak dihapus agar akun lama tidak langsung putus. Sebaiknya secara bertahap ubah ke CHECKER_GERAI dari User Management.
