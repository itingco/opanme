UPDATE OPNAME - SIDEBAR + SAMPLING GUDANG
Tanggal: 21 September 2026

CARA PASANG
1. Backup project/database local Anda terlebih dahulu.
2. Copy seluruh isi folder update ini ke root project opanme Anda.
   Pilih overwrite/replace jika file sudah ada.
3. Dari terminal VS Code di root project jalankan:

   php artisan optimize:clear
   php artisan migrate
   npm install
   npm run build

   Untuk mode development Anda juga bisa memakai: npm run dev

4. Login sebagai ADMIN existing.
5. Buka menu User, buat:
   - role ADMIN_GUDANG
   - role CHECKER_GUDANG
6. Login ADMIN_GUDANG -> menu Sampling Gudang:
   - buat periode
   - pilih database ERP + gudang
   - tentukan target %
   - pilih Checker Gudang
   - cari & tambah item
   - item yang sama BOLEH ditambah berulang kali
   - klik Serahkan ke Checker
7. Login CHECKER_GUDANG -> Tugas Sampling:
   - buka periode
   - hitung barang fisik
   - masukkan Qty Fisik Ditemukan
   - sistem otomatis menghitung cocok/selisih dan progress.

CATATAN DESAIN
- Periode baru dimulai dengan status DRAFT.
- Selama DRAFT item boleh tambah/hapus.
- Setelah "Serahkan ke Checker" status OPEN dan daftar item dikunci.
- Item yang sama boleh muncul berkali-kali dalam satu periode karena setiap pilihan disimpan sebagai line sampling berbeda.
- Progress = jumlah line yang sudah dicek / total line.
- Target % dihitung dari jumlah line, jadi item duplikat tetap dihitung sebagai target sampling tersendiri.
- Checker memakai blind count: qty sistem tidak ditampilkan sebelum qty fisik pertama kali disimpan.
- ADMIN existing dapat melihat seluruh periode Sampling Gudang.
- ADMIN_GUDANG hanya melihat periode yang dibuat sendiri.
- Flow lama ADMIN/CHECKER/GERAI tetap dipertahankan.

ROLLBACK DATABASE
Jika update baru saja dimigrate dan belum ada migration lain setelahnya:
php artisan migrate:rollback --step=1
