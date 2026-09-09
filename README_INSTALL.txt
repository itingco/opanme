OPNAME - UPDATE SAMPLING VALIDATION MODAL
=========================================

Basis project:
https://github.com/itingco/opanme.git
Branch: main
Referensi commit saat analisis: c754bd4 (update changepassword)

TUJUAN
------
Saat barcode Sampling Check berhasil ditemukan, validasi stok sekarang langsung
muncul sebagai modal wajib. User tidak perlu scroll ke bawah lagi.

FLOW BARU
---------
1. Scan / input barcode.
2. Jika item ditemukan, modal "Validasi Stok Fisik" langsung muncul.
3. Modal tidak memiliki tombol X dan tidak bisa ditutup dari backdrop/ESC.
4. Scanner dikunci selama validasi masih pending agar barcode lain tidak terbaca.
5. Pilih:
   - Stok Cocok -> langsung simpan MATCH.
   - Ada Selisih -> isi Qty Fisik, lalu Simpan Hasil.
6. Setelah berhasil tersimpan, modal menutup dan scanner aktif untuk item berikutnya.

FILE YANG DIUBAH
----------------
resources/views/gerai/scan.blade.php
resources/js/sampling.js
resources/css/sampling.css

FILE TEST BARU
--------------
tests/static/sample_validation_modal_contract.php

TIDAK ADA PERUBAHAN
-------------------
- Database / migration
- Route
- SamplingController
- SampleCheckingService
- Model SampleCheck

CARA COPY MANUAL
----------------
1. Backup terlebih dahulu 3 file existing:
   resources/views/gerai/scan.blade.php
   resources/js/sampling.js
   resources/css/sampling.css

2. Extract ZIP ini.

3. Copy isi folder ZIP ke root project opanme Anda dan overwrite file dengan path
   yang sama.

4. File test boleh ikut dicopy:
   tests/static/sample_validation_modal_contract.php

5. Dari root project jalankan:

   php artisan optimize:clear

6. Untuk local development dengan Vite:

   npm install
   npm run dev

   ATAU untuk membuat asset production:

   npm install
   npm run build

CATATAN:
Jika node_modules sudah ada, npm install tidak wajib diulang. Cukup npm run dev
atau npm run build sesuai cara Anda menjalankan project.

TEST
----
Jalankan:

   php tests/static/sample_validation_modal_contract.php
   php tests/static/sample_scanner_contract.php
   npm run build

HASIL YANG DIHARAPKAN
---------------------
- Scan item valid -> modal langsung tampil di tengah layar.
- Tidak ada tombol untuk menutup modal tanpa validasi.
- Klik backdrop / tekan ESC tidak menutup modal.
- Saat modal terbuka, scan berikutnya diabaikan.
- Stok Cocok -> data tersimpan, modal menutup, history bertambah.
- Ada Selisih -> Qty Fisik wajib diisi, selisih ditampilkan, lalu disimpan.
- Jika API gagal menyimpan, modal tetap terbuka dan pesan error tampil di modal.

ROLLBACK
--------
Kembalikan 3 file backup Anda. Tidak ada rollback database karena update ini tidak
memakai migration.
