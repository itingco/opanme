# Stock Opname Web

Aplikasi Laravel mobile-first untuk stock opname multi-warehouse dengan multi-checker, barcode ERP, master UOM ratio, lokasi/rak free text, opening/closing snapshot, dan summary variance.

## Stack yang disamakan dengan project existing

- **Laravel 10**
- **PHP ^8.1**; **PHP 8.2 direkomendasikan** untuk menyamai environment `XAMPP82`
- PostgreSQL untuk database aplikasi
- Microsoft SQL Server untuk ERP AS_INGCO / AS_SMI (read-only)
- Blade + responsive CSS
- Vite 4
- Camera barcode scanning via `@zxing/browser`

Project ini sengaja tidak lagi memakai Laravel 13 / PHP 8.3. Dengan requirement PHP `^8.1`, project dapat dijalankan di PHP 8.1 maupun PHP 8.2. Untuk environment Anda, gunakan PHP 8.2 agar konsisten dengan project yang berada di `xampp82`.

> Catatan Composer 2026: Laravel 10 sudah merupakan major lama dan Composer modern dapat memblokir dependency karena security advisory. Karena target project ini memang menyamakan stack existing, `composer.json` menyetel `audit.block-insecure=false` agar `composer install` tidak berhenti hanya karena blok advisory. Untuk production jangka panjang tetap disarankan melakukan upgrade major secara terencana.

## Fitur

### Admin
- Login role ADMIN.
- Kelola user ADMIN / CHECKER.
- Master UOM ratio berdasarkan `source_database + item_id + uom_level`.
- Cari barcode ERP untuk mengisi master ratio.
- Buat cycle `SO-YYYYMMDD-XXX`.
- Pilih AS_INGCO atau AS_SMI.
- Ambil warehouse aktif dari ERP.
- Satu cycle dapat memilih beberapa warehouse.
- Assign beberapa checker ke tiap warehouse.
- Start cycle dan capture opening stock dari `USP_Dashboard_SisaStokWarehouse`.
- Close cycle dan capture closing stock.
- Summary opening ERP vs scan fisik vs final fisik vs variance.
- Export Summary ke file Excel `.xlsx` mengikuti filter warehouse dan **Hanya selisih**.
- Manual override Admin setelah cycle CLOSED; hasil scan asli tetap immutable.
- Override wajib comment dan menyimpan admin + waktu perubahan untuk audit.
- Flag **net movement ERP** berdasarkan perubahan opening ke closing snapshot.
- Drill-down scan per item: checker, lokasi/rak, barcode, UOM, ratio used, waktu.

### Checker
- Hanya melihat cycle/warehouse yang ditugaskan dan masih OPEN.
- Lokasi/rak wajib dan free text.
- Camera mobile scanning.
- Scanner USB/Bluetooth keyboard dan input manual sebagai fallback.
- Satu scan = satu unit sesuai UOM barcode.
- Ratio master otomatis dikalikan dan disimpan sebagai physical quantity.
- Checker tidak menerima/menampilkan stock system, ratio, physical total, progress, atau variance.
- Setiap scan disimpan individual beserta checker + lokasi + timestamp.

## ERP Query Barcode

```sql
SELECT TOP (2)
    A.AliasCode,
    A.ItemID,
    A.UOMLevel,
    I.ItemCode,
    I.ItemName,
    U.UOMCode
FROM IC_Aliases A
LEFT JOIN IC_Items I ON I.ItemID = A.ItemID
LEFT JOIN IC_UOM U ON U.UOMID = CASE A.UOMLevel
    WHEN 1 THEN I.UOMID1
    WHEN 2 THEN I.UOMID2
    WHEN 3 THEN I.UOMID3
    WHEN 4 THEN I.UOMID4
END
WHERE A.AliasType = 'Barcode'
  AND A.AliasCode = ?;
```

Jika satu barcode ditemukan pada lebih dari satu item, scan ditolak agar tidak salah hitung.

## ERP Warehouse

```sql
SELECT WarehouseID, WarehouseCode, Name
FROM IC_Warehouses
WHERE Disabled = 0
ORDER BY DisplaySequence;
```

## 1. PHP yang digunakan

Gunakan **PHP 8.2**. Cek dengan:

```bash
php -v
```

Di Windows bila Anda memiliki beberapa XAMPP, pastikan terminal memakai PHP dari XAMPP82:

```powershell
C:\xampp82\php\php.exe -v
```

Extension yang dibutuhkan:

```text
pdo_pgsql
pgsql
sqlsrv
pdo_sqlsrv
```

Cek:

```bash
php -m
```

Untuk SQL Server di Windows, install Microsoft ODBC Driver serta extension `sqlsrv` dan `pdo_sqlsrv` yang sesuai dengan PHP 8.2, arsitektur x64/x86, dan mode Thread Safe yang digunakan PHP.

## 2. Buat PostgreSQL localhost

```sql
CREATE DATABASE stock_opname;
```

## 3. Install project

Windows PowerShell:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
npm install
npm run build
```

Linux:

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
npm run build
```

Edit `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=stock_opname
DB_USERNAME=postgres
DB_PASSWORD=PASSWORD_POSTGRES

ERP_HOST=IP_SQL_SERVER
ERP_PORT=1433
ERP_USERNAME=USER_READONLY
ERP_PASSWORD=PASSWORD
ERP_INGCO_DATABASE=AS_INGCO
ERP_SMI_DATABASE=AS_SMI
ERP_ENCRYPT=yes
ERP_TRUST_SERVER_CERTIFICATE=true
```

Kemudian:

```bash
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

## 4. User demo

```text
Admin
username: admin
password: admin123

Checker
username: checker1
password: checker123
```

Ganti password demo setelah testing.

## 5. Urutan testing

1. Login sebagai admin.
2. Menu **Ratio** → pilih database → scan/ketik satu barcode ERP → **Cari** → isi ratio → simpan.
3. Menu **Cycle** → **Buat Cycle** → pilih database dan beberapa warehouse.
4. Assign `checker1` ke satu atau beberapa warehouse.
5. Tekan **Start Stock Opname**.
6. Login checker dari browser/HP.
7. Pilih warehouse → isi lokasi/rak → mulai scan.
8. Scan barcode berulang. Barcode sama boleh masuk berkali-kali.
9. Ganti lokasi/rak dan lanjut scan.
10. Admin menekan **Selesai / Close Cycle**.
11. Buka **Summary** dan periksa variance serta net movement.
12. Setelah cycle CLOSED, Admin dapat mengisi **Manual Override** + comment bila diperlukan.
13. Gunakan **Export Excel** untuk mengunduh summary sesuai filter yang aktif.

## Camera Mobile dan HTTPS

Camera browser membutuhkan secure context. `localhost` dapat digunakan untuk development pada komputer yang sama, tetapi camera dari HP yang membuka `http://192.168.x.x:8000` dapat ditolak browser.

Untuk test camera dari HP gunakan HTTPS/reverse proxy HTTPS/domain internal HTTPS. Scanner USB/Bluetooth/manual tetap dapat digunakan tanpa camera.

## Net Movement

Karena sumber tabel histori movement ERP belum diberikan, versi ini menandai **net movement** dengan menjalankan USP stok kembali saat cycle ditutup:

```text
movement_qty = closing ERP stock - opening ERP stock
```

Ini menunjukkan perubahan stok neto selama opname, tetapi belum dapat mendeteksi dua transaksi yang saling meniadakan. Ketika sumber histori mutasi dengan timestamp tersedia, mekanisme dapat ditingkatkan menjadi audit movement penuh antara `started_at` dan `completed_at`.

## Struktur penting

```text
app/Services/ErpCatalogService.php       barcode + warehouse ERP
app/Services/ErpStockService.php         execute USP stock
app/Services/CycleSnapshotService.php    opening/closing snapshot
app/Services/CycleSummaryService.php     summary, override-aware final fisik & variance
app/Services/CycleSummaryExcelService.php export summary langsung ke XLSX tanpa package tambahan
app/Services/ScanService.php             validasi + simpan scan atomic
resources/views/checker/scan.blade.php   mobile scanner UI
resources/js/app.js                      camera + debounce + feedback
```

## Test

```bash
php artisan test
```

Project source tidak menyertakan `vendor/` dan `node_modules/`; dependency diinstall di environment lokal.

## Update: Export Excel + Manual Override

Setelah mengganti source ke versi ini, jalankan migration tambahan:

```bash
php artisan migrate
php artisan optimize:clear
```

Aturan override:

```text
Scan Fisik  = hasil scan checker asli, tidak diubah
Override    = koreksi manual Admin, optional
Final Fisik = Override jika ada, selain itu Scan Fisik
Variance    = Final Fisik - Opening ERP
```

Override hanya dapat dibuat ketika cycle berstatus `CLOSED`, quantity menggunakan smallest UOM, comment wajib, dan audit menyimpan Admin serta waktu perubahan. Jika override dihapus, Final Fisik otomatis kembali menggunakan hasil Scan Fisik.

Export Excel tidak membutuhkan package Composer tambahan. File `.xlsx` berisi Opening ERP, Scan Fisik, Override, Final Fisik, Variance, Closing ERP, Net Movement, jumlah scan, comment override, admin override, dan waktu override.

**Fix 2026-08-29:** urutan SpreadsheetML pada worksheet diperbaiki (`autoFilter` sebelum `mergeCells`) agar Microsoft Excel tidak melakukan repair saat file dibuka.

## Import Master UOM Ratio dari Excel

Menu **Ratio** mendukung import massal menggunakan template `.xlsx` bawaan aplikasi.

1. Klik **Download Template Excel**.
2. Isi kolom `Database`, `Barcode ERP`, dan `Ratio` pada sheet **Import Ratio**.
3. Database hanya `AS_INGCO` atau `AS_SMI`.
4. Barcode dicocokkan ke `IC_Aliases.AliasCode`; Item/UOM diambil otomatis dari ERP.
5. Jika kombinasi Database + Item + UOM sudah ada, ratio akan di-update.
6. Baris barcode tidak ditemukan/ambigu atau ratio tidak valid akan ditolak dan ditampilkan pada hasil import.

Format CSV dengan header yang sama juga didukung sebagai fallback.

## Override / Audit setelah Cycle CLOSED

Di halaman **Summary**, kolom **Override / Audit** berada langsung setelah kolom Item agar terlihat tanpa scroll ke ujung kanan. Tombol hanya mengizinkan perubahan nilai final setelah cycle berstatus `CLOSED`. Hasil scan checker tidak diubah; override menyimpan nilai koreksi, comment, admin, dan waktu update.
