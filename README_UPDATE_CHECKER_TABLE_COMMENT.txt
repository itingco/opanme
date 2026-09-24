UPDATE CHECKER GUDANG - TABLE INPUT + KOMENTAR + DRAFT/FINAL
Tanggal: 23 September 2026

PERUBAHAN
- Input checker sekarang berbentuk tabel, bukan card per item.
- Satu baris = satu item sampling.
- Kolom: No, Item, UOM, Qty Fisik Total, Komentar Checker, Status.
- Komentar Checker bersifat opsional dan disimpan per item.
- Tombol "Simpan Draft Semua" menyimpan Qty + komentar seluruh baris sekaligus.
- Draft dapat dibuka lagi dan Qty/komentar dapat diedit berulang kali.
- Tombol "Simpan Semua & Finalisasi" aktif jika seluruh Qty sudah terisi.
- Setelah finalisasi, Qty dan komentar terkunci dari UI dan backend.
- Admin Gudang dapat melihat Komentar Checker pada item yang sudah final saat proses validasi.
- Tabel memiliki scroll vertikal/horizontal dan header sticky untuk item yang banyak.

FILE BARU / BERUBAH
app/Http/Controllers/Warehouse/CheckerSamplingController.php
app/Models/SampleCycleItem.php
database/migrations/2026_09_23_000015_add_checker_comment_to_sample_cycle_items.php
resources/views/warehouse_sampling/checker/show.blade.php
resources/views/warehouse_sampling/admin/show.blade.php
public/assets/css/warehouse-sampling.css

CARA INSTALL
1. Extract ZIP.
2. Copy seluruh isi folder update ke root project, contoh:
   C:\xampp82\htdocs\opname
3. Pilih Replace/Overwrite.
4. Jalankan:

   composer dump-autoload
   php artisan optimize:clear
   php artisan migrate
   php artisan route:list --path=warehouse

5. Refresh browser dengan Ctrl + F5.

CATATAN
- Update ini MEMILIKI migration baru karena komentar checker perlu disimpan di database.
- Tidak perlu npm install / npm run build / Vite.
- Komentar tidak wajib untuk finalisasi. Yang wajib tetap Qty Fisik Total seluruh item.
