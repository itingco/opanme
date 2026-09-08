OPNAME - GERAI MOBILE SCANNER UPDATE
====================================

Target:
C:\xampp82\htdocs\opname

Tujuan:
Membuat scan Sampling GERAI responsive di mobile dan mengikuti pola
scanner CHECKER, tanpa mengubah backend sampling.

FILE PRODUKSI YANG DIGANTI
--------------------------
resources\views\gerai\scan.blade.php
resources\css\sampling.css
resources\js\sampling.js

File test opsional:
tests\static\mobile_sampling_scanner_contract.php

PERUBAHAN UI
------------
- Header sampling dibuat compact seperti scanner Checker.
- Cycle, warehouse, dan lokasi aktif tampil dalam context card.
- Feedback scan memakai pola Checker.
- Kamera memakai rasio mobile portrait + scan guide merah.
- Tombol Aktifkan Kamera model Checker.
- Manual barcode full-width di mobile.
- Hasil item menjadi focused card tepat di bawah scanner.
- Stok Sistem tetap tampil besar.
- Tombol Stok Cocok / Tidak Cocok dibuat besar untuk mobile.
- Qty Fisik hanya muncul saat Tidak Cocok.
- Ganti Lokasi / Rak menjadi collapsible.
- Riwayat Sampling otomatis collapsed di mobile dan terbuka di desktop.
- Setelah simpan hasil, result card hilang dan scanner kembali siap.

BACKEND
-------
Tidak ada controller, route, migration, atau query database yang berubah.

CARA PASANG
-----------
1. Backup tiga file produksi di atas.
2. Extract ZIP.
3. Copy folder resources ke:
   C:\xampp82\htdocs\opname
   pilih Replace.
4. Opsional copy folder tests.

Jalankan:

cd /d C:\xampp82\htdocs\opname
npm install
npm run build

C:\xampp82\php\php.exe artisan optimize:clear
C:\xampp82\php\php.exe artisan config:cache
C:\xampp82\php\php.exe artisan view:cache

Kemudian buka halaman sampling GERAI dari HP dan Ctrl+F5 / refresh penuh.

TEST OPSIONAL
-------------
C:\xampp82\php\php.exe tests\static\mobile_sampling_scanner_contract.php

Expected:
mobile sampling scanner contract: PASS

CATATAN
-------
Build Vite harus dilakukan di Windows/local Anda. Environment pembuatan patch
ini membawa node_modules Windows sehingga tidak dapat dibuild dari Linux.
