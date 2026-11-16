OPANME V14 - ADMIN PREVIEW FOTO BUKTI
===================================
Fitur:
- Pada Summary Opname, klik 'Detail & Foto Bukti' pada tiap item ERP.
- Lihat transaksi scan pada cycle dan warehouse terpilih.
- Klik thumbnail foto bukti checker untuk preview besar dalam dialog.
- Thumbnail hanya dapat diakses lewat route admin yang dilindungi middleware auth + role:ADMIN.
- Mendukung foto dari V12/V13: opname_manual_scan_photos.
- Mendukung foto lama V9: stock_opname_manual_photo_evidence.
- Data barcode scan lama tanpa foto tetap dapat dilihat.

INSTALL
1. Backup routes/web.php dan resources/views/admin/cycles/summary.blade.php.
2. Extract seluruh isi ZIP ke root project Laravel (sejajar artisan), jangan cuma INSTALL_V14.php.
3. PowerShell:
   cd C:\xampp82\htdocs\opanme
   php INSTALL_V14.php
   php artisan optimize:clear
   php artisan route:list --name=photo-evidence
4. Buka Admin -> Cycle -> Summary -> klik Detail & Foto Bukti.

Yang berubah:
- routes/web.php (installer memodifikasi)
- resources/views/admin/cycles/summary.blade.php (installer memodifikasi)
Yang baru:
- app/Http/Controllers/Admin/CyclePhotoEvidenceController.php
- resources/views/admin/cycles/photo-evidence.blade.php
- INSTALL_V14.php dan README_V14.txt

Tidak memerlukan migration baru. Membaca dua tabel foto lama jika tersedia.
Tidak memberi akses publik ke file foto dan tidak mengubah scan/qty.

Catatan: Ini halaman audit bukti foto. Tidak otomatis mengubah status persetujuan/finalisasi.
