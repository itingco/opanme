OPNAME - UPDATE TANPA VITE / NPM
Tanggal: 23-09-2026

Tujuan:
- Tidak lagi memakai @vite pada Blade.
- Tidak perlu npm install / npm run build untuk menjalankan CSS/JS aplikasi.
- CSS dan JS dibaca langsung dari public/assets.
- ZXing barcode scanner disimpan lokal di public/assets/vendor, bukan CDN.
- Paket ini juga menyertakan fix route AuthController sebelumnya.

CARA PASANG
1. Backup project lokal.
2. Copy seluruh isi folder update ini ke root project, contoh:
   C:\xampp82\htdocs\opname
3. Pilih Replace/Overwrite untuk file yang sama.
4. Jalankan:
   composer dump-autoload
   php artisan optimize:clear
   php artisan migrate
   php artisan route:list
5. Buka aplikasi / jalankan php artisan serve jika memang memakai artisan serve.

TIDAK PERLU MENJALANKAN:
- npm install
- npm run build
- npm run dev

CATATAN
- package.json, package-lock.json, vite.config.js, resources/css dan resources/js lama boleh tetap ada.
  File-file tersebut tidak lagi dipanggil oleh Blade pada versi ini.
- Jika ingin membersihkannya nanti, boleh dilakukan terpisah setelah aplikasi dipastikan stabil.
- Jangan hapus public/assets karena itulah asset aktif versi tanpa Vite.
