PATCH V16 — KEMBALIKAN SCAN KAMERA + AI TANPA MENGHAPUS INPUT MANUAL V15

Kebutuhan: versi V15 sudah terpasang dan berjalan.

1. Ekstrak ZIP ke C:\xampp82\htdocs\opanme (sejajar artisan).
2. Jalankan: php INSTALL_V16.php
3. Jalankan: php artisan optimize:clear
4. Buka ulang halaman /checker/sessions/{id}/scan lalu Ctrl+F5.

Tampilan sekarang:
- Scan Barcode dengan Kamera (aktifkan, matikan, atau Ambil Gambar & Baca AI)
- Input Kode Barang Tanpa Barcode V15 TETAP ADA di bawah area kamera
- Input manual memvalidasi item -> modal Qty + Foto wajib, dengan kamera sendiri
- Kamera scanner berhenti sementara saat modal manual dibuka, lalu dilanjutkan jika sebelumnya aktif.
- Hasil AI harus dikonfirmasi sebelum scan dikirim.
- Barang Non-System tetap dapat didaftarkan lewat form lama.

Dua file yang diubah:
resources/views/checker/scan.blade.php
public/assets/js/checker-camera-v16.js (baru)

Tidak ada migration database atau route baru.

PERHATIAN:
- Kamera memerlukan HTTPS atau localhost (127.0.0.1 berlaku hanya pada perangkat yang mengakses komputernya sendiri).
- AI membutuhkan endpoint checker.camera-ai.read dari V15 dan API key jika menggunakan cloud Vision.
- Perangkat yang tidak memiliki BarcodeDetector memerlukan assets/vendor/zxing-browser.min.js.
- Pengujian langsung di kamera HP, database, dan server harus dilakukan di lokal Anda.
