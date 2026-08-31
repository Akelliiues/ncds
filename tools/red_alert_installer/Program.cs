using System;
using System.Diagnostics;
using System.IO;
using System.Reflection;
using System.Text;
using System.Windows.Forms;
using Microsoft.Win32;

[assembly: AssemblyTitle("NCDs Red Alert Station Setup")]
[assembly: AssemblyProduct("NCDs Red Alert Station Setup")]
[assembly: AssemblyCompany("สำนักงานสาธารณสุขอำเภอตาลสุม")]
[assembly: AssemblyVersion("1.0.0.0")]
[assembly: AssemblyFileVersion("1.0.0.0")]
[assembly: AssemblyInformationalVersion("1.0 Build 202608312200")]

namespace NCDsRedAlertInstaller
{
    static class Program
    {
        const string AppResource = "NCDsRedAlertStation.exe";
        const string UninstallerResource = "NCDsRedAlertUninstaller.exe";

        [STAThread]
        static void Main()
        {
            Application.EnableVisualStyles();
            var answer = MessageBox.Show(
                "ติดตั้ง NCDs Red Alert Station Version 1.0\nBuild 202608312200 บนเครื่องนี้?\n\nSetup จะปิดรุ่นเดิม อัปเกรดไฟล์ และตั้งให้เริ่มพร้อม Windows",
                "NCDs Red Alert Station Setup", MessageBoxButtons.YesNo, MessageBoxIcon.Information);
            if (answer != DialogResult.Yes) return;

            try
            {
                string installDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "NCDsRedAlertStation");
                string appPath = Path.Combine(installDir, "NCDs_RedAlert_Station.exe");
                string uninstallerPath = Path.Combine(installDir, "Uninstall_NCDs_Red_Alert.exe");

                StopExistingStations();
                Directory.CreateDirectory(installDir);
                ExtractResource(AppResource, appPath);
                ExtractResource(UninstallerResource, uninstallerPath);
                CreateShortcut(appPath, installDir);
                RegisterAutoStart(appPath);
                RegisterUninstaller(uninstallerPath, installDir);

                Process.Start(new ProcessStartInfo { FileName = appPath, WorkingDirectory = installDir, UseShellExecute = true });
                MessageBox.Show("ติดตั้ง NCDs Red Alert Station Version 1.0\nBuild 202608312200 เรียบร้อยแล้ว", "ติดตั้งสำเร็จ", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show("ติดตั้งไม่สำเร็จ: " + ex.Message, "ข้อผิดพลาด", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        static void StopExistingStations()
        {
            foreach (var process in Process.GetProcesses())
            {
                if (!process.ProcessName.StartsWith("NCDs_RedAlert", StringComparison.OrdinalIgnoreCase)) continue;
                if (process.Id == Process.GetCurrentProcess().Id) continue;
                try { process.Kill(); process.WaitForExit(4000); } catch { }
            }
        }

        static void ExtractResource(string resourceName, string destination)
        {
            using (Stream input = Assembly.GetExecutingAssembly().GetManifestResourceStream(resourceName))
            {
                if (input == null) throw new Exception("ไม่พบไฟล์ภายใน Setup: " + resourceName);
                using (var output = new FileStream(destination, FileMode.Create, FileAccess.Write, FileShare.None)) input.CopyTo(output);
            }
        }

        static void CreateShortcut(string appPath, string installDir)
        {
            string shortcutPath = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "NCDs Red Alert Station.lnk");
            Type shellType = Type.GetTypeFromProgID("WScript.Shell");
            dynamic shell = Activator.CreateInstance(shellType);
            dynamic shortcut = shell.CreateShortcut(shortcutPath);
            shortcut.TargetPath = appPath;
            shortcut.WorkingDirectory = installDir;
            shortcut.IconLocation = appPath + ",0";
            shortcut.Description = "NCDs Red Alert Station Version 1.0 Build 202608312200";
            shortcut.Save();
        }

        static void RegisterAutoStart(string appPath)
        {
            using (var key = Registry.CurrentUser.CreateSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\Run"))
            {
                key.DeleteValue("NCDsRedAlertStation", false);
                key.DeleteValue("NCDsRedAlertStationV3", false);
                key.SetValue("NCDsRedAlertStation", "\"" + appPath + "\"");
            }
        }

        static void RegisterUninstaller(string uninstallerPath, string installDir)
        {
            using (var key = Registry.CurrentUser.CreateSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\NCDsRedAlertStation"))
            {
                key.SetValue("DisplayName", "NCDs Red Alert Station");
                key.SetValue("DisplayVersion", "1.0");
                key.SetValue("BuildId", "202608312200");
                key.SetValue("Publisher", "สำนักงานสาธารณสุขอำเภอตาลสุม");
                key.SetValue("InstallLocation", installDir);
                key.SetValue("DisplayIcon", Path.Combine(installDir, "NCDs_RedAlert_Station.exe"));
                key.SetValue("UninstallString", "\"" + uninstallerPath + "\"");
                key.SetValue("NoModify", 1, RegistryValueKind.DWord);
                key.SetValue("NoRepair", 1, RegistryValueKind.DWord);
            }
        }
    }
}
