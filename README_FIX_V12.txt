FIX V12 - Route not defined
===========================
Problem: Route [checker.camera-manual.store] not defined; V12 view renders before the named routes were added.

1) Unzip all contents of this ZIP into C:\xampp82\htdocs\opanme (same directory as artisan).
2) Open PowerShell there and run:
   php FIX_V12_ROUTES.php
   php artisan optimize:clear
   php artisan migrate
   php artisan route:list --name=camera
3) Verify these route names exist:
   checker.camera-manual.store
   checker.camera-ai.read
   warehouse.legacy.camera-manual.store
   warehouse.legacy.camera-ai.read
4) Refresh browser Ctrl+F5.

IMPORTANT: This patch doesn't overwrite existing PHP controllers, JavaScript, or user data. It restores missing V12 support files only. It inserts the missing routes and creates *.before-fix-v12-routes backups.
Migration runs only if needed. Do not edit existing database tables manually.
If route:list says the route does not exist, check the FIX_V12_ROUTES.php output, the right project directory, and routes/warehouse_sampling.php inclusion.
