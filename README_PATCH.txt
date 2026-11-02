PATCH CYCLE V3 - 02 NOVEMBER 2026
=================================
ISINYA:
- app/Http/Controllers/Admin/CycleListController.php (baru)
- resources/views/admin/cycles/index.blade.php (replace)
- resources/views/layouts/app.blade.php (replace; berbasis FIX LOGIN V2)
- INSTALL_PATCH.php (pendaftaran route tanpa mengganti route lain)

PENTING:
Patch ini melanjutkan patch 02 Nov dan Fix Login V2 yang sudah dipasang.
File asli app/Http/Controllers/Admin/MenuPermissionController.php,
resources/views/admin/menu-permissions.blade.php, dan
resources/views/admin/partials/table-pagination.blade.php harus tetap ada (sudah ada dalam repo).

INSTALASI WINDOWS / XAMPP:
1. BACKUP project dahulu.
2. Extract seluruh isi ZIP ke C:\xampp82\htdocs\opname dan pilih overwrite.
3. Buka CMD/PowerShell:
   cd C:\xampp82\htdocs\opname
   php INSTALL_PATCH.php
   php artisan optimize:clear
   php artisan view:cache
   php artisan route:list
4. Akses /admin/cycles dan menu "Setup Role & Hak Akses" di sidebar Superadmin.

PERHATIAN:
- INSTALL_PATCH.php perlu dijalankan sekali; hanya menambal route /admin/cycles dan
  menambah route /admin/menu-permissions di routes/web.php. Script membuat backup routes/web.php.
- Tidak perlu migration baru bila tabel role_menu_permissions dari migration sebelumnya telah tersedia.
- Jika form simpan hak akses mengembalikan 503, jalankan php artisan migrate setelah backup DB.
- Pagination tampil ketika data melebihi jumlah per halaman; untuk dua data, hanya tampil ringkasan jumlah.
- Jangan menghapus vendor / .env / database.
