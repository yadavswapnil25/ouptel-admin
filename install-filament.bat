@echo off
echo Installing Filament Admin Panel...
echo.

echo Step 1: Installing Filament...
composer require filament/filament:"^3.3" -W

echo.
echo Step 2: Installing Filament Panels...
php artisan filament:install --panels

echo.
echo Step 3: Publishing Assets...
php artisan vendor:publish --tag=filament-assets

echo.
echo Step 4: Clearing Cache...
php artisan config:clear
php artisan route:clear
php artisan cache:clear

echo.
echo Step 5: Creating Admin User...
php artisan db:seed --class=AdminUserSeeder

echo.
echo Installation Complete!
echo You can now access the admin panel at: http://127.0.0.1:8000/admin
echo Login with: admin@ouptel.com / admin123
echo.
pause






