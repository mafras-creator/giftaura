@echo off
REM ===================================================================
REM  GiftAura - backup of the database and the product photos.
REM
REM  How to use: double-click this file, or in PowerShell run
REM      C:\xampp\htdocs\giftaura\scripts\backup.bat
REM  MySQL must be running in XAMPP.
REM
REM  Creates, in the "backups" folder of the project:
REM      giftaura_db-YYYY-MM-DD_HHMM.sql   (all GiftAura tables and data)
REM      photos-YYYY-MM-DD_HHMM.zip        (uploaded product photos)
REM
REM  The backups contain customer data: keep them private, copy them to
REM  a USB drive or cloud storage, and never upload them to a website.
REM ===================================================================

REM ---- settings (change if your XAMPP is different) ------------------
set MYSQL_BIN=C:\xampp\mysql\bin
set DB_HOST=127.0.0.1
set DB_PORT=3307
set DB_NAME=giftaura_db
REM The XAMPP root account is used because it can read everything.
REM If you have set a root password, add  -p  after %DB_USER% below.
set DB_USER=root
REM ---------------------------------------------------------------------

cd /d "%~dp0.."
if not exist backups mkdir backups
if not exist backups\.htaccess (
    echo # Backups must never be reachable from a browser.> backups\.htaccess
    echo Require all denied>> backups\.htaccess
)

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmm"') do set STAMP=%%i

echo.
echo Backing up database %DB_NAME% ...
"%MYSQL_BIN%\mysqldump.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% --single-transaction --default-character-set=utf8mb4 --add-drop-table %DB_NAME% > "backups\giftaura_db-%STAMP%.sql"
if errorlevel 1 (
    echo.
    echo [FAIL] Database backup failed. Is MySQL started? Is the port %DB_PORT% correct?
    del "backups\giftaura_db-%STAMP%.sql" 2>nul
    pause
    exit /b 1
)
echo [OK]   backups\giftaura_db-%STAMP%.sql

echo Backing up product photos ...
powershell -NoProfile -Command "$f = Get-ChildItem 'public\uploads\products' -File | Where-Object { $_.Name -ne '.gitkeep' }; if (-not $f) { exit 2 }; Compress-Archive -Path $f.FullName -DestinationPath 'backups\photos-%STAMP%.zip' -Force; if ($?) { exit 0 } else { exit 1 }"
if errorlevel 2 (
    echo [OK]   No product photos to back up yet.
) else if errorlevel 1 (
    echo [FAIL] Photo backup failed - copy public\uploads\products by hand.
) else (
    echo [OK]   backups\photos-%STAMP%.zip
)

echo Backing up banner, category, logo and founder pictures ...
powershell -NoProfile -Command "$d = @('public\uploads\banners','public\uploads\categories','public\uploads\brand','public\uploads\people') | Where-Object { Test-Path $_ }; $f = Get-ChildItem $d -File | Where-Object { $_.Name -ne '.gitkeep' }; if (-not $f) { exit 2 }; Compress-Archive -Path $d -DestinationPath 'backups\pictures-%STAMP%.zip' -Force; if ($?) { exit 0 } else { exit 1 }"
if errorlevel 2 (
    echo [OK]   No banner or category pictures to back up yet.
) else if errorlevel 1 (
    echo [FAIL] Picture backup failed - copy public\uploads\banners, categories and brand by hand.
) else (
    echo [OK]   backups\pictures-%STAMP%.zip
)

if exist "storage\content\popular.json" copy /Y "storage\content\popular.json" "backups\popular-%STAMP%.json" >nul
if exist "storage\content\about.json" (
    copy /Y "storage\content\about.json" "backups\about-%STAMP%.json" >nul && echo [OK]   backups\about-%STAMP%.json
)
echo.
echo Backup finished. Copy the "backups" folder to a safe place (USB / cloud).
pause
