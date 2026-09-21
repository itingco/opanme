# Warehouse Check Update v4 - Unified Dashboard + Checker Scanner Fix

Patch ini **cumulative** dari update Good Transfer, Admin Qty Override, dan Unified New Check sebelumnya.
Copy isi folder patch ke root Laravel project, misalnya:

`C:\xampp82\htdocs\Checker`

## Perubahan v4

### 1. Tombol scan checker kembali muncul untuk draft lama

Pada versi sebelumnya, draft yang dibuat saat role `admin` masih diperbolehkan membuat check dapat tetap menyimpan `checker_user_id = Administrator`.
Akibatnya ketika draft dibuka oleh user role `checker`, halaman masuk mode lihat saja dan scanner tidak muncul.

Sekarang:

- jika draft dimiliki user non-checker lama (misalnya Administrator), checker yang membuka draft akan mengambil ownership session;
- nama Checker pada detail ikut berubah ke checker yang mengambil session;
- scanner USB/manual/kamera, Supervisor Override, dan Final Save menjadi aktif;
- draft yang sudah dimiliki **checker lain** tidak akan diambil alih otomatis.

Perbaikan ini berlaku untuk:

- Invoice Check
- Good Transfer Check

### 2. Dashboard Invoice + Good Transfer digabung

Menu atas tidak lagi memisahkan `Invoice Dashboard` dan `Good Transfer`.
Sekarang hanya ada:

- `Dashboard`
- `New Check` (role checker)
- menu khusus Supervisor/Admin sesuai role

Dashboard menampilkan Invoice dan Good Transfer dalam **satu tabel** dengan kolom:

- Jenis Dokumen
- Nomor Dokumen
- Company
- Customer / Perpindahan Gudang
- Picker
- Checker
- Waktu mulai/selesai
- Salah Scan
- Status

Filter tersedia untuk:

- Nomor Dokumen
- Jenis Dokumen
- Company
- Status

URL lama `/good-transfers` tetap kompatibel dan diarahkan ke Dashboard dengan filter Good Transfer.

### 3. New Check tetap satu halaman

`New Check` tetap menggunakan satu form untuk:

- Invoice
- Good Transfer

## Fitur yang tetap dipertahankan

1. Hanya role `checker` yang dapat scan dan Final Save.
2. Perbaikan double-scan kamera tetap aktif.
3. Invoice dan Good Transfer tetap menggunakan service/logic masing-masing.
4. Good Transfer mengambil UOM dari `UOMLevel -> IC_Items.UOMID1..4 -> IC_UOM.UOMCode`.
5. Final Invoice mengisi `AR_Invoices.CheckedBy` dan `CheckedDateTime`.
6. Final Good Transfer mengisi `IC_Mutations.CheckedBy` dan `CheckedDateTime`.
7. Admin Qty Override tetap tersedia untuk Invoice dan Good Transfer menggunakan OTP.
8. Admin tetap tidak dapat scan atau Final Save.

## SQL

Untuk perubahan v4 ini **tidak ada SQL baru**.

Jika patch sebelumnya belum pernah dipasang, jalankan pada `DB_AppHub`:

1. `sql/03_good_transfer_and_scan_fix.sql`
2. `sql/05_admin_qty_override.sql`

Jika sudah pernah dijalankan, tidak perlu dijalankan ulang.

## Setelah copy file

```powershell
cd C:\xampp82\htdocs\Checker
composer dump-autoload
php artisan optimize:clear
```

## Test lokal yang disarankan

1. Login sebagai `Antoni Checker` / user role `checker`.
2. Buka draft lama yang sebelumnya menunjukkan Checker = Administrator.
3. Pastikan nama Checker berubah ke user checker yang login dan area scanner muncul.
4. Test scan manual + kamera.
5. Kembali ke Dashboard dan pastikan Invoice + Good Transfer muncul dalam satu tabel.
6. Pastikan menu atas tidak lagi mempunyai menu terpisah Invoice Dashboard / Good Transfer.
7. Test `New Check` untuk Invoice dan Good Transfer.
8. Login Admin dan pastikan Qty Override + OTP tetap dapat digunakan.
