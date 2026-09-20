@echo off
REM ===========================================================================
REM  Sunrise Films - reset the MySQL root password WITHOUT knowing the old one.
REM
REM  Use this when you have LOST / never knew the current root password.
REM  It uses MySQL's documented --init-file recovery, which needs the service
REM  to be restarted - so this script MUST be run as Administrator.
REM
REM  Right-click this file  ->  "Run as administrator".
REM
REM  It will:
REM    1. stop the MySQL84 service
REM    2. start a temporary mysqld that runs an ALTER USER on startup
REM       (sets root  ->  Mehar@26, matching .env)
REM    3. shut that temporary server down using the NEW password
REM    4. restart the MySQL84 service
REM    5. load the schema, seed the admin, run the acceptance tests
REM ===========================================================================
setlocal EnableExtensions
cd /d "%~dp0"

set "SERVICE=MySQL84"
set "BIN=C:\Program Files\MySQL\MySQL Server 8.4\bin"
set "MYSQLD=%BIN%\mysqld.exe"
set "MYSQLADMIN=%BIN%\mysqladmin.exe"
set "MYSQL=%BIN%\mysql.exe"
set "MYINI=C:\ProgramData\MySQL\MySQL Server 8.4\my.ini"
set "NEWPASS=Mehar@26"
set "INITSQL=%TEMP%\sunrise_reset_root.sql"

REM --- must be elevated -------------------------------------------------------
net session >nul 2>&1
if errorlevel 1 (
    echo.
    echo  This script must be run as Administrator.
    echo  Right-click reset-root-password.bat  ->  "Run as administrator".
    echo.
    pause
    exit /b 1
)

REM --- sanity checks ----------------------------------------------------------
if not exist "%MYSQLD%"     ( echo  mysqld.exe not found at "%MYSQLD%"     & pause & exit /b 1 )
if not exist "%MYSQLADMIN%" ( echo  mysqladmin.exe not found at "%MYSQLADMIN%" & pause & exit /b 1 )

echo.
echo  [1/6] Stopping %SERVICE% ...
net stop %SERVICE%
if errorlevel 1 ( echo  Could not stop %SERVICE%. & pause & exit /b 1 )

REM --- write the init file (runs as root on the temp server's startup) --------
> "%INITSQL%" echo ALTER USER 'root'@'localhost' IDENTIFIED BY '%NEWPASS%';
>> "%INITSQL%" echo FLUSH PRIVILEGES;

REM --init-file wants forward slashes in the path
set "INITSQL_FWD=%INITSQL:\=/%"

echo  [2/6] Starting a temporary server to apply the new password ...
start "SunriseTempMySQL" /min "%MYSQLD%" --defaults-file="%MYINI%" --init-file="%INITSQL_FWD%"

echo  [3/6] Waiting for the temporary server to accept the new password ...
set /a tries=0
:waitloop
"%MYSQLADMIN%" -u root -p%NEWPASS% -h 127.0.0.1 -P 3306 ping >nul 2>&1
if not errorlevel 1 goto isup
set /a tries+=1
if %tries% GEQ 30 (
    echo  Timed out waiting for the temporary server.
    echo  Attempting to restart the normal service so nothing is left broken ...
    "%MYSQLADMIN%" -u root -p%NEWPASS% -h 127.0.0.1 -P 3306 shutdown >nul 2>&1
    net start %SERVICE% >nul 2>&1
    del "%INITSQL%" >nul 2>&1
    pause
    exit /b 1
)
ping -n 2 127.0.0.1 >nul
goto waitloop

:isup
echo  [4/6] Password set. Shutting the temporary server down ...
"%MYSQLADMIN%" -u root -p%NEWPASS% -h 127.0.0.1 -P 3306 shutdown
del "%INITSQL%" >nul 2>&1

REM give the port a moment to free up
ping -n 3 127.0.0.1 >nul

echo  [5/6] Restarting %SERVICE% ...
net start %SERVICE%
if errorlevel 1 ( echo  Could not restart %SERVICE%. & pause & exit /b 1 )

REM --- from here it is identical to setup-db.bat's happy path ------------------
echo  [6/6] Loading schema, seeding admin, running acceptance tests ...
echo.
"%MYSQL%" -u root -p%NEWPASS% -e "CREATE DATABASE IF NOT EXISTS sunrise_films DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci"
if errorlevel 1 ( echo  Database create failed. & pause & exit /b 1 )
for %%f in (database\*.sql) do (
    "%MYSQL%" -u root -p%NEWPASS% sunrise_films < "%%f"
    if errorlevel 1 ( echo  Schema load failed at %%f. & pause & exit /b 1 )
)
echo    - schema loaded into sunrise_films

php database\seed.php
if errorlevel 1 ( echo  Seeder failed. & pause & exit /b 1 )
echo    - admin seeded

php tests\auth_check.php
if errorlevel 1 ( echo  Acceptance tests failed - see output above. & pause & exit /b 1 )
echo    - acceptance tests passed

echo.
echo  ======================================================
echo   Done. root password is now %NEWPASS% (matches .env).
echo   Start the app with:
echo       php -S localhost:8000 -t public public/index.php
echo   Then open  http://localhost:8000/admin
echo.
echo   Email:    admin@gmail.com
echo   Password: admin123
echo  ======================================================
echo.
pause
endlocal
