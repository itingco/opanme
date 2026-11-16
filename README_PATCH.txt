PATCH V4 - CHECKER CYCLE LEGACY
Sumber acuan: itingco/opanme branch main, 16 November 2026.

CARA PASANG
1. Backup project & database.
2. Extract file APPLY_PATCH.php ke root Laravel (sejajar artisan).
3. Jalankan: php APPLY_PATCH.php
4. Jalankan: php artisan optimize:clear
5. Ctrl+F5 halaman checker agar public/assets/js/app.js terbaru dimuat.

FILES TARGET
- app/Services/ScanService.php
- public/assets/js/app.js

FITUR
- Scan sukses menampilkan notifikasi 1,1 detik lalu kembali 'Siap scan'.
- Barcode Non-System pertama kali: input nama, qty, UOM, ratio.
- Barcode sama pada cycle aktif: otomatis tambah 1 UOM (menggunakan ratio yang pertama disimpan), tanpa form.
- 'Kode barang' Non-System adalah alias barcode pada cycle tersebut; tidak menambah master ERP.
- Penemuan barcode hanya dicari pada cycle_id sesi aktif (lintas warehouse dalam cycle yang sama).
- Scan dari cycle lain tidak dikenali sebagai barang temuan pada cycle baru.
- Tidak mengubah skema CLOSED / FINALIZED atau tabel database.

PENTING
- Qty scan ulang otomatis 1 UOM, bukan qty yang dimasukkan pertama kali.
- Camera cooldown mencegah scan ganda cepat untuk barcode sama selama 2,5 detik.
- Simpan barcode dan nama barang pertama kali diperlukan sekali per cycle.
- Installer memerlukan source file original sesuai repo. Jika local Anda telah memodifikasi bagian yang sama, installer menolak tanpa mengubah file.
- Tidak diperlukan migration.

TEST
A) Status OPEN; scan barcode NON123 baru, nama barang dan qty 3, UOM PCS, simpan.
B) Scan NON123 lagi setelah jeda >2,5 detik: tanpa modal, bertambah 1 PCS.
C) Scan di warehouse lain dalam cycle sama: dikenali.
D) Scan pada cycle baru: harus registrasi ulang.
E) Setelah CLOSED: scan harus ditolak.
F) Success feedback kembali ke Siap scan dalam 1,1 detik.
