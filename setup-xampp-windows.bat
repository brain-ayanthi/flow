@echo off
setlocal
set "ROOT=%~dp0"
set "APP=%ROOT%laravel-app"
if not defined XAMPP_DIR set "XAMPP_DIR=C:\xampp"

if not exist "%XAMPP_DIR%\php\php.exe" (
  echo XAMPP PHP was not found at "%XAMPP_DIR%\php\php.exe".
  echo Edit XAMPP_DIR near the top of this file, or set XAMPP_DIR before running it.
  exit /b 1
)
set "PATH=%XAMPP_DIR%\php;%PATH%"
where composer >nul 2>nul || (echo Composer not found. Install Composer and select XAMPP's php.exe when prompted.& exit /b 1)
if not exist "%APP%\artisan" (
  echo Laravel artisan was not found in "%APP%". Re-extract the complete PressFlow bundle.
  exit /b 1
)
if not exist "%APP%\composer.json" (
  echo composer.json was not found in "%APP%".
  exit /b 1
)
if not exist "%APP%\.env" (
  if not exist "%APP%\.env.example" (
    echo Neither .env nor .env.example was found in "%APP%".
    exit /b 1
  )
  copy "%APP%\.env.example" "%APP%\.env" >nul
  if errorlevel 1 (echo Could not create .env from .env.example.& exit /b 1)
  echo Created a local .env from .env.example. Review DB credentials before migrating.
) else (
  echo Preserving existing laravel-app\.env.
)

rem Create Laravel writable/cache directories; some Windows extractors skip dotfiles that preserve empty folders.
if not exist "%APP%\storage\framework\views" mkdir "%APP%\storage\framework\views"
if not exist "%APP%\storage\framework\cache\data" mkdir "%APP%\storage\framework\cache\data"
if not exist "%APP%\storage\framework\sessions" mkdir "%APP%\storage\framework\sessions"
if not exist "%APP%\storage\framework\testing" mkdir "%APP%\storage\framework\testing"
if not exist "%APP%\storage\logs" mkdir "%APP%\storage\logs"
if not exist "%APP%\bootstrap\cache" mkdir "%APP%\bootstrap\cache"

echo Using XAMPP PHP:
php -v
if errorlevel 1 exit /b 1
pushd "%APP%"
echo Installing Laravel and Sanctum dependencies into vendor\ ...
composer install
if errorlevel 1 (popd & exit /b 1)
findstr /R /C:"^APP_KEY=." .env >nul
if errorlevel 1 (
  echo Generating APP_KEY because the existing value is empty.
  php artisan key:generate
  if errorlevel 1 (popd & exit /b 1)
) else (
  echo Existing APP_KEY preserved.
)
php artisan config:clear
if errorlevel 1 (popd & exit /b 1)
popd

echo.
echo Laravel app is ready at: "%APP%"
echo For a NEW empty database only: start MySQL, create pressflow in phpMyAdmin, then run:
echo   C:\xampp\php\php.exe artisan migrate --seed
echo For an EXISTING shop database: preserve its data and .env; run artisan migrate only.
echo Optional public logo support:
echo   C:\xampp\php\php.exe artisan storage:link
echo.
echo Review laravel-app\.env before migrating; ensure its DB credentials match your local MySQL setup.
echo For Apache, point VirtualHost DocumentRoot to laravel-app\public only; see backend\docs\XAMPP_SETUP_SI.md.
exit /b 0
