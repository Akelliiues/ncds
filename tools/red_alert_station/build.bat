@echo off
title NCDs Red Alert Compiler
color 0A
echo ===================================================
echo   Compiling NCDs Red Alert Station (.EXE)
echo ===================================================
echo.

set CSC="C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"

echo Compiling NCDs_RedAlert_Station.exe...
%CSC% /target:winexe /optimize+ /out:"NCDs_RedAlert_Station.exe" /r:System.dll /r:System.Windows.Forms.dll /r:System.Drawing.dll /r:System.Web.Extensions.dll /r:Microsoft.CSharp.dll Program.cs

if %errorlevel% neq 0 (
    echo ❌ Compilation failed!
    pause
    exit /b 1
)

echo.
echo ✅ NCDs_RedAlert_Station.exe COMPILED SUCCESSFULLY!
echo.
pause
