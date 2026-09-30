FIX TOMBOL KAMERA SAMPLING GERAI
Tanggal: 2026-09-30

Masalah:
- Tombol "Aktifkan Kamera" ditekan tetapi tidak terjadi apa-apa.

Perbaikan:
1. sampling.js sekarang di-init ulang setelah DOM siap dan hanya sekali.
2. Class ZXing dibaca saat tombol kamera ditekan, bukan dicapture terlalu awal.
3. scan.blade.php memuat ZXing + sampling.js langsung pada halaman scanner dengan cache version baru.
4. Halaman menampilkan pesan "Scanner siap" jika script benar-benar aktif.
5. Jika akses HP masih HTTP, tombol Aktifkan Kamera otomatis membuka fallback kamera foto.
6. Untuk live barcode scanner, browser mobile tetap membutuhkan HTTPS.
7. Jika script gagal aktif, halaman menampilkan pesan diagnostik dan tombol berubah menjadi "Muat Ulang Scanner".

File berubah:
- public/assets/js/sampling.js
- public/assets/vendor/zxing-browser.min.js
- resources/views/gerai/scan.blade.php

Setelah copy:
php artisan view:clear
php artisan optimize:clear

Kemudian tutup tab browser HP, buka lagi halaman Sampling Gerai, lalu refresh.
