@echo off
setlocal EnableExtensions
title JhutLedger BD Installer

set "APP_NAME=jhutledger"
set "SOURCE_DIR=%~dp0"
set "XAMPP_ROOT="

echo.
echo ============================================================
echo   JhutLedger BD - XAMPP installer
echo ============================================================
echo.

if not exist "%SOURCE_DIR%database\schema.sql" goto missing_project
if not exist "%SOURCE_DIR%database\seed.sql" goto missing_project

rem Prefer an explicitly configured XAMPP path, then common locations.
if defined XAMPP_HOME if exist "%XAMPP_HOME%\php\php.exe" set "XAMPP_ROOT=%XAMPP_HOME%"
if not defined XAMPP_ROOT if exist "C:\xampp\php\php.exe" set "XAMPP_ROOT=C:\xampp"
if not defined XAMPP_ROOT if exist "D:\xampp\php\php.exe" set "XAMPP_ROOT=D:\xampp"
if not defined XAMPP_ROOT if exist "D:\Softwares\XAMPP\php\php.exe" set "XAMPP_ROOT=D:\Softwares\XAMPP"

if defined XAMPP_ROOT goto xampp_ready

echo XAMPP 8.2 was not found. Installing it with Windows Package Manager...
where winget >nul 2>&1
if errorlevel 1 goto missing_winget

winget install --id ApacheFriends.Xampp.8.2 --exact --accept-package-agreements --accept-source-agreements
if errorlevel 1 goto xampp_install_failed

if exist "C:\xampp\php\php.exe" set "XAMPP_ROOT=C:\xampp"
if not defined XAMPP_ROOT if exist "D:\xampp\php\php.exe" set "XAMPP_ROOT=D:\xampp"
if not defined XAMPP_ROOT goto xampp_not_detected

:xampp_ready
set "TARGET_DIR=%XAMPP_ROOT%\htdocs\%APP_NAME%"
set "MYSQL=%XAMPP_ROOT%\mysql\bin\mysql.exe"
set "MYSQLADMIN=%XAMPP_ROOT%\mysql\bin\mysqladmin.exe"
set "MYSQLD=%XAMPP_ROOT%\mysql\bin\mysqld.exe"
set "PHP=%XAMPP_ROOT%\php\php.exe"
set "HTTPD=%XAMPP_ROOT%\apache\bin\httpd.exe"

echo Found XAMPP at: %XAMPP_ROOT%
echo Installing project at: %TARGET_DIR%
echo.

if not exist "%MYSQL%" goto incomplete_xampp
if not exist "%PHP%" goto incomplete_xampp
if not exist "%HTTPD%" goto incomplete_xampp

if /I "%SOURCE_DIR:~0,-1%"=="%TARGET_DIR%" goto project_ready

if not exist "%TARGET_DIR%" mkdir "%TARGET_DIR%"
robocopy "%SOURCE_DIR%" "%TARGET_DIR%" /E /R:2 /W:1 /XD ".git" ".codex" >nul
if errorlevel 8 goto copy_failed

:project_ready
echo Starting MySQL...
"%MYSQLADMIN%" --protocol=tcp --host=127.0.0.1 --port=3306 --user=root ping >nul 2>&1
if errorlevel 1 start "JhutLedger MySQL" /MIN "%MYSQLD%" --defaults-file="%XAMPP_ROOT%\mysql\bin\my.ini" --standalone

set /A WAIT_COUNT=0
:wait_for_mysql
"%MYSQLADMIN%" --protocol=tcp --host=127.0.0.1 --port=3306 --user=root ping >nul 2>&1
if not errorlevel 1 goto mysql_ready
set /A WAIT_COUNT+=1
if %WAIT_COUNT% GEQ 30 goto mysql_failed
timeout /t 1 /nobreak >nul
goto wait_for_mysql

:mysql_ready
echo Starting Apache...
powershell -NoProfile -Command "try { Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1/' -TimeoutSec 2 ^| Out-Null; exit 0 } catch { exit 1 }" >nul 2>&1
if errorlevel 1 start "JhutLedger Apache" /MIN /D "%XAMPP_ROOT%\apache\bin" "%HTTPD%"

set /A APACHE_WAIT_COUNT=0
:wait_for_apache
powershell -NoProfile -Command "try { Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1/' -TimeoutSec 2 ^| Out-Null; exit 0 } catch { exit 1 }" >nul 2>&1
if not errorlevel 1 goto apache_ready
set /A APACHE_WAIT_COUNT+=1
if %APACHE_WAIT_COUNT% GEQ 20 goto apache_failed
timeout /t 1 /nobreak >nul
goto wait_for_apache

:apache_ready

set "DB_EXISTS="
for /F "usebackq delims=" %%D in (`"%MYSQL%" --host=127.0.0.1 --port=3306 --user=root --batch --skip-column-names --execute="SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='jhutledger_db'" 2^>nul`) do set "DB_EXISTS=%%D"
if defined DB_EXISTS (
    echo.
    echo WARNING: jhutledger_db already exists. Continuing will reset its data.
    choice /C YN /N /M "Reset the existing database? [Y/N]: "
    if errorlevel 2 goto cancelled
)

echo Importing database schema...
"%MYSQL%" --host=127.0.0.1 --port=3306 --user=root < "%TARGET_DIR%\database\schema.sql"
if errorlevel 1 goto database_failed

echo Importing demonstration data...
"%MYSQL%" --host=127.0.0.1 --port=3306 --user=root < "%TARGET_DIR%\database\seed.sql"
if errorlevel 1 goto database_failed

echo Running database verification...
pushd "%TARGET_DIR%"
"%PHP%" tests\database_smoke.php
set "TEST_RESULT=%ERRORLEVEL%"
popd
if not "%TEST_RESULT%"=="0" goto test_failed

echo.
echo ============================================================
echo   Installation completed successfully.
echo   URL: http://localhost/jhutledger/
echo   Demo password: Demo@123
echo ============================================================
echo.
start "" "http://localhost/jhutledger/"
pause
exit /b 0

:missing_project
echo ERROR: Run this file from the extracted JhutLedger project folder.
goto failed

:missing_winget
echo ERROR: XAMPP is missing and winget is unavailable.
echo Install XAMPP 8.2 manually, then run this file again.
goto failed

:xampp_install_failed
echo ERROR: XAMPP installation failed or was cancelled.
goto failed

:xampp_not_detected
echo ERROR: XAMPP was installed but its folder could not be detected.
echo Set XAMPP_HOME to the installation folder and run this file again.
goto failed

:incomplete_xampp
echo ERROR: The detected XAMPP installation is incomplete.
goto failed

:copy_failed
echo ERROR: The project could not be copied into the XAMPP htdocs folder.
goto failed

:mysql_failed
echo ERROR: MySQL did not start within 30 seconds.
echo Check whether port 3306 is already being used by another program.
goto failed

:apache_failed
echo ERROR: Apache did not start within 20 seconds.
echo Check whether port 80 is already being used by another program.
goto failed

:database_failed
echo ERROR: Database import failed.
echo This installer expects a fresh XAMPP root account with an empty password.
goto failed

:test_failed
echo ERROR: Installation finished, but the database verification failed.
goto failed

:cancelled
echo Installation cancelled. The existing database was not changed.
pause
exit /b 0

:failed
echo.
echo Installation was not completed. Read the error above, then try again.
pause
exit /b 1
