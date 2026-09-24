UPDATE CHECKER GUDANG - BATCH SAVE + FINAL LOCK
Tanggal: 23 September 2026

TUJUAN
- Checker tidak lagi menyimpan Qty Fisik satu per satu.
- Seluruh Qty Fisik Total dapat diisi dalam satu halaman.
- Tombol "Simpan Draft Semua" menyimpan seluruh input sekaligus dan data masih dapat diedit.
- Tombol "Simpan Semua & Finalisasi" menyimpan seluruh input dan mengunci hasil checker.
- Finalisasi hanya aktif jika SEMUA item telah memiliki Qty Fisik.
- Setelah finalisasi, input tidak tampil lagi dan backend juga menolak perubahan.
- Admin Gudang baru melihat Qty Fisik sebagai hasil final setelah checker melakukan finalisasi.
- Distribusi proporsional per gudang tetap dilakukan oleh Admin Gudang pada tahap validasi.

CARA INSTALL
1. Extract ZIP.
2. Copy isi folder update ke root project, contoh:
   C:\xampp82\htdocs\opname
3. Pilih Replace/Overwrite untuk file yang sama.
4. Jalankan:

   composer dump-autoload
   php artisan route:clear
   php artisan view:clear
   php artisan optimize:clear

5. Refresh browser dengan Ctrl + F5.

TIDAK ADA MIGRATION BARU.
TIDAK PERLU NPM / VITE.

FLOW CHECKER BARU
1. Buka periode sampling.
2. Isi Qty Fisik Total pada banyak/semua item.
3. Klik "Simpan Draft Semua" kapan saja selama pengecekan.
4. Draft dapat dibuka kembali dan diperbaiki.
5. Setelah semua Qty sudah benar, klik "Simpan Semua & Finalisasi".
6. Sistem meminta konfirmasi.
7. Setelah finalisasi, semua Qty terkunci dan tidak dapat diedit lagi.
8. Admin Gudang melakukan validasi/distribusi proporsional ke masing-masing gudang.

CATATAN
Endpoint save per-item lama tetap ada agar route lama tidak hilang, tetapi proses per-item sudah dinonaktifkan dan akan meminta checker memakai batch save.
