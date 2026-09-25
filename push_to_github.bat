@echo off
chcp 65001 >nul
echo ========================================================
echo   Money Life - Auto Push to GitHub (jirakorn2538/GBL)
echo ========================================================
echo.
cd /d "%~dp0"

set "GIT=C:\Program Files\Git\cmd\git.exe"
set "GH=C:\Program Files\GitHub CLI\gh.exe"

"%GIT%" add .
"%GIT%" commit -m "Deploy Money Life web app" >nul 2>&1

echo [1/2] กำลังทดสอบส่งโค้ดขึ้น GitHub...
"%GIT%" push -u origin main
if %errorlevel% equ 0 goto success

echo.
echo [แจ้งเตือน] ยังไม่ได้เข้าสู่ระบบ GitHub หรือระบบขอการยืนยันสิทธิ์
echo กำลังเปิดหน้าต่างเบราว์เซอร์เพื่อเข้าสู่ระบบ GitHub (กดปุ่มยืนยันในเว็บเบราว์เซอร์)...
echo.

if exist "%GH%" (
    "%GH%" auth login --hostname github.com --git-protocol https --web --scopes repo
    "%GH%" auth setup-git
    echo.
    echo กำลังส่งโค้ดขึ้น GitHub อีกครั้ง...
    "%GIT%" push -u origin main
    if %errorlevel% equ 0 goto success
)

:fail
echo.
echo ========================================================
echo   [ไม่สำเร็จ] กรุณาตรวจสอบการล็อกอิน GitHub อีกครั้ง
echo ========================================================
goto end

:success
echo.
echo ========================================================
echo   [สำเร็จ 100%%] โค้ดทั้งหมดขึ้น GitHub jirakorn2538/GBL แล้ว!
echo ========================================================

:end
echo.
pause

