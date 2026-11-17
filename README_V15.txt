PATCH V15 - INPUT KODE BARANG + MODAL QTY/FOTO/KAMERA
Untuk itingco/opanme Laravel 10, melanjutkan V12/V13/V14.

PERUBAHAN
- Tidak ada lagi kolom ketik barcode / kamera otomatis ketika halaman checker pertama dibuka.
- Tersedia kolom Kode Barang yang siap dipakai, lalu tombol Cek Barang.
- Kode diperiksa pada stock_snapshots warehouse + cycle aktif, yang sudah diambil dari source_database cycle.
- Jika item tidak ditemukan, modal TIDAK dibuka.
- Jika valid, modal rapi tampil: kode/nama barang, qty smallest UOM, video live, Ambil Foto, Preview, Simpan.
- Kamera di modal manual; tidak perlu memilih file atau upload galeri.
- Foto dan qty wajib; server tetap melakukan validasi kode saat menyimpan, jangan hanya mengandalkan pemeriksaan browser.
- Pembaca barcode via frame + AI tetap tersedia sebagai opsi di modal.
- Tidak mengubah fitur V14 preview admin; bukti menggunakan tabel opname_manual_scan_photos yang sama dengan V12.
- Menggunakan satu video aktif; ketika modal ditutup, track kamera dimatikan.

CARA PASANG
1. Backup file project dan database. Pastikan versi sebelumnya V12/V13/V14 sudah digunakan.
2. Extract seluruh ZIP ke ROOT project Laravel, misalnya C:\xampp82\htdocs\opanme.
   Struktur: artisan, INSTALL_V15.php dan folder V15_PATCH berada sejajar.
3. Jalankan di PowerShell:
   cd C:\xampp82\htdocs\opanme
   php INSTALL_V15.php
   php artisan migrate
   php artisan optimize:clear
   php artisan route:list --name=manual-item.lookup
   php artisan route:list --name=camera
4. Restart php artisan serve bila perlu, refresh Ctrl+F5.

PENGUJIAN
- CHECKER membuka /checker/sessions/{id}/scan: kolom kode barang tersedia, kamera belum aktif.
- Input kode barang yang tidak ada => pesan error; modal tidak muncul.
- Input kode valid cycle/warehouse => modal terbuka dengan nama item.
- Isi Qty tanpa foto => tombol Simpan masih disabled.
- Aktifkan Kamera => ambil foto => Simpan Qty+Foto berhasil.
- Admin preview foto melalui V14.
- Jika menggunakan HP via alamat IP HTTP, browser biasanya menolak kamera; gunakan HTTPS.
- Untuk kode barang yang tidak ada dalam snapshot, langkah manual ini sengaja ditolak untuk menghindari salah input.
- Koneksi AS_INGCO atau AS_SMI tidak di-query ulang: hasil dari snapshot cycle yang berasal dari DB pilihan.

CATATAN
- Foto AI harus dikonfirmasi sebelum dikirim ke ScanService.
- Input manual Qty menggunakan smallest UOM, tidak dikalikan ratio kembali.
- Browser mengontrol volume media; beep JavaScript tidak bisa menaikkan volume perangkat di luar batasnya.
- Jika route/list atau migration error, hentikan dan berikan output error lengkap untuk diperbaiki.
