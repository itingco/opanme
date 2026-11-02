FIX 403 - Warehouse Aktif pada Buat Cycle untuk ADMIN_GERAI

Penyebab:
- Halaman Cycle bisa dibuka berdasarkan permission menu.
- AJAX daftar warehouse masih memanggil /admin/erp/warehouses di grup route role ADMIN, sehingga ADMIN_GERAI mendapat HTTP 403.

Perbaikan:
- ADMIN_GERAI menggunakan route gerai.admin.warehouses (sudah ada) yang hanya mengembalikan warehouse assignment user.
- SUPERADMIN tetap menggunakan admin.erp.warehouses.

Instalasi:
1. Extract dan timpa file sesuai path dari root aplikasi.
2. php artisan optimize:clear
3. Reload /admin/cycles/create

Jika warehouse kosong, pastikan assignment gudang user ADMIN_GERAI sudah diisi di User & Akses.
PENTING: Ini memperbaiki endpoint tampilan pemilihan warehouse. Validasi sisi server pada penyimpanan cycle sebaiknya tetap memastikan warehouse_ids milik user, agar tidak bisa melewati pembatasan melalui POST manual.
