OPNAME - FIX IMPORT BARCODE SQL SERVER 2100 PARAMETER
===================================================

Masalah:
SQLSTATE[IMSSP]: Tried to bind parameter number 2101.
SQL Server maksimal 2100 bound parameters per statement.

Fix:
- Lookup barcode existing: diproses per 1.000 barcode.
- Upsert ke DB_AppHub: diproses per 500 barcode.
- Setiap upsert hanya mengirim ItemCode, Barcode, UOM.
- Maksimum binding upsert = 500 x 3 = 1.500 (aman di bawah 2.100).
- Tidak ada perubahan database/migration.
- Tidak ada perubahan format file import.
- Counter created / updated / skipped / failed tetap dipertahankan.

TARGET LOCAL:
C:\xampp82\htdocs\opname

FILE PRODUKSI YANG DIGANTI:
app\Services\BarcodeImportService.php

CARA COPY MANUAL:
1. Backup file lama:
   app\Services\BarcodeImportService.php

2. Copy file dari ZIP ke project dengan struktur folder yang sama.

3. Jalankan:
   C:\xampp82\php\php.exe artisan optimize:clear

4. Test upload barcode lagi.

TEST OPSIONAL:
C:\xampp82\php\php.exe tests\static\barcode_import_batching_contract.php

Expected:
barcode import batching contract: PASS (lookup=1000, upsert=500, max_upsert_bindings=1500)

UNTUK SERVER LINUX:
cd /var/www/opname
git pull
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan view:cache

Tidak perlu npm build untuk patch ini karena hanya file PHP backend yang berubah.
