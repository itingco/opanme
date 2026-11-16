PATCH V12 - CHECKER CAMERA-ONLY + MANUAL QTY PHOTO + AI FALLBACK
Target repo: itingco/opanme (Laravel 10)

FITUR
- Input barcode manual dihapus dari halaman scanner.
- Tombol Input Kode Barang membuka popup Item Code + Qty smallest UOM + foto wajib.
- Foto diambil langsung dari frame video kamera aktif (tanpa upload/galeri).
- Tombol Ambil Gambar & Baca AI mengambil frame video yang sama.
- Deteksi lokal BarcodeDetector dicoba dulu, lalu jika gagal server OpenAI Vision API.
- Nilai hasil deteksi dapat diperiksa/diperbaiki checker dan WAJIB dikonfirmasi.
- Setelah konfirmasi, alur ScanService biasa digunakan (termasuk KODE~QTY dan Non-System).
- Beep scan sukses diperkeras dengan dua nada dan vibrasi (batas volume tetap dikontrol HP).
- Fitur untuk /checker/sessions/{session}/scan dan /warehouse-legacy-checker/sessions/{session}/scan.

INSTALASI
1. Backup project Laravel dan database.
2. Extract ZIP ini ke root project, sejajar file artisan (boleh menimpa scan.blade.php).
3. php INSTALL_V12.php
4. php artisan migrate
5. php artisan optimize:clear
6. php artisan route:list --name=camera
7. Browser refresh paksa, atau tab baru pada HP.
8. Pastikan HTTPS jika akses menggunakan HP melalui jaringan selain localhost.

KONFIGURASI VISION AI (opsional, fallback lokal tetap dapat dicoba tanpa AI)
.env:
OPENAI_API_KEY=... (jangan commit)
OPENAI_VISION_MODEL=gpt-4.1-mini
Setelah ganti .env: php artisan optimize:clear
Model harus mendukung input gambar melalui Responses API.

CARA UJI
- Klik Aktifkan Kamera; pastikan kamera aktif dan mengarah ke barang.
- Klik Input Kode Barang, masukkan kode valid dan qty. Sebelum foto, tombol Simpan harus disabled.
- Ambil Foto Bukti dari Kamera -> preview -> simpan; cek scan_transactions dan opname_manual_scan_photos.
- Arahkan barcode ke kamera; jika tidak dibaca, tekan Ambil Gambar & Baca AI -> periksa hasil -> Konfirmasi & Proses Scan.
- Uji KODE~QTY; periksa physical_qty dan cycle+warehouse.
- Uji error foto, item tidak ditemukan, cycle CLOSED, session milik user lain.

KEAMANAN / BATASAN
- File foto disimpan private pada storage/app (disk local), bukan public.
- Tampilan tidak menyediakan unggah dari galeri, tetapi aplikasi web tidak dapat membuktikan secara kriptografis bahwa frame berasal dari kamera nyata.
- AI tidak menyimpan hasil otomatis. Checker harus memeriksa dan konfirmasi.
- OpenAI Vision API membutuhkan internet dan ada biaya berdasarkan penggunaan.
- Volume audio tidak bisa melebihi setelan media atau batas fisik HP.
- Snapshot item hanya untuk cycle dan warehouse aktif, tidak lookup ERP per transaksi.
- Jika kode item tidak ada di snapshot, input manual ditolak.

FILE DALAM ZIP
app/Http/Controllers/Checker/CameraManualController.php (baru)
app/Http/Controllers/Checker/CameraAiReadController.php (baru)
config/opanme_camera_ai.php (baru)
database/migrations/2026_11_16_235900_create_opname_manual_scan_photos_table.php (baru)
public/assets/js/checker-camera-v12.js (baru)
resources/views/checker/scan.blade.php (pengganti)
INSTALL_V12.php (mem-patch routes/web.php, routes/warehouse_sampling.php,
  public/assets/js/app.js, resources/views/layouts/app.blade.php dengan backup .before-v12)

PENTING: Repo publik yang diperiksa mungkin tidak mencakup semua patch lokal V9-V11. Backup file dan uji di salinan proyek dulu.
