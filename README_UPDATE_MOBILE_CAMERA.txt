UPDATE SAMPLING GERAI + GUDANG MOBILE FRIENDLY / CAMERA FIX
Tanggal: 30-09-2026

FILE YANG BERUBAH
- public/assets/js/sampling.js
- public/assets/css/sampling.css
- public/assets/css/warehouse-sampling.css
- public/assets/vendor/zxing-browser.min.js
- resources/views/gerai/scan.blade.php
- resources/views/gerai/checker_home.blade.php
- resources/views/warehouse_sampling/checker/index.blade.php
- resources/views/warehouse_sampling/checker/show.blade.php

PERUBAHAN KAMERA SAMPLING GERAI
1. Tombol Aktifkan Kamera sekarang memberi status/error yang jelas.
2. Kamera belakang diprioritaskan.
3. Jika browser/HP membuka aplikasi lewat HTTP LAN dan live camera diblokir browser,
   user dapat memakai tombol "Ambil Foto Barcode" sebagai fallback.
4. Foto barcode dibaca oleh ZXing lokal, tidak membutuhkan layanan internet.
5. Cache asset diganti versi agar browser mengambil JS/CSS baru.

PENTING UNTUK LIVE CAMERA DI HP
- getUserMedia pada browser mobile umumnya membutuhkan HTTPS.
- Akses seperti http://192.168.x.x/opname dapat diblokir browser untuk live camera.
- Gunakan HTTPS untuk pengalaman live scanning terbaik.
- Fallback "Ambil Foto Barcode" tetap tersedia jika live camera tidak bisa dipakai.

MOBILE FRIENDLY
- Sampling Gerai: scanner, modal Cocok/Selisih, input manual dan history responsive.
- History pada HP berubah menjadi card per hasil scan.
- Sampling Gudang Checker: tabel berubah menjadi card per item di HP.
- Modal Qty + komentar menjadi bottom sheet pada HP.
- Tugas Sampling Gudang dan daftar gudang lebih nyaman di-scroll/tap.

SETELAH COPY/OVERWRITE
php artisan view:clear
php artisan optimize:clear

Lalu di HP/browser lakukan hard refresh / hapus cache situs jika asset lama masih tampil.
Tidak ada migration database. Tidak perlu npm/vite.
