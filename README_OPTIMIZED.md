# OPNAME - Optimized Full Source

Dibuat langsung dari `opname(2).zip` yang Anda upload.

## Scope final
- Tidak ada fitur Stock Aging dan tidak ada route `/stock-aging`.
- User role **GERAI** dapat diikat ke **maksimal 1 gudang**, atau tidak diikat sehingga bebas memilih gudang saat Sampling.
- Stock Opname, Sampling Gerai, Barcode, Ratio, User Management, dan integrasi ERP tetap dipertahankan.

## Yang dibuang agar ringan
- `vendor/`
- `node_modules/`
- `.git/`
- `.env`
- stale `public/build/`
- backup/patch/debug installer lama
- log Laravel
- session/cache/compiled Blade lama
- `_stock_aging_source`
- backup AppHub lama

## Target Anda
`C:\xampp82\htdocs\opname`

Copy isi folder `opname` dari ZIP ke lokasi tersebut. Paket ini tidak menyertakan `.env`, jadi `.env` existing Anda tidak tertimpa.

Sesudah copy, double-click:
`OPTIMIZE_LOCAL.bat`

Script melakukan:
1. `composer install --no-dev --optimize-autoloader`
2. `npm ci`
3. `npm run build`
4. menghapus `node_modules` setelah build
5. `php artisan optimize:clear`
6. `php artisan config:cache`
7. `php artisan view:cache`

Kemudian jalankan:
`C:\xampp82\php\php.exe artisan serve`

## Binding User Gerai
Admin → User → pilih role `GERAI`.

- Jika pilih Database ERP + Gudang: user hanya terikat ke gudang tersebut.
- Jika binding dikosongkan: user bebas memilih gudang saat membuat Sampling Cycle.

Model menyimpan satu `erp_warehouse_id`, jadi satu User GERAI tidak bisa diikat ke banyak gudang sekaligus.
