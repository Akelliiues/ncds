@echo off
setlocal
title NCDs Red Alert Station Setup

set "SOURCE_DIR=%~dp0"
set "SOURCE_EXE=%SOURCE_DIR%NCDs_RedAlert_Station.exe"
set "INSTALL_DIR=%LOCALAPPDATA%\NCDsRedAlertStation"
set "INSTALL_EXE=%INSTALL_DIR%\NCDs_RedAlert_Station.exe"

if not exist "%SOURCE_EXE%" (
  echo [ERROR] NCDs_RedAlert_Station.exe was not found in this folder.
  pause
  exit /b 1
)

echo Closing previous Red Alert Station processes...
taskkill /f /im NCDs_RedAlert_Station.exe >nul 2>&1
timeout /t 1 /nobreak >nul

if not exist "%INSTALL_DIR%" mkdir "%INSTALL_DIR%"
copy /y "%SOURCE_EXE%" "%INSTALL_EXE%" >nul
copy /y "%SOURCE_DIR%Uninstall_NCDs_Red_Alert.exe" "%INSTALL_DIR%\Uninstall_NCDs_Red_Alert.exe" >nul 2>&1

powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Unblock-File -LiteralPath '%INSTALL_EXE%'; if (Test-Path -LiteralPath '%INSTALL_DIR%\Uninstall_NCDs_Red_Alert.exe') { Unblock-File -LiteralPath '%INSTALL_DIR%\Uninstall_NCDs_Red_Alert.exe' }"

powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$s=(New-Object -ComObject WScript.Shell).CreateShortcut([Environment]::GetFolderPath('Desktop')+'\NCDs Red Alert Station.lnk');$s.TargetPath='%INSTALL_EXE%';$s.WorkingDirectory='%INSTALL_DIR%';$s.Save()"

echo Starting NCDs Red Alert Station...
start "" "%INSTALL_EXE%"
echo Installation completed.
timeout /t 2 /nobreak >nul
exit /b 0
