OPNAME - SAMPLING FILTER UI UPDATE
==================================

Target:
C:\xampp82\htdocs\opname

Patch ini hanya merapikan UI filter:
http://127.0.0.1:8000/admin/sampling

Tidak mengubah controller, query report, database, atau migration.

FILE YANG DICOPY
----------------
resources\views\admin\sampling\index.blade.php
resources\css\app.css

File test bersifat opsional:
tests\static\sampling_filter_ui_contract.php

CARA PASANG
-----------
1. Backup dua file existing.
2. Extract ZIP.
3. Copy folder resources ke:
   C:\xampp82\htdocs\opname
   lalu pilih Replace.
4. Copy tests bila ingin menjalankan static verification.

Sesudah copy jalankan:

cd /d C:\xampp82\htdocs\opname
npm install
npm run build

C:\xampp82\php\php.exe artisan optimize:clear
C:\xampp82\php\php.exe artisan config:cache
C:\xampp82\php\php.exe artisan view:cache

Kemudian Ctrl+F5 pada browser.

UI BARU
-------
- Card filter lebih rapi dan terstruktur.
- Periode menjadi satu grup.
- Desktop 3 kolom, tablet 2 kolom, mobile 1 kolom.
- Warehouse searchable.
- User Gerai searchable.
- Database / Hasil seragam.
- Lokasi / Rak dan Cari Item lebih lebar dan jelas.
- Reset secondary dan Terapkan Filter primary.
- Logika filter existing tidak diubah.
