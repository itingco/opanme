PATCH V8 - BACA BARCODE DARI FOTO (CHECKER CYCLE LEGACY)

FILE DIPAKAI (pertahankan struktur folder):
- app/Http/Controllers/Checker/LegacyPhotoReadController.php (baru)
- public/assets/js/legacy-photo-scan.js (baru)
- resources/views/checker/warehouse-legacy-scan.blade.php (ditimpa)
- APPLY_PHOTO_V8.php (installer perubahan satu route)

SETUP:
1. Backup file resources/views/checker/warehouse-legacy-scan.blade.php dan database.
2. Salin isi ZIP ke root Laravel, SEJAJAR folder app dan file artisan.
3. Jalankan php APPLY_PHOTO_V8.php
4. Opsional AI: tambahkan OPENAI_API_KEY=... ke .env pada server (jangan commit token). Model default gpt-4.1-mini; opsional OPENAI_VISION_MODEL.
5. php artisan optimize:clear
6. php artisan route:list --name=photo.read
7. Refresh halaman Checker Cycle Legacy. HTTPS dibutuhkan untuk kamera web pada HP.

ALUR:
- Kamera aktif: klik Baca dari Foto untuk mengambil 1 frame.
- Kamera tidak aktif: tombol membuka native camera/photo picker.
- BarcodeDetector lalu ZXing browser mencoba membaca gambar secara lokal.
- Jika keduanya gagal, endpoint AI berbasis OpenAI API dicoba HANYA jika API key tersedia.
- Kode hasil foto harus dikonfirmasi pengguna dan dikirim via form scan existing.
- Tidak otomatis membuat transaksi dari jawaban AI; validasi dilakukan ScanService existing.

CATATAN:
- AI API menerima foto ke layanan pihak ketiga, pastikan kebijakan privasi internal mengizinkan.
- AI tidak menjamin akurasi; operator wajib memeriksa kode.
- Fitur ini tidak menambah database/migration, tidak mengubah scan normal, dan khusus Checker Cycle Legacy.
- Paket tidak mengandung API key atau dependensi Composer.
- Pengujian lapangan dengan kamera, API, dan database tetap diperlukan.
