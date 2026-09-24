@echo off
chcp 65001 >nul
echo ========================================================
echo   Money Life - Auto Push to GitHub (jirakorn2538/GBL)
echo ========================================================
echo.
cd /d "%~dp0"
"C:\Program Files\Git\cmd\git.exe" add .
"C:\Program Files\Git\cmd\git.exe" commit -m "Deploy Money Life web app"
echo.
echo กำลังส่งโค้ดขึ้น GitHub...
echo (หากมีหน้าต่างเด้งขึ้นมา ให้กด "Sign in with your browser" เพื่อยืนยันสิทธิ์)
echo.
"C:\Program Files\Git\cmd\git.exe" push -u origin main
echo.
if %errorlevel% equ 0 (
    echo ========================================================
    echo   [สำเร็จ!] โค้ดทั้งหมดขึ้น GitHub jirakorn2538/GBL แล้ว
    echo ========================================================
) else (
    echo ========================================================
    echo   หากยังไม่สำเร็จ กรุณากด Sign in GitHub ในหน้าต่าง Browser ครับ
    echo ========================================================
)
echo.
pause
