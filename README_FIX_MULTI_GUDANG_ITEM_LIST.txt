FIX DAFTAR ITEM MULTI-GUDANG
Tanggal: 23-09-2026

Masalah:
Periode sudah berisi beberapa gudang, tetapi pada halaman pemilihan item terlihat seolah-olah item hanya berasal dari satu gudang. Hal ini membingungkan terutama karena daftar gabungan diurutkan berdasarkan kode item; halaman pertama dapat didominasi oleh satu gudang saja.

Perbaikan:
1. Backend membaca snapshot setiap gudang terpilih secara independen sebelum item digabung.
2. API sekarang mengirim ringkasan jumlah item stok > 0 untuk SETIAP gudang periode.
3. Ditambah filter gudang pada daftar item.
4. Ditambah chip ringkasan per gudang, contoh: AS_INGCO · G-ACEK — 1.234 item stok.
5. Klik chip gudang untuk menampilkan item stok dari gudang tersebut; klik lagi untuk kembali ke Semua Gudang.
6. Rincian per item tetap menunjukkan gudang mana saja yang memiliki stok dan berapa qty-nya.
7. Item tetap disimpan sebagai item gabungan dan snapshot per gudang tetap terpisah untuk proses validasi.

FILE YANG BERUBAH:
- app/Http/Controllers/Admin/WarehouseSamplingController.php
- app/Services/WarehouseSamplingMultiStockService.php
- resources/views/warehouse_sampling/admin/show.blade.php
- public/assets/css/warehouse-sampling.css

CARA PASANG:
Copy/overwrite isi folder update ke root project opname.

Kemudian jalankan:
  composer dump-autoload
  php artisan view:clear
  php artisan optimize:clear

Tidak ada migration baru.
Tidak perlu npm / Vite.

CARA CEK:
1. Buat periode dengan 3 gudang.
2. Masuk Kelola & Validasi.
3. Di bagian Pilih Item dari Stok Gabungan harus muncul chip 3 gudang.
4. Tiap chip menampilkan jumlah item stok > 0 dari gudang tersebut.
5. Klik G-ACEK -> tabel hanya item yang punya stok di G-ACEK.
6. Klik G-TOKO -> tabel hanya item yang punya stok di G-TOKO.
7. Pilih Semua Gudang -> tabel kembali menjadi union/gabungan semua gudang.
