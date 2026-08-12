@echo off
title BizLedger Laravel Setup
cd /d "%~dp0"
where php >nul 2>nul || (echo PHP is not available in PATH. Install PHP 8.3+ or XAMPP and add PHP to PATH.& pause & exit /b 1)
if not exist .env copy .env.example .env
where composer >nul 2>nul
if errorlevel 1 (
  if exist composer.phar (php composer.phar install) else (echo Composer is not installed.& pause & exit /b 1)
) else (
  composer install
)
php artisan key:generate
php artisan migrate --seed
start "" http://127.0.0.1:8000
php artisan serve
pause
