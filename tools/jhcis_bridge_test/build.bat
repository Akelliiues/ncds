@echo off
setlocal
set CSC=C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe
"%CSC%" /nologo /target:winexe /optimize+ /out:"NCDs_JHCIS_Bridge_Test.exe" /r:System.dll /r:System.Windows.Forms.dll /r:System.Drawing.dll Program.cs
exit /b %errorlevel%
