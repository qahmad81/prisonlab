@echo off
chcp 65001 >nul
title AI Prisoner Interrogation Test Lab - Port 8488
cd /d "%~dp0"

set PORT=8488
for /f "tokens=1,2 delims==" %%a in (.env) do (
    if "%%a"=="PORT" set PORT=%%b
)

echo ========================================================
echo   AI Prisoner Stress & Interrogation Test Lab
echo   Running PHP Server on: http://localhost:%PORT%
echo ========================================================
echo.

start "" "http://localhost:%PORT%"
php -S 127.0.0.1:%PORT% router.php
pause
