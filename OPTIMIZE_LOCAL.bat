@echo off
setlocal EnableExtensions
title OPNAME - Optimized Setup
cd /d "%~dp0"

echo.
echo ============================================================
echo   OPNAME - INSTALL + OPTIMIZE
echo ============================================================
echo Project: %CD%
echo.

where composer >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Composer tidak ditemukan di PATH.
    pause
    exit /b 1
)

where npm >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Node.js / npm tidak ditemukan di PATH.
    pause
    exit /b 1
)

set "PHP=C:\xampp82\php\php.exe"
if not exist "%PHP%" set "PHP=php"

if not exist ".env" (
    if exist ".env.example" (
        copy /Y ".env.example" ".env" >nul
        echo [INFO] .env dibuat dari .env.example.
        echo [INFO] Isi koneksi database jika ini instalasi baru.
    )
)

echo.
echo [1/6] Composer runtime install...
call composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
if errorlevel 1 goto :fail

echo.
echo [2/6] Frontend dependencies...
if exist package-lock.json (
    call npm ci
) else (
    call npm install
)
if errorlevel 1 goto :fail

echo.
echo [3/6] Build frontend production...
call npm run build
if errorlevel 1 goto :fail

echo.
echo [4/6] Hapus node_modules setelah build agar tetap ringan...
if exist node_modules rmdir /S /Q node_modules

echo.
echo [5/6] Bersihkan cache lama...
"%PHP%" artisan optimize:clear
if errorlevel 1 goto :fail

echo.
echo [6/6] Cache config dan Blade...
"%PHP%" artisan config:cache
if errorlevel 1 goto :fail
"%PHP%" artisan view:cache
if errorlevel 1 goto :fail

echo.
echo ============================================================
echo   SELESAI
echo ============================================================
echo Jalankan:
echo   "%PHP%" artisan serve
echo.
pause
exit /b 0

:fail
echo.
echo [ERROR] Proses berhenti. Lihat pesan di atas.
pause
exit /b 1
