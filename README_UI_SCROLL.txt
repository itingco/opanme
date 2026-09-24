UPDATE UI SAMPLING GUDANG - SCROLL & LAYOUT
Tanggal: 23 September 2026

File yang berubah:
1. public/assets/css/warehouse-sampling.css
2. resources/views/warehouse_sampling/admin/index.blade.php

Perubahan:
- Daftar database/gudang punya scroll vertikal sendiri (max tinggi 360px desktop).
- Header tiap grup database tetap terlihat saat scroll.
- Daftar periode di kanan punya scroll vertikal sendiri.
- Header Daftar Periode dan pagination tetap terpisah dari area scroll.
- Card periode dibuat lebih compact.
- Scrollbar dibuat lebih halus/rapi.
- Layout responsive tetap kembali satu kolom di tablet/mobile.
- Tidak menggunakan Vite / npm build.

Cara pasang:
1. Copy isi folder update ke root project C:\xampp82\htdocs\opname
2. Replace file yang sama.
3. Jalankan:
   php artisan view:clear
   php artisan optimize:clear
4. Hard refresh browser: Ctrl + F5

Tidak ada migration database.
