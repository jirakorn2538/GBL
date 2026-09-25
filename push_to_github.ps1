# Set console output encoding to UTF-8
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$Host.UI.RawUI.WindowTitle = "Money Life - Push to GitHub"

Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  Money Life - Auto Push to GitHub (jirakorn2538/GBL)" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""

$git = "C:\Program Files\Git\cmd\git.exe"
$gh  = "C:\Program Files\GitHub CLI\gh.exe"

Set-Location $PSScriptRoot

Write-Host "กำลังจัดเตรียมไฟล์..." -ForegroundColor Yellow
& $git add .
& $git commit -m "Deploy Money Life web app" 2>$null | Out-Null

Write-Host "กำลังตรวจสอบการส่งโค้ดขึ้น GitHub..." -ForegroundColor Yellow
& $git push -u origin main

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "========================================================" -ForegroundColor Green
    Write-Host "  [สำเร็จ 100%] โค้ดทั้งหมดขึ้น GitHub jirakorn2538/GBL เรียบร้อยแล้ว!" -ForegroundColor Green
    Write-Host "========================================================" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "[แจ้งเตือน] ยังไม่ได้เข้าสู่ระบบ GitHub หรือต้องยืนยันสิทธิ์" -ForegroundColor Yellow
    Write-Host "ระบบกำลังเปิดหน้าเว็บเบราว์เซอร์เพื่อเข้าสู่ระบบ GitHub..." -ForegroundColor Yellow
    Write-Host "(หากหน้าเว็บเบราว์เซอร์เปิดขึ้นมา ให้กดปุ่มสีเขียว Authorize)" -ForegroundColor Cyan
    Write-Host ""
    
    & $gh auth login --hostname github.com --git-protocol https --web --scopes repo
    & $gh auth setup-git
    
    Write-Host ""
    Write-Host "กำลังส่งโค้ดขึ้น GitHub อีกครั้ง..." -ForegroundColor Yellow
    & $git push -u origin main
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "========================================================" -ForegroundColor Green
        Write-Host "  [สำเร็จ 100%] โค้ดทั้งหมดขึ้น GitHub jirakorn2538/GBL เรียบร้อยแล้ว!" -ForegroundColor Green
        Write-Host "========================================================" -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "========================================================" -ForegroundColor Red
        Write-Host "  [ไม่สำเร็จ] กรุณาตรวจสอบการเข้าสู่ระบบ GitHub" -ForegroundColor Red
        Write-Host "========================================================" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "กดปุ่ม Enter เพื่อปิดหน้าต่างนี้..."
Read-Host
