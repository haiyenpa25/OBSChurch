# OBSChurch WebSocket Server - PowerShell Launcher
# Chay file nay bang cach: chuot phai -> Run with PowerShell

$Host.UI.RawUI.WindowTitle = "OBSChurch - WebSocket Server"

Write-Host ""
Write-Host "  =====================================================" -ForegroundColor Cyan
Write-Host "    OBSChurch Broadcast Command v2" -ForegroundColor White
Write-Host "    WebSocket Server Launcher" -ForegroundColor Gray
Write-Host "  =====================================================" -ForegroundColor Cyan
Write-Host ""

# === Kiem tra Node.js ===
Write-Host "  [1/3] Kiem tra Node.js..." -ForegroundColor Yellow
try {
    $nodeVersion = node --version 2>&1
    Write-Host "        OK - Node.js $nodeVersion" -ForegroundColor Green
} catch {
    Write-Host ""
    Write-Host "  [LOI] Node.js chua duoc cai dat!" -ForegroundColor Red
    Write-Host "        Tai tai: https://nodejs.org/ (chon ban LTS)" -ForegroundColor Red
    Write-Host ""
    Read-Host "  Nhan Enter de thoat"
    exit 1
}

# === Di chuyen vao thu muc server ===
$serverPath = Join-Path $PSScriptRoot "server"
Set-Location $serverPath

# === Kiem tra va cai node_modules ===
Write-Host "  [2/3] Kiem tra dependencies..." -ForegroundColor Yellow
if (-not (Test-Path "node_modules")) {
    Write-Host "        Lan dau chay - Dang cai WebSocket library..." -ForegroundColor Yellow
    npm install
    if ($LASTEXITCODE -ne 0) {
        Write-Host "  [LOI] npm install that bai! Kiem tra ket noi Internet." -ForegroundColor Red
        Read-Host "  Nhan Enter de thoat"
        exit 1
    }
    Write-Host "        Cai dat xong!" -ForegroundColor Green
} else {
    Write-Host "        OK - Dependencies da san sang" -ForegroundColor Green
}

# === Khoi dong server ===
Write-Host "  [3/3] Khoi dong WebSocket Server..." -ForegroundColor Yellow
Write-Host ""
Write-Host "  =====================================================" -ForegroundColor Cyan
Write-Host "    Server dang chay:" -ForegroundColor White
Write-Host ""
Write-Host "    WebSocket    : ws://localhost:3001" -ForegroundColor Green
Write-Host "    Control Panel: http://localhost/OBSChurch/index.html" -ForegroundColor Green
Write-Host "    OBS Overlay  : http://localhost/OBSChurch/overlay/index.html" -ForegroundColor Green
Write-Host ""
Write-Host "    GIU CUA SO NAY MO TRONG SUOT BUOI THO PHUONG!" -ForegroundColor Yellow
Write-Host "    Nhan Ctrl+C de tat server." -ForegroundColor Gray
Write-Host "  =====================================================" -ForegroundColor Cyan
Write-Host ""

node ws-server.js

Write-Host ""
Write-Host "  Server da dung." -ForegroundColor Gray
Read-Host "  Nhan Enter de thoat"
