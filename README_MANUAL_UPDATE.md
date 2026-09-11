# OPANME - Manual Patch Sampling Multi Warehouse + In Transit

Patch dibuat berdasarkan repository `itingco/opanme` branch `main` pada commit/tree:
`823f1eae06e4ff8b6ed6583d826b4c0c695b8b48`

## Fitur yang ditambahkan

### 1. Informasi stok Gudang In Transit saat Sampling Check
- Ketika barcode berhasil dibaca, aplikasi tetap mengambil `Smallest On Hand` dari gudang yang sedang disampling sebagai `Stok Sistem`.
- Secara tambahan aplikasi mencari item yang sama pada semua warehouse ERP dengan **kode atau nama warehouse yang mengandung kata `TRANSIT`**.
- Hanya saldo `Smallest On Hand > 0` yang ditampilkan.
- Jika tidak ada saldo In Transit, bagian In Transit tidak muncul sama sekali.
- Saldo In Transit hanya informasi tambahan dan **tidak ikut mengubah** perhitungan MATCH/MISMATCH stok gudang sampling.

### 2. Filter laporan Sampling dapat memilih multiple warehouse
- Filter Gudang sekarang menerima `erp_warehouse_ids[]`.
- Bisa memilih Gudang A, B, C sekaligus.
- Data tabel hanya menampilkan hasil sampling dari warehouse yang dipilih.
- Link lama yang masih menggunakan `erp_warehouse_id=<id>` tetap didukung.

### 3. Coverage dihitung per warehouse
Contoh bila memilih Gudang A, B, C:
- Coverage Gudang A = sampled item A / target item stok > 0 Gudang A.
- Coverage Gudang B = sampled item B / target item stok > 0 Gudang B.
- Coverage Gudang C = sampled item C / target item stok > 0 Gudang C.
- Jika memilih lebih dari satu gudang, halaman juga menampilkan Coverage Gabungan.
- Periode sampling mengikuti `date_from` sampai `date_to`.
- Target stok masing-masing gudang menggunakan snapshot ERP pada `date_to`, sama seperti logika lama.

### 4. Export PDF
- Filter multiple warehouse ikut diterapkan ke PDF.
- PDF menampilkan coverage masing-masing warehouse yang dipilih.

## File yang harus ditimpa

Copy isi ZIP ini ke **root project Laravel OPANME** dan izinkan overwrite file berikut:

1. `app/Services/ErpStockService.php`
2. `app/Services/SampleCheckingService.php`
3. `app/Services/SampleReportService.php`
4. `app/Services/SampleReportPdfService.php`
5. `app/Http/Controllers/Admin/SamplingReportController.php`
6. `resources/views/gerai/scan.blade.php`
7. `resources/views/admin/sampling/index.blade.php`
8. `resources/js/sampling.js`

Tidak ada migration/database schema baru.

## Setelah overwrite

Jalankan dari root project:

```bash
php artisan optimize:clear
npm run build
```

Jika `node_modules` belum tersedia / dependencies berubah di server:

```bash
npm ci
npm run build
```

Tidak perlu `php artisan migrate` untuk patch ini.

## Verifikasi cepat

1. Login sebagai user Gerai dan buka Sampling Check.
2. Scan item yang mempunyai stok pada warehouse bernama/kode `IN TRANSIT` / mengandung `TRANSIT`.
3. Modal harus menampilkan blok `Stok tersedia di Gudang In Transit` beserta warehouse dan qty.
4. Scan item yang tidak mempunyai stok In Transit. Blok tersebut tidak boleh muncul.
5. Login Admin, buka Laporan Sampling.
6. Pilih database ERP.
7. Pilih 2 atau lebih Gudang menggunakan Ctrl+klik (Windows) / Cmd+klik (Mac).
8. Terapkan filter.
9. Pastikan tabel hanya berisi gudang terpilih dan muncul kartu Coverage untuk masing-masing gudang.
10. Coba Export PDF dan pastikan coverage per gudang ikut tercetak.

## Catatan penting In Transit

Deteksi warehouse In Transit berada di:
`app/Services/ErpStockService.php -> findItemInTransitWarehouses()`

Saat ini kriterianya:

```php
str_contains(strtoupper($warehouseCode.' '.$warehouseName), 'TRANSIT')
```

Jadi nama seperti `IN TRANSIT`, `G-INTRANSIT`, `TRANSIT MEDAN`, dan sejenisnya otomatis terbaca.
