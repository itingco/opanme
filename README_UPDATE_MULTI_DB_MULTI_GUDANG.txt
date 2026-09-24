UPDATE SAMPLING GUDANG - MULTI DATABASE + MULTI GUDANG + VALIDASI DISTRIBUSI
Tanggal: 23 September 2026

TUJUAN FLOW BARU
1. ADMIN_GUDANG membuat 1 periode dan dapat memilih banyak gudang sekaligus dari AS_INGCO dan/atau AS_SMI.
2. Saat Admin memilih item, sistem menampilkan item yang stok gabungannya > 0 pada minimal satu gudang terpilih.
3. Setiap item disimpan 1 baris sampling, tetapi snapshot stok sistem disimpan TERPISAH untuk setiap database/gudang.
4. CHECKER_GUDANG tidak mengisi qty per gudang. Checker mengisi SATU Qty Fisik Total karena barang fisik memang digabung.
5. Setelah checker menyimpan Qty Fisik Total, ADMIN_GUDANG melakukan validasi dan membagi Qty Fisik Total ke masing-masing gudang.
6. Total alokasi fisik per gudang WAJIB sama dengan Qty Fisik Total dari checker.
7. Dengan cara ini Admin Gudang menentukan gudang mana yang COCOK, SELISIH LEBIH, atau SELISIH KURANG.
8. Jika checker mengubah Qty Fisik Total setelah pernah divalidasi, validasi/alokasi lama otomatis dibatalkan dan Admin harus validasi ulang.
9. Item yang sama tetap boleh dipilih berulang di periode yang sama pada batch penyimpanan berikutnya.

CONTOH
Stok sistem:
- AS_INGCO / GUDANG-A = 10
- AS_INGCO / GUDANG-B = 20
- AS_SMI   / GUDANG-C = 5
Total sistem = 35

Checker menghitung fisik gabungan = 33.
Admin dapat menentukan misalnya:
- GUDANG-A = 10 -> COCOK
- GUDANG-B = 18 -> KURANG 2
- GUDANG-C = 5  -> COCOK
Total alokasi = 33, sehingga validasi dapat disimpan.

FILE DATABASE BARU
- sample_cycle_warehouses
  Menyimpan daftar database/gudang yang masuk ke satu periode.
- sample_cycle_item_stocks
  Menyimpan snapshot stok dan hasil alokasi fisik per item per gudang.
- sample_cycle_items ditambah:
  validated_by, validated_at, validation_note.

KOMPATIBILITAS
Migration baru otomatis membuat 1 warehouse row untuk periode WAREHOUSE lama yang sebelumnya hanya punya satu gudang, kemudian memindahkan snapshot system_qty item lama ke tabel detail stok per gudang. Tidak menghapus data lama.

CARA INSTALL
Copy isi folder update ini ke root project, contoh:
C:\xampp82\htdocs\opname
Pilih Replace / Overwrite.

Lalu jalankan:
  composer dump-autoload
  php artisan optimize:clear
  php artisan migrate
  php artisan route:list --path=warehouse

Versi ini tetap NO-VITE.
Tidak perlu menjalankan npm install / npm run build.

PENGECEKAN YANG SUDAH DILAKUKAN
- php -l seluruh PHP file yang berubah: OK
- php artisan route:list: OK (76 routes pada project test)
- compileString 4 Blade Warehouse Sampling: OK
