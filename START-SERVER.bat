@echo off
title OBSChurch - WebSocket Server
color 0A
cls

echo.
echo  =====================================================
echo    OBSChurch Broadcast Command v2
echo    WebSocket Server Launcher
echo  =====================================================
echo.

REM === Kiem tra Node.js ===
echo  [1/3] Kiem tra Node.js...
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo  [LOI] Node.js chua duoc cai dat!
    echo        Vui long tai va cai: https://nodejs.org/
    echo        Yeu cau phien ban 18.x tro len (LTS)
    echo.
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('node --version') do set NODE_VER=%%i
echo       OK - Node.js %NODE_VER%

REM === Kiem tra va cai node_modules ===
echo  [2/3] Kiem tra dependencies...
cd /d "%~dp0server"

if not exist "node_modules\" (
    echo       Lan dau chay - Dang cai thu vien WebSocket...
    echo.
    npm install
    if %errorlevel% neq 0 (
        echo  [LOI] npm install that bai! Kiem tra ket noi Internet.
        pause
        exit /b 1
    )
    echo       Cai dat xong!
) else (
    echo       OK - Dependencies da san sang
)

REM === Khoi dong server ===
echo  [3/3] Khoi dong WebSocket Server...
echo.
echo  =====================================================
echo    Server dang chay tai:
echo.
echo    WebSocket   : ws://localhost:3000
echo    Control Panel: http://localhost/OBSChurch/index.html
echo    OBS Overlay  : http://localhost/OBSChurch/overlay/index.html
echo.
echo    GIU CUA SO NAY MO TRONG SUOT BUOI THO PHUONG!
echo    Nhan Ctrl+C de tat server.
echo  =====================================================
echo.

node ws-server.js

echo.
echo  Server da dung.
pause
