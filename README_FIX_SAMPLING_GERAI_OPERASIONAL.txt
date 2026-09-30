FIX MODUL SAMPLING OPNAME HARIAN GERAI
Tanggal: 2026-09-30

Tujuan:
- CHECKER_GERAI / legacy GERAI dapat memulai sampling harian sendiri.
- Tidak harus menunggu Admin Gerai membuat tugas.
- User hanya dapat memilih gudang yang memang di-assign kepadanya, termasuk lintas AS_INGCO / AS_SMI.
- Setelah mulai, user scan barcode.
- Sistem membaca Qty stok ERP gudang tersebut.
- User pilih Stok Cocok atau Ada Selisih.
- Bila selisih, user memasukkan Qty fisik sebenarnya.
- Hasil tersimpan ke sample_checks dan tampil pada laporan Sampling Gerai Harian.

MENU BARU:
Sampling Opname Harian

ROUTE:
GET  /gerai-checker/sampling-harian
POST /gerai-checker/sampling-harian/start

LANGKAH UPDATE:
1. Copy seluruh isi folder update ke root project C:\xampp82\htdocs\opname
2. Overwrite file yang sama.
3. Jalankan:
   composer dump-autoload
   php artisan route:clear
   php artisan view:clear
   php artisan optimize:clear
4. Cek route:
   php artisan route:list --path=gerai-checker
5. Login menggunakan user role CHECKER_GERAI atau legacy GERAI.
6. Refresh browser Ctrl + F5.

TIDAK ADA MIGRATION BARU.
TIDAK PERLU NPM / VITE.

CATATAN:
Akun harus memiliki assignment gudang di user_warehouse_assignments. Jika tidak ada assignment, halaman tetap muncul tetapi tombol mulai sampling tidak dapat digunakan dan sistem menampilkan pesan agar Admin Gerai memberikan akses gudang.
