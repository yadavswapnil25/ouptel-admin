@echo off
echo Setting up Ouptel Admin Panel...

echo.
echo 1. Generating application key...
php artisan key:generate

echo.
echo 2. Creating storage link...
php artisan storage:link

echo.
echo 3. Running migrations...
php artisan migrate

echo.
echo 4. Seeding admin user...
php artisan db:seed --class=AdminUserSeeder

echo.
echo 5. Clearing cache...
php artisan config:clear
php artisan cache:clear

echo.
echo Setup complete!
echo.
echo Admin Panel URL: http://localhost:8000/admin
echo Admin Email: admin@ouptel.com
echo Admin Password: admin123
echo.
echo To start the server, run: php artisan serve
echo.
pause






