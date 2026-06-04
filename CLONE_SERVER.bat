@echo off
title OBSChurch - Clone / Update từ GitHub
color 0B
cls

echo.
echo  =====================================================
echo    OBSChurch Broadcast Command v2
echo    GitHub Clone / Update Tool
echo  =====================================================
echo.

REM === Bien cau hinh ===
set REPO_URL=https://github.com/haiyenpa25/OBSChurch.git
set INSTALL_DIR=C:\xampp\htdocs\OBSChurch

REM === Kiem tra Git ===
echo  [1/4] Kiem tra Git...
git --version >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo  [LOI] Git chua duoc cai dat!
    echo        Vui long tai va cai: https://git-scm.com/download/win
    echo        Sau do chay lai file nay.
    echo.
    pause
    exit /b 1
)
for /f "tokens=*" %%i in ('git --version') do set GIT_VER=%%i
echo        OK - %GIT_VER%

REM === Kiem tra Node.js ===
echo  [2/4] Kiem tra Node.js...
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo  [LOI] Node.js chua duoc cai dat!
    echo        Vui long tai va cai: https://nodejs.org/ (chon ban LTS)
    echo        Sau do chay lai file nay.
    echo.
    pause
    exit /b 1
)
for /f "tokens=*" %%i in ('node --version') do set NODE_VER=%%i
echo        OK - Node.js %NODE_VER%

REM === Clone hoac Pull moi nhat ===
echo  [3/4] Tai code tu GitHub...
echo.

if exist "%INSTALL_DIR%\.git" (
    echo        Thu muc da ton tai - Dang cap nhat len phien ban moi nhat...
    cd /d "%INSTALL_DIR%"
    git pull origin main
    if %errorlevel% neq 0 (
        echo.
        echo  [LOI] git pull that bai! Kiem tra ket noi Internet.
        echo        Hoac co the co xung dot - thu chay: git reset --hard origin/main
        pause
        exit /b 1
    )
    echo        Da cap nhat thanh cong!
) else (
    echo        Chua co code - Dang clone lan dau tu GitHub...
    echo        URL: %REPO_URL%
    echo.

    REM Tao thu muc cha neu chua co
    if not exist "C:\xampp\htdocs\" (
        echo  [LOI] Khong tim thay C:\xampp\htdocs\
        echo        Vui long cai XAMPP truoc: https://www.apachefriends.org/
        pause
        exit /b 1
    )

    git clone %REPO_URL% "%INSTALL_DIR%"
    if %errorlevel% neq 0 (
        echo.
        echo  [LOI] git clone that bai! Kiem tra ket noi Internet.
        pause
        exit /b 1
    )
    echo        Clone thanh cong!
)

REM === Cai Node dependencies ===
echo  [4/4] Cai dat WebSocket dependencies...
cd /d "%INSTALL_DIR%\server"

if not exist "node_modules\" (
    echo        Dang chay npm install...
    npm install
    if %errorlevel% neq 0 (
        echo  [LOI] npm install that bai!
        pause
        exit /b 1
    )
) else (
    echo        node_modules da co san - bo qua.
)

REM === Hoan thanh ===
echo.
echo  =====================================================
echo    CAI DAT HOAN TAT!
echo.
echo    Buoc tiep theo:
echo    1. Mo XAMPP Control Panel -> Start Apache
echo    2. Double-click START-SERVER.bat trong thu muc:
echo       %INSTALL_DIR%\
echo    3. Mo trinh duyet: http://localhost/OBSChurch/
echo  =====================================================
echo.

set /p OPEN="  Mo thu muc OBSChurch ngay bay gio? (Y/N): "
if /i "%OPEN%"=="Y" (
    explorer "%INSTALL_DIR%"
)

pause
