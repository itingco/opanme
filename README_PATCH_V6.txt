OPANME V6 - PERBAIKAN SCAN RATIO DAN KAMERA
=========================================
Target: project Laravel itingco/opanme dengan V4 sebelumnya.

INSTALL:
1. Backup project dan database lokal.
2. Ekstrak zip ke root project (sejajar file artisan).
3. Jalankan: php APPLY_FIX_V6.php
4. Jalankan: php artisan optimize:clear
5. Refresh halaman checker pada HP atau komputer. Cache JS dibedakan v=20261116v6.

FILE YANG DIUBAH OTOMATIS:
- app/Services/ScanService.php
- public/assets/js/app.js
- resources/views/layouts/app.blade.php (hanya versi cache script scanner)

PERUBAHAN:
1. Scan barcode master mengambil mapping dari ItemBarcode (AppHub), item identitas dari stock_snapshots untuk cycle berjalan, fallback ke scan_transactions dalam cycle yang sama. Ratio dari ItemRatio AppHub. physical_qty = 1 * ratio, tanpa query AS_INGCO/AS_SMI per scan.
2. QR KODE~QTY membaca item snapshot cycle; qty dianggap dalam smallest UOM (perilaku semula dipertahankan).
3. Jika item tidak ada dalam snapshot cycle/riwayat scan, scan ditolak dengan pesan jelas dan TIDAK otomatis query ERP.
4. Kamera: menghapus teks patch yang secara tidak sengaja tersisip ke JS, fallback pilihan kamera, pesan error detail, dan cache-bust.

CATATAN:
- Pastikan fitur scanner memakai HTTPS di HP; HTTP selain localhost sering tidak diizinkan browser.
- Tombol izin Kamera pada browser harus diaktifkan untuk domain aplikasi.
- Harus ada file public/assets/vendor/zxing-browser.min.js.
- Jika snapshot cycle tidak memuat item yang diharapkan, cek tahap pengambilan snapshot cycle.
- Override, status cycle, dan data hasil scan tidak dihapus.
- Installer membuat backup *.bak-v6-timestamp pada file yang diubah.

PENGUJIAN:
- Scan barcode UOM PCS ratio 1 -> physical_qty 1.
- Scan barcode UOM BOX ratio 12 -> physical_qty 12.
- Scan QR ABC~5 -> physical_qty 5 (smallest UOM).
- Scan barang tidak ada di snapshot -> pesan error, tidak query ERP.
- Kamera HTTPS -> izin browser -> video aktif; coba scan dan tutup/refresh.
- Kamera diblokir -> tampak pesan jelas.

Patch telah melewati php -l dan simulasi installer di file sampel, belum diuji dengan database/HP lokal pengguna.
