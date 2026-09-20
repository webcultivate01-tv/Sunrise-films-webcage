@echo off
REM ---------------------------------------------------------------------------
REM  Sunrise Films - one-shot database setup.
REM
REM  Sets the MySQL root password to the one already in .env (Mehar@26),
REM  loads the schema, seeds the bootstrap Admin, and runs the acceptance test.
REM
REM  You are prompted once for your CURRENT root password.
REM ---------------------------------------------------------------------------
setlocal
cd /d "%~dp0"
set MYSQL="C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"

echo.
echo  Enter your CURRENT MySQL root password at the prompt below.
echo.

%MYSQL% -u root -p -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'Mehar@26'; FLUSH PRIVILEGES;"
if errorlevel 1 goto badpass
echo  [1/8] root password is now Mehar@26

%MYSQL% -u root -pMehar@26 -e "CREATE DATABASE IF NOT EXISTS sunrise_films DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci"
if errorlevel 1 goto fail
for %%f in (database\*.sql) do (
    %MYSQL% -u root -pMehar@26 sunrise_films < "%%f"
    if errorlevel 1 goto fail
)
echo  [2/8] schema loaded into sunrise_films

php database\seed.php
if errorlevel 1 goto fail
echo  [3/8] admin seeded

php tests\auth_check.php
if errorlevel 1 goto fail
echo  [4/8] authentication acceptance tests passed

php tests\modules_check.php
if errorlevel 1 goto fail
echo  [5/8] photographer + employee management acceptance tests passed

php tests\work_management_check.php
if errorlevel 1 goto fail
echo  [6/8] work management acceptance tests passed

php tests\task_management_check.php
if errorlevel 1 goto fail
echo  [7/8] task management acceptance tests passed

php tests\monthly_salary_check.php
if errorlevel 1 goto fail
echo  [8/8] monthly salary acceptance tests passed

echo.
echo  ======================================================
echo   Done. Start the app with:
echo       php -S localhost:8000 -t public public/index.php
echo   Then open  http://localhost:8000/admin
echo.
echo   Email:    admin@gmail.com
echo   Password: admin123
echo  ======================================================
echo.
goto end

:badpass
echo.
echo  Could not sign in as root with the password you typed.
echo  That was your EXISTING root password, not the new one.
echo  Re-run this script and try again.
goto end

:fail
echo.
echo  A step failed - see the output above.

:end
endlocal
pause
