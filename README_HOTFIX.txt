OPANME - HOTFIX IMPORT XLSX
Tanggal: 2026-09-04

ROOT CAUSE
PHP menyimpan file upload ke path sementara seperti /tmp/phpABC123 tanpa ekstensi .xlsx.
SimpleSpreadsheetReader membuka path tersebut memakai PharData.
PharData menentukan format ZIP/XLSX dari ekstensi nama file, sehingga file valid tetap ditolak.

FIX
Jika path upload tidak memiliki ekstensi .xlsx/.zip, reader menyalin file ke temporary file berakhiran .xlsx, membaca file tersebut, lalu menghapus file temporary.

CARA PASANG
1. Copy folder app dan tests dari hotfix ini ke root project opanme (overwrite file yang sama).
2. Jalankan:
   php artisan optimize:clear
3. Test:
   php artisan test --filter=SimpleSpreadsheetReaderTest
4. Upload ulang file Excel yang sama.

Tidak ada migration database pada hotfix ini.
