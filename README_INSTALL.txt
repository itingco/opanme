PATCH V11 — FIX HALAMAN CHECKER /checker/sessions/{session}/scan

PENYEBAB
Screenshot menggunakan route checker.scan (/checker/sessions/3/scan),
sedangkan patch V8/V9 menambahkan antarmuka terutama di warehouse.legacy.scan.
Perubahan V11 memasang UI di resources/views/checker/scan.blade.php yang benar-benar dipakai route checker.scan.

FITUR
1) Baca dari Kamera: ambil foto dari video scanner aktif, decode kode lokal atau AI opsional, konfirmasi dulu.
2) Ambil / Pilih Foto: pilih atau ambil gambar di HP, decode lalu konfirmasi.
3) Barang tanpa Barcode: isi Item Code + Qty + foto bukti wajib; foto disimpan privat dan transaksi masuk scan cycle aktif.
4) Form barcode tetap tersedia, tetapi judul menjadi "Scan barcode (jika ada)".
5) Route /checker dan /warehouse-legacy-checker memakai controller yang sama untuk input manual.

INSTAL
1. BACKUP project dan database.
2. Extract isi ZIP langsung ke root project Laravel (sejajar artisan). Saat diminta, timpa file paket berikut bila sudah ada.
3. Jalankan:
   php INSTALL_V11.php
   php artisan migrate
   php artisan optimize:clear
   php artisan route:list --name=checker.manual-photo.store
   php artisan route:list --name=checker.photo.read
4. Refresh browser Ctrl+F5 dan buka URL /checker/sessions/{session}/scan.

FILE PAKET
app/Http/Controllers/Checker/ManualPhotoCountController.php
app/Http/Controllers/Checker/LegacyPhotoReadController.php
database/migrations/2026_11_16_220000_create_stock_opname_manual_photo_evidence.php
resources/views/checker/partials_manual_count.blade.php
resources/views/checker/partials_photo_tools.blade.php
public/assets/js/legacy-photo-scan.js
INSTALL_V11.php (mengubah routes/web.php, routes/warehouse_sampling.php, resources/views/checker/scan.blade.php dan backup .before-v11)

CATATAN
- Foto bukti wajib untuk input tanpa barcode; data foto disimpan pada disk 'local', bukan URL public.
- Form tanpa barcode hanya menerima Item Code dari stock_snapshots warehouse + cycle aktif.
- API Vision bersifat opsional: set OPENAI_API_KEY jika ingin fallback; tanpa key decoder lokal tetap tersedia.
- Foto pada fitur 'Baca dari Foto' hanyalah sarana membaca barcode, BUKAN bukti foto wajib untuk input tanpa barcode.
- Jika user membuka /warehouse-legacy-checker/sessions/.../scan dan sudah ada V8/V9, kemungkinan tombol ganda; fokus perbaikan ini route /checker yang ditunjukkan screenshot. Jika perlu bersihkan extends legacy dari patch sebelumnya.
- Tidak diuji dengan DB atau kamera HP produksi. Coba di lingkungan test dahulu.
