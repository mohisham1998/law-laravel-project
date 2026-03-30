@echo off
echo Running Laravel migrations...
php artisan migrate --force
echo Done.
pause
