OPNAME - FIX IMPORT RATIO SQL SERVER 2100 PARAMETER
=================================================

Masalah:
SQLSTATE[IMSSP]: Tried to bind parameter number 2101.
SQL Server maksimal 2100 bound parameters per statement.

Penyebab:
RatioImportService melakukan satu MERGE/upsert besar ke dbo.ItemRatio.

Fix:
- Lookup ItemCode existing: chunk 500.
- Upsert ItemRatio: chunk 500.
- Tiap baris hanya mengirim ItemCode, UOM, Ratio.
- Maksimum binding per upsert: 500 x 3 = 1.500.
- created_at / updated_at dibuang dari payload karena dbo.ItemRatio
  Anda hanya memiliki id, ItemCode, UOM, Ratio.
- Tidak ada migration.
- Tidak ada perubahan struktur database.
- Tidak ada perubahan template Excel/CSV.

TARGET:
C:\xampp82\htdocs\opname

FILE PRODUKSI YANG DIGANTI:
app\Services\RatioImportService.php

CARA PASANG:
1. Backup file lama:
   C:\xampp82\htdocs\opname\app\Services\RatioImportService.php

2. Copy file dari ZIP ke project dengan struktur folder yang sama.

3. Jalankan:
   cd /d C:\xampp82\htdocs\opname
   C:\xampp82\php\php.exe artisan optimize:clear

4. Test upload Ratio lagi.

TEST OPSIONAL:
C:\xampp82\php\php.exe tests\static\ratio_import_batching_contract.php

Expected:
ratio import batching contract: PASS (lookup=500, upsert=500, max_upsert_bindings=1500)

UNTUK SERVER LINUX SETELAH PUSH:
cd /var/www/opname
git pull
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan view:cache

Tidak perlu npm build karena fix ini hanya backend PHP.
