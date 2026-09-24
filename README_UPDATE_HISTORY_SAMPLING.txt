UPDATE: HISTORY SAMPLING GUDANG + PDF + EXCEL RAW
Tanggal: 23 September 2026

FITUR BARU
1. Menu baru: History Sampling.
2. History hanya menampilkan periode Sampling Gudang berstatus CLOSED.
3. Admin dapat melihat semua history; ADMIN_GUDANG hanya history periode yang dibuatnya sendiri.
4. Filter tersedia untuk:
   - pencarian bebas (periode/item/gudang/komentar/checker/validator)
   - tanggal tutup dari/sampai
   - database
   - gudang
   - checker
   - hasil Cocok/Selisih
5. Tabel raw menggunakan satu baris per ITEM x GUDANG.
6. Export PDF mengikuti filter aktif.
7. Export Excel .xlsx mengikuti filter aktif dan berisi data mentah lengkap.
8. Qty pada Excel ditulis sebagai numeric cell agar dapat langsung SUM/Pivot.
9. Penutupan periode sekarang wajib:
   - seluruh Qty checker sudah difinalisasi
   - seluruh item sudah divalidasi Admin Gudang
   Setelah ditutup, user diarahkan ke History Sampling.

CARA INSTALL
1. Copy seluruh folder dari ZIP ini ke root project:
   C:\xampp82\htdocs\opname
2. Pilih Replace/Overwrite untuk file yang sama.
3. Jalankan:

   composer dump-autoload
   php artisan route:clear
   php artisan view:clear
   php artisan optimize:clear

4. Tidak ada migration baru pada update ini.
5. Tidak perlu npm / vite.
6. Refresh browser dengan Ctrl + F5.

ROUTE BARU
GET admin/warehouse-sampling/history
GET admin/warehouse-sampling/history/export-pdf
GET admin/warehouse-sampling/history/export-excel

CATATAN DATA RAW EXCEL
Kolom Excel meliputi:
Tanggal Tutup, No Periode, Area/Lokasi, Dibuat Oleh, Checker,
Waktu Checker, Validator, Waktu Validasi, Database, Warehouse ID,
Kode Gudang, Nama Gudang, Line, Kode Item, Nama Item, UOM,
Total Sistem Gabungan, Fisik Total Checker, Stok Sistem Gudang,
Fisik Alokasi Gudang, Selisih Gudang, Hasil Gudang,
Komentar Checker, Catatan Validasi.
