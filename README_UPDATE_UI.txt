UPDATE UI SAMPLING GUDANG - 23 SEPTEMBER 2026

Perubahan:
1. Search gudang tidak lagi digabung. Setiap database (AS_INGCO / AS_SMI) mempunyai search box sendiri.
2. AS_INGCO dan AS_SMI tetap sejajar horizontal pada desktop.
3. Checkbox dan nama/kode gudang diperbaiki agar teks tidak tertutup atau terpotong.
4. Nama gudang panjang sekarang wrap ke baris berikutnya.
5. Area validasi stok per gudang diubah menjadi kartu horizontal ke kanan.
6. Jika gudang banyak, bagian validasi cukup digeser/scroll horizontal.
7. Tampilan hasil validasi dan snapshot stok juga menggunakan pola horizontal yang sama.

Cara pasang:
- Copy folder public dan resources dari ZIP ke root project C:\xampp82\htdocs\opname
- Pilih Replace/Overwrite.
- Jalankan:
  php artisan view:clear
  php artisan optimize:clear
- Refresh browser dengan Ctrl + F5.

Tidak ada migration database dan tidak membutuhkan Vite/NPM.
