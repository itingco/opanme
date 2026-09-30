UPDATE LABEL - HANYA ADMIN_GUDANG

Perubahan:
1. Menu Label hanya tampil untuk role ADMIN_GUDANG.
2. Role ADMIN, ADMIN_GERAI, CHECKER_GERAI, CHECKER_GUDANG, GERAI legacy, dan CHECKER legacy tidak melihat menu Label.
3. Route /label, /label/items, dan /label/preview dikunci dengan middleware role:ADMIN_GUDANG.
4. Jika role lain mencoba akses URL /label secara langsung, Laravel akan mengembalikan 403.

File yang berubah:
- resources/views/layouts/app.blade.php
- routes/web.php

Setelah overwrite:
composer dump-autoload
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

Tes:
php artisan route:list --path=label

Lalu login sebagai ADMIN_GUDANG dan refresh Ctrl+F5.
