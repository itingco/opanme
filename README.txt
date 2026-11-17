PATCH V20 - OPANME CHECKER CAMERA AI + RIWAYAT SAMPLING

Perubahan:
1. Kamera: lewati QR URL http:// / https:// tanpa mengirim scan.
2. Ambil Gambar & Baca AI: setelah OCR/BarcodeDetector, validasi barcode pada master ItemBarcode + ItemRatio dan snapshot cycle aktif; tampilkan kode, nama, UOM, ratio, qty sebelum tombol konfirmasi diaktifkan.
3. Riwayat: tampilkan Sampling Gudang semua status, ditambah Sampling Gerai (sample_checks), berdasarkan database/item/ERP warehouse sama. Rentang default tetap tiga bulan, bisa pilih Semua Tanggal.

Pemasangan (backup database/kode lebih dulu):
- Extract isi ZIP ini ke ROOT Laravel, sejajar dengan artisan.
- php INSTALL_V20.php
- php artisan optimize:clear
- php artisan route:list --name=camera-barcode.lookup

PENTING: Jangan upload folder opanme_v20_patch sebagai subfolder. Isi folder itu yang harus ada di root project.

Yang diperbarui langsung dengan salin file:
 app/Http/Controllers/Checker/CameraBarcodeLookupController.php
 app/Http/Controllers/Admin/CycleSamplingItemHistoryController.php
 resources/views/admin/cycles/sampling-item-history.blade.php
 routes/checker_camera_lookup_v20.php

INSTALL_V20.php mengubah:
 routes/web.php
 resources/views/checker/scan.blade.php
 public/assets/js/checker-camera-v16.js
 app/Http/Controllers/Checker/CameraAiReadController.php
File lama yang dimodifikasi installer dibackup dengan ekstensi .before-v20.

Tidak ada migration.

Catatan: Untuk barcode yang tidak cocok, jangan langsung diproses menjadi qty. Konfirmasi aktif hanya setelah lookup berhasil. Untuk pengujian pada server/staging, cek contoh barcode master, QR KODE~QTY, QR HTTPS, dan riwayat sampling yang belum CLOSED.
