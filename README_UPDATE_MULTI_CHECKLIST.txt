UPDATE PEMILIHAN ITEM SAMPLING GUDANG - MULTI CHECKLIST
======================================================

Perubahan:
1. Admin Gudang tidak lagi mencari/tambah barang satu per satu.
2. Halaman detail periode menampilkan item yang stok sistemnya > 0 pada gudang periode tersebut.
3. User dapat search kode/nama, checklist banyak item, pindah halaman, dan pilihan tetap tersimpan di browser selama belum disubmit.
4. Klik "Simpan Item Terpilih" hanya satu kali untuk memasukkan seluruh pilihan.
5. Qty sistem disnapshot saat tombol Simpan ditekan.
6. Kolom "Sudah Dipilih" memperlihatkan berapa kali item tersebut sudah pernah masuk dalam periode.
7. Item yang sama TIDAK dinonaktifkan. Jadi kebutuhan lama "item boleh berulang dalam satu periode" tetap berlaku: pilih kembali item tersebut pada batch berikutnya.
8. Hanya item dengan stok > 0 yang dapat disimpan.
9. Tetap versi NO-VITE. Tidak perlu npm install / npm run build.

FILE YANG BERUBAH
-----------------
app/Http/Controllers/Admin/WarehouseSamplingController.php
app/Services/WarehouseSamplingCatalogService.php
resources/views/warehouse_sampling/admin/show.blade.php
public/assets/css/warehouse-sampling.css
routes/warehouse_sampling.php

CARA UPDATE
-----------
Copy folder app, resources, public, dan routes ke root project lokal lalu Replace/Overwrite.

Setelah copy jalankan:

composer dump-autoload
php artisan optimize:clear
php artisan route:list

Tidak ada migration baru untuk update ini.
