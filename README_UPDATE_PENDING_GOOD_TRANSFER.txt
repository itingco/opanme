UPDATE SAMPLING GUDANG - PENDING GOOD TRANSFER BELUM RECEIVED
Tanggal: 29-09-2026

TUJUAN
Menambahkan Good Transfer yang belum diterima ke perhitungan validasi Sampling Gudang.
Gudang yang sedang dicek selalu dicocokkan sebagai DestinationWarehouseID.

RULE ERP
- Sumber: IC_MutationDetails + IC_Mutations
- Join warehouse asal/tujuan dan IC_Items/IC_UOM
- MUT.ReceivedBy IS NULL
- MUT.DestinationWarehouseID = gudang sampling yang sedang divalidasi
- DET.ItemID = item sampling
- Tidak dibatasi tanggal, sesuai query/rule yang diberikan.

RUMUS VALIDASI
Stok Validasi = Snapshot Stok - Sales Invoice Hari Cek + Pending Good Transfer Belum Received

UOM
- UOMLevel 1: ratio 1
- UOMLevel > 1: dikonversi ke smallest UOM memakai ItemRatio / BarcodeService
- Jika ratio tidak tersedia, GT tersebut tidak ditambahkan dan warning ditampilkan di halaman validasi.

TAMPILAN VALIDASI
Per gudang menampilkan:
- Snapshot Stok
- Sales Invoice (-)
- Pending GT (+)
- Stok Validasi
- Proporsi Stok
- Fisik Proporsional
- Selisih
- Detail nomor Sales Invoice
- Detail nomor Good Transfer, tanggal, asal -> tujuan, qty dokumen dan qty smallest

HISTORY
Data berikut disimpan ke sample_cycle_item_stocks:
- pending_transfer_qty
- pending_transfer_details
- validation_system_qty

History Sampling, PDF, dan Excel Raw ikut menampilkan Pending GT dan Stok Validasi.

FILE BARU
- app/Services/WarehouseSamplingPendingTransferService.php
- database/migrations/2026_09_29_000018_add_pending_good_transfer_to_sampling_stocks.php

SETELAH COPY / OVERWRITE
cd C:\xampp82\htdocs\opname
composer dump-autoload
php artisan optimize:clear
php artisan migrate
php artisan route:list --path=warehouse

Lalu Ctrl + F5 pada browser.
Tetap NO-VITE / tidak perlu npm.
