using System;
using System.Diagnostics;
using System.IO;
using System.Reflection;
using System.Text;
using System.Windows.Forms;
using Microsoft.Win32;

[assembly: AssemblyTitle("NCDs Red Alert Station Uninstaller")]
[assembly: AssemblyProduct("NCDs Red Alert Station")]
[assembly: AssemblyVersion("1.0.0.0")]
[assembly: AssemblyFileVersion("1.0.0.0")]
[assembly: AssemblyInformationalVersion("1.0 Build 202608312200")]

namespace NCDsRedAlertUninstaller
{
    static class Program
    {
        [STAThread]
        static void Main()
        {
            Application.EnableVisualStyles();
            var result = MessageBox.Show(
                "ถอนการติดตั้ง NCDs Red Alert Station ออกจากเครื่องนี้?\n\nระบบจะปิดโปรแกรมและลบไฟล์กับการตั้งค่าของระบบออกจากเครื่อง\nฐาน JHCIS และข้อมูลบนเว็บไซต์จะไม่ถูกลบ",
                "NCDs Red Alert Uninstaller", MessageBoxButtons.YesNo, MessageBoxIcon.Warning);
            if (result != DialogResult.Yes) return;
            try
            {
                RemoveVersion("NCDsRedAlertStation", "NCDs_RedAlert_Station", "red_alert_config.json", "🚨 NCDs Red Alert Station.lnk");
                Registry.CurrentUser.DeleteSubKeyTree(@"SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\NCDsRedAlertStation", false);
                Delete(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "NCDs Red Alert Station.lnk"));
                ScheduleSelfCleanup();
                MessageBox.Show("ถอนการติดตั้ง NCDs Red Alert Station เรียบร้อยแล้ว", "สำเร็จ", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show("ถอนการติดตั้งไม่สมบูรณ์: " + ex.Message, "ข้อผิดพลาด", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        static void RemoveVersion(string runKeyName, string processName, string configName, string shortcutName)
        {
            string exePath = "";
            using (var key = Registry.CurrentUser.OpenSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\Run", true))
            {
                if (key != null)
                {
                    object value = key.GetValue(runKeyName);
                    if (value != null) exePath = value.ToString().Trim().Trim('"');
                    key.DeleteValue(runKeyName, false);
                }
            }

            foreach (var process in Process.GetProcesses())
            {
                if (!process.ProcessName.StartsWith(processName, StringComparison.OrdinalIgnoreCase)) continue;
                try { if (String.IsNullOrEmpty(exePath)) exePath = process.MainModule.FileName; } catch { }
                try { process.Kill(); process.WaitForExit(3000); } catch { }
            }

            Delete(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), shortcutName));
            if (!String.IsNullOrEmpty(exePath))
            {
                string dir = Path.GetDirectoryName(exePath);
                Delete(Path.Combine(dir, configName));
                Delete(Path.Combine(dir, "red_alert_debug.log"));
                Delete(Path.Combine(dir, "red_alert_error.log"));
                Delete(exePath);
            }
        }

        static void Delete(string path)
        {
            if (File.Exists(path)) File.Delete(path);
        }

        static void ScheduleSelfCleanup()
        {
            string ownPath = Application.ExecutablePath;
            string ownDir = Path.GetDirectoryName(ownPath);
            if (!Path.GetFileName(ownDir).Equals("NCDsRedAlertStation", StringComparison.OrdinalIgnoreCase)) return;
            string helper = Path.Combine(Path.GetTempPath(), "ncd_station_cleanup_" + Guid.NewGuid().ToString("N") + ".cmd");
            string script = "@echo off\r\ntimeout /t 2 /nobreak >nul\r\ndel /f /q \"" + ownPath + "\"\r\nrmdir \"" + ownDir + "\" 2>nul\r\ndel /f /q \"%~f0\"\r\n";
            File.WriteAllText(helper, script, Encoding.Default);
            Process.Start(new ProcessStartInfo { FileName = helper, UseShellExecute = true, WindowStyle = ProcessWindowStyle.Hidden });
        }
    }
}
