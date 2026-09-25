UPDATE SAMPLING GUDANG - PENYESUAIAN SALES INVOICE
Tanggal: 25 September 2026

TUJUAN
Pada saat Admin Gudang melakukan validasi Qty Sampling, stok sistem per gudang sekarang
akan dikurangi Qty barang yang keluar melalui Sales Invoice pada HARI CHECKER melakukan
pengecekan fisik.

SUMBER ERP
- Header : AR_Invoices
- Detail : AR_InvoiceDetails
- Join   : AR_Invoices.InvoiceID = AR_InvoiceDetails.InvoiceID
- Filter : InvoiceDate + ItemID + WarehouseID
- Qty    : AR_InvoiceDetails.Quantity

TANGGAL YANG DIGUNAKAN
Tanggal diambil dari sample_cycle_items.checked_at.
Artinya jika checker melakukan pengecekan 25/09/2026 dan Admin baru validasi 26/09/2026,
Sales Invoice yang dihitung tetap tanggal 25/09/2026.

CONTOH
Snapshot stok G-TOKO       : 10
Sales Invoice tanggal cek  : 2
Stok setelah Sales Invoice : 8

Nilai 8 yang digunakan sebagai stok pembanding dan sebagai dasar pembagian proporsional
Qty Fisik Total checker.

Jika item berada pada beberapa gudang, pengurangan dilakukan PER GUDANG:
- Database harus sama
- ItemID harus sama
- WarehouseID harus sama
- Tanggal Invoice harus sama dengan tanggal checker

TAMPILAN VALIDASI ADMIN
Setiap card gudang menampilkan:
1. Snapshot Stok
2. Qty Sales Invoice Hari Cek
3. Stok Setelah Sales
4. Proporsi Stok
5. Fisik Proporsional
6. Selisih
7. Nomor Sales Invoice yang menyebabkan pengurangan (jika ada)

Saat tombol validasi ditekan, Sales Invoice dibaca ulang dari ERP agar angka yang disimpan
merupakan angka terbaru pada saat validasi.

AUDIT / HISTORY
Data Sales Invoice juga disimpan ke sample_cycle_item_stocks sehingga periode yang sudah
CLOSED tidak tergantung data ERP live lagi.
Field baru:
- sales_invoice_date
- sales_invoice_qty
- adjusted_system_qty
- sales_invoice_details

History Sampling, PDF, dan Excel Raw juga sudah menggunakan Stok Setelah Sales untuk
perhitungan selisih. Excel Raw menambahkan tanggal, qty, dan nomor Sales Invoice.

INSTALL
1. Copy seluruh isi folder update ke root project, contoh:
   C:\xampp82\htdocs\opname
2. Replace / overwrite file yang diminta.
3. Jalankan:

   composer dump-autoload
   php artisan optimize:clear
   php artisan migrate
   php artisan route:list --path=warehouse

4. Browser: Ctrl + F5

Tidak perlu NPM / Vite.

CATATAN
Perhitungan menggunakan AR_InvoiceDetails.Quantity secara langsung seperti query acuan.
