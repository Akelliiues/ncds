@echo off
title NCDs Red Alert Station Launcher
color 0C
echo =======================================================
echo    🚨 NCDs RED ALERT STATION LAUNCHER (รพ.สต.)
echo    ศูนย์รับสัญญาณเตือนภัยวิกฤตฉุกเฉิน NCDs Portal
echo =======================================================
echo.
echo กำลังเปิดสถานีรับสัญญาณฉุกเฉินในโหมด Always-on-Top / App Mode...

:: Try launching in Microsoft Edge App Mode
start msedge --app="http://localhost/ssotansum/ncd/admin/emergency_receiver.php" --window-size=1280,800
if %errorlevel% equ 0 goto done

:: Fallback to Chrome App Mode
start chrome --app="http://localhost/ssotansum/ncd/admin/emergency_receiver.php" --window-size=1280,800
if %errorlevel% equ 0 goto done

:: Fallback to default browser
start http://localhost/ssotansum/ncd/admin/emergency_receiver.php

:done
echo ✅ สถานีพร้อมรับสัญญาณแล้ว (หน้าต่างจะเด้งและส่งเสียงเตือนเมื่อมีเคสวิกฤต)
timeout /t 5
exit
