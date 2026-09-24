UPDATE UI SAMPLING GUDANG - DATABASE SEJAJAR + SEARCH GUDANG
Tanggal: 23 September 2026

Perubahan:
1. AS_INGCO dan AS_SMI sekarang tampil sejajar horizontal pada desktop.
2. Tidak ada lagi satu database yang tampil panjang di bawah database lainnya.
3. Masing-masing database mempunyai scroll daftar gudang sendiri jika jumlah gudang banyak.
4. Ditambahkan search bar "Cari kode atau nama gudang".
5. Search bekerja sekaligus pada semua database.
6. Counter per database menampilkan jumlah gudang yang sedang tampil dari total gudang.
7. "Pilih tampil" akan memilih hanya gudang yang sedang terlihat berdasarkan hasil pencarian.
8. Pilihan checkbox yang sudah dipilih tidak hilang saat search berubah.
9. Responsive: pada mobile, database kembali ditumpuk vertikal agar tetap mudah digunakan.

INSTALL:
Copy/overwrite isi folder ini ke root project opname.

Kemudian jalankan:
php artisan view:clear
php artisan optimize:clear

Tidak ada migration.
Tidak perlu npm / Vite.
Lakukan Ctrl + F5 pada browser setelah update.
