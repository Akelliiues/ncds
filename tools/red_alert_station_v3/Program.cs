using System;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.IO;
using System.Net;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Text;
using System.Threading;
using System.Diagnostics;
using System.Web.Script.Serialization;
using System.Windows.Forms;
using Microsoft.Win32;

[assembly: AssemblyTitle("NCDs Red Alert Station V3")]
[assembly: AssemblyDescription("ระบบแจ้งเตือนและ Local JHCIS Bridge สำหรับ NCDs Portal")]
[assembly: AssemblyConfiguration("")]
[assembly: AssemblyCompany("สำนักงานสาธารณสุขอำเภอตาลสุม")]
[assembly: AssemblyProduct("NCDs Portal Red Alert Station V3")]
[assembly: AssemblyCopyright("Copyright © 2026 SSO Tan Sum")]
[assembly: AssemblyTrademark("SSO Tan Sum NCDs")]
[assembly: AssemblyCulture("")]
[assembly: ComVisible(false)]
[assembly: Guid("2c82e2eb-3cb5-4f4c-89ec-2c0f3a9e9f30")]
[assembly: AssemblyVersion("3.0.0.0")]
[assembly: AssemblyFileVersion("3.0.0.0")]

namespace NCDsRedAlertStation
{
    public class AppConfig
    {
        public string ServerUrl { get; set; }
        public string Hoscode { get; set; }
        public string Hosname { get; set; }
        public bool SoundEnabled { get; set; }
        public int SoundCycles { get; set; }
        public bool AutoStartWithWindows { get; set; }
        public string DestHospitalCode { get; set; }
        public string DestHospitalName { get; set; }
        public string JhcisHost { get; set; }
        public int JhcisPort { get; set; }
        public string JhcisDbname { get; set; }
        public string JhcisUser { get; set; }
        public string JhcisPass { get; set; }
        public bool AutoSyncJhcisReferral { get; set; }
        public bool IsFirstRunSetupDone { get; set; }

        public AppConfig()
        {
            ServerUrl = "https://ncd.ssotansum.com";
            Hoscode = "ALL";
            Hosname = "ALL - ศูนย์กลาง (รับแจ้งเตือนทุกหน่วยบริการ)";
            SoundEnabled = true;
            SoundCycles = 2;
            AutoStartWithWindows = true;
            DestHospitalCode = "10957";
            DestHospitalName = "โรงพยาบาลตาลสุม";
            JhcisHost = "localhost";
            JhcisPort = 3333;
            JhcisDbname = "jhcisdb";
            JhcisUser = "root";
            JhcisPass = "";
            AutoSyncJhcisReferral = true;
            IsFirstRunSetupDone = false;
        }
    }

    public static class UninstallManager
    {
        public static bool ConfirmAndPrepare()
        {
            var answer = MessageBox.Show(
                "ถอน NCDs Red Alert Station V3 ออกจากเครื่อง?\n\nระบบจะลบ Auto Start, Shortcut, การตั้งค่า, Log และไฟล์โปรแกรม V3\nฐานข้อมูล JHCIS และข้อมูลบนเว็บไซต์จะไม่ถูกลบ",
                "ถอนการติดตั้ง V3", MessageBoxButtons.YesNo, MessageBoxIcon.Warning);
            if (answer != DialogResult.Yes) return false;

            try
            {
                using (var key = Registry.CurrentUser.OpenSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\Run", true))
                    if (key != null) key.DeleteValue("NCDsRedAlertStationV3", false);

                DeleteIfExists(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "🚨 NCDs Red Alert Station V3.lnk"));
                string dir = AppDomain.CurrentDomain.BaseDirectory;
                DeleteIfExists(Path.Combine(dir, "red_alert_config_v3.json"));
                DeleteIfExists(Path.Combine(dir, "red_alert_v3_debug.log"));
                DeleteIfExists(Path.Combine(dir, "red_alert_v3_error.log"));
                ScheduleSelfDelete(Application.ExecutablePath);
                return true;
            }
            catch (Exception ex)
            {
                MessageBox.Show("ถอนการติดตั้ง V3 ไม่สำเร็จ: " + ex.Message, "ข้อผิดพลาด", MessageBoxButtons.OK, MessageBoxIcon.Error);
                return false;
            }
        }

        private static void DeleteIfExists(string path) { if (File.Exists(path)) File.Delete(path); }

        private static void ScheduleSelfDelete(string executablePath)
        {
            string helper = Path.Combine(Path.GetTempPath(), "ncd_red_alert_v3_uninstall_" + Guid.NewGuid().ToString("N") + ".cmd");
            string script = "@echo off\r\ntimeout /t 2 /nobreak >nul\r\ndel /f /q \"" + executablePath + "\"\r\ndel /f /q \"%~f0\"\r\n";
            File.WriteAllText(helper, script, Encoding.Default);
            Process.Start(new ProcessStartInfo { FileName = helper, UseShellExecute = true, WindowStyle = ProcessWindowStyle.Hidden });
        }
    }

    public static class ConfigManager
    {
        public static string ConfigPath
        {
            get
            {
                string dir = AppDomain.CurrentDomain.BaseDirectory;
                return Path.Combine(dir, "red_alert_config_v3.json");
            }
        }

        public static bool ConfigExists()
        {
            return File.Exists(ConfigPath);
        }

        public static AppConfig Load()
        {
            try
            {
                if (File.Exists(ConfigPath))
                {
                    string json = File.ReadAllText(ConfigPath, Encoding.UTF8);
                    var serializer = new JavaScriptSerializer();
                    var cfg = serializer.Deserialize<AppConfig>(json);
                    if (cfg != null)
                    {
                        if (string.IsNullOrEmpty(cfg.ServerUrl) || cfg.ServerUrl.Contains("localhost"))
                        {
                            cfg.ServerUrl = "https://ncd.ssotansum.com";
                        }
                        return cfg;
                    }
                }
            }
            catch { }
            return new AppConfig();
        }

        public static void Save(AppConfig cfg)
        {
            try
            {
                var serializer = new JavaScriptSerializer();
                string json = serializer.Serialize(cfg);
                File.WriteAllText(ConfigPath, json, Encoding.UTF8);
                ApplyAutoStart(cfg.AutoStartWithWindows);
            }
            catch (Exception ex)
            {
                MessageBox.Show("ไม่สามารถบันทึกการตั้งค่าได้: " + ex.Message, "ข้อผิดพลาด", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        public static void ApplyAutoStart(bool enable)
        {
            try
            {
                using (var key = Registry.CurrentUser.OpenSubKey(@"SOFTWARE\Microsoft\Windows\CurrentVersion\Run", true))
                {
                    if (key != null)
                    {
                        string appPath = Application.ExecutablePath;
                        if (enable)
                        {
                            // Prevent duplicate alert popups after V3 becomes the active station.
                            key.DeleteValue("NCDsRedAlertStation", false);
                            key.SetValue("NCDsRedAlertStationV3", "\"" + appPath + "\"");
                        }
                        else
                        {
                            key.DeleteValue("NCDsRedAlertStationV3", false);
                        }
                    }
                }
            }
            catch { }
        }

        public static void CreateDesktopShortcut()
        {
            try
            {
                string desktopDir = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                string shortcutPath = Path.Combine(desktopDir, "🚨 NCDs Red Alert Station V3.lnk");
                string appPath = Application.ExecutablePath;
                string workingDir = AppDomain.CurrentDomain.BaseDirectory;

                Type shellType = Type.GetTypeFromProgID("WScript.Shell");
                if (shellType != null)
                {
                    dynamic shell = Activator.CreateInstance(shellType);
                    dynamic shortcut = shell.CreateShortcut(shortcutPath);
                    shortcut.TargetPath = appPath;
                    shortcut.WorkingDirectory = workingDir;
                    shortcut.Description = "NCDs Red Alert Station V3 พร้อม Local JHCIS Bridge";
                    shortcut.Save();
                }
            }
            catch { }
        }

        public static Font GetSystemFont(float size, FontStyle style = FontStyle.Regular)
        {
            string[] preferredFonts = { "Sarabun", "Prompt", "Segoe UI", "Tahoma", "Arial" };
            foreach (var name in preferredFonts)
            {
                try
                {
                    using (var f = new Font(name, size, style))
                    {
                        if (f.Name.Equals(name, StringComparison.InvariantCultureIgnoreCase))
                        {
                            return new Font(name, size, style);
                        }
                    }
                }
                catch { }
            }
            return new Font("Segoe UI", size, style);
        }
    }

    public sealed class JhcisConnectionResult
    {
        public string Version { get; set; }
        public string PcuCode { get; set; }
        public int PersonCount { get; set; }
        public int ScreenCount { get; set; }
        public string ClientPath { get; set; }
    }

    // Runs on the station computer. It never asks the web server to connect to localhost.
    public static class LocalJhcisBridge
    {
        public static JhcisConnectionResult Test(AppConfig config)
        {
            string client = FindMysqlClient();
            string version = RunQuery(client, config, "SELECT VERSION()", 8).Trim();
            string columns = RunQuery(client, config,
                "SELECT GROUP_CONCAT(LOWER(column_name)) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='person'", 8);
            string pcuColumn = HasColumn(columns, "pcucodeperson") ? "pcucodeperson" : (HasColumn(columns, "pcucode") ? "pcucode" : "");
            if (pcuColumn == "") throw new Exception("ตาราง person ไม่มีคอลัมน์ pcucodeperson หรือ pcucode");

            string pcu = RunQuery(client, config,
                "SELECT `" + pcuColumn + "` FROM person WHERE `" + pcuColumn + "`<>'' GROUP BY `" + pcuColumn + "` ORDER BY COUNT(*) DESC LIMIT 1", 8).Trim();
            int persons = ParseCount(RunQuery(client, config, "SELECT COUNT(*) FROM person", 8));
            int screens = ParseCount(RunQuery(client, config, "SELECT COUNT(*) FROM ncd_person_ncd_screen", 8));
            return new JhcisConnectionResult { Version = version, PcuCode = pcu, PersonCount = persons, ScreenCount = screens, ClientPath = client };
        }

        public static int ExecutePortalScript(AppConfig config, string sql)
        {
            if (string.IsNullOrWhiteSpace(sql) || !sql.Contains("-- NCDS-SCREENING-IDS:"))
                throw new Exception("ไฟล์คำสั่งไม่ได้สร้างจากหน้า NCDs Portal");
            string upper = sql.ToUpperInvariant();
            string[] forbidden = { " DROP ", " DELETE ", " UPDATE ", " ALTER ", " TRUNCATE ", " CREATE ", " GRANT ", " REPLACE " };
            foreach (string token in forbidden)
                if (upper.Contains(token)) throw new Exception("พบคำสั่งที่ไม่ได้รับอนุญาต: " + token.Trim());

            string client = FindMysqlClient();
            var start = new ProcessStartInfo {
                FileName = client,
                Arguments = "--connect-timeout=5 --protocol=tcp -h " + Quote(config.JhcisHost) + " -P " + config.JhcisPort +
                    " -u " + Quote(config.JhcisUser) + " --default-character-set=tis620 " + Quote(config.JhcisDbname),
                UseShellExecute = false, RedirectStandardInput = true, RedirectStandardOutput = true,
                RedirectStandardError = true, CreateNoWindow = true,
                StandardOutputEncoding = Encoding.UTF8, StandardErrorEncoding = Encoding.UTF8
            };
            start.EnvironmentVariables["MYSQL_PWD"] = config.JhcisPass ?? "";
            using (var process = Process.Start(start))
            {
                process.StandardInput.Write(sql);
                process.StandardInput.Close();
                string stderr = process.StandardError.ReadToEnd();
                process.StandardOutput.ReadToEnd();
                if (!process.WaitForExit(120000)) { process.Kill(); throw new TimeoutException("หมดเวลารอการบันทึก JHCIS"); }
                if (process.ExitCode != 0) throw new Exception(string.IsNullOrWhiteSpace(stderr) ? "JHCIS ปฏิเสธคำสั่งนำเข้า" : stderr.Trim());
            }
            int count = 0;
            const string markerText = "-- NCDS-SCREENING-IDS:";
            int marker = sql.IndexOf(markerText, StringComparison.Ordinal);
            if (marker >= 0) count = sql.Substring(marker + markerText.Length).Split('\n')[0].Split(new[] { ',' }, StringSplitOptions.RemoveEmptyEntries).Length;
            return count;
        }

        private static bool HasColumn(string csv, string name)
        {
            foreach (string item in (csv ?? "").Split(','))
                if (item.Trim().Equals(name, StringComparison.OrdinalIgnoreCase)) return true;
            return false;
        }

        private static int ParseCount(string value)
        {
            int result;
            if (!int.TryParse((value ?? "").Trim(), out result)) throw new Exception("JHCIS ส่งค่าจำนวนข้อมูลไม่ถูกต้อง");
            return result;
        }

        private static string FindMysqlClient()
        {
            string[] candidates = {
                @"C:\Program Files\JHCIS\MySQL5.6\bin\mysql.exe",
                @"C:\Program Files\JHCIS\MySQL\bin\mysql.exe",
                @"C:\Program Files (x86)\JHCIS\MySQL\bin\mysql.exe"
            };
            foreach (string candidate in candidates) if (File.Exists(candidate)) return candidate;
            throw new FileNotFoundException("ไม่พบ mysql.exe ของ JHCIS ในเครื่อง");
        }

        private static string RunQuery(string client, AppConfig config, string query, int timeoutSeconds)
        {
            if (string.IsNullOrWhiteSpace(config.JhcisHost)) throw new Exception("กรุณาระบุ Host หรือ IP ของ JHCIS");
            if (config.JhcisPort < 1 || config.JhcisPort > 65535) throw new Exception("พอร์ต JHCIS ไม่ถูกต้อง");
            if (!IsSafeIdentifier(config.JhcisDbname)) throw new Exception("ชื่อฐานข้อมูล JHCIS ไม่ถูกต้อง");

            var start = new ProcessStartInfo {
                FileName = client,
                Arguments = "--connect-timeout=5 --protocol=tcp -h " + Quote(config.JhcisHost) + " -P " + config.JhcisPort +
                    " -u " + Quote(config.JhcisUser) + " --default-character-set=utf8 " + Quote(config.JhcisDbname) + " -N -e " + Quote(query),
                UseShellExecute = false, RedirectStandardOutput = true, RedirectStandardError = true, CreateNoWindow = true,
                StandardOutputEncoding = Encoding.UTF8, StandardErrorEncoding = Encoding.UTF8
            };
            start.EnvironmentVariables["MYSQL_PWD"] = config.JhcisPass ?? "";
            using (var process = Process.Start(start))
            {
                string stdout = process.StandardOutput.ReadToEnd();
                string stderr = process.StandardError.ReadToEnd();
                if (!process.WaitForExit(timeoutSeconds * 1000)) { process.Kill(); throw new TimeoutException("หมดเวลารอ JHCIS"); }
                if (process.ExitCode != 0) throw new Exception(string.IsNullOrWhiteSpace(stderr) ? "เชื่อมต่อ JHCIS ไม่สำเร็จ" : stderr.Trim());
                return stdout;
            }
        }

        private static bool IsSafeIdentifier(string value)
        {
            if (string.IsNullOrWhiteSpace(value)) return false;
            foreach (char c in value) if (!(char.IsLetterOrDigit(c) || c == '_')) return false;
            return true;
        }

        private static string Quote(string value)
        {
            return "\"" + (value ?? "").Replace("\\", "\\\\").Replace("\"", "\\\"") + "\"";
        }
    }

    public sealed class LocalBridgeServer
    {
        private readonly AppConfig _config;
        private HttpListener _listener;
        private Thread _thread;
        public LocalBridgeServer(AppConfig config) { _config = config; }

        public void Start()
        {
            _listener = new HttpListener();
            _listener.Prefixes.Add("http://127.0.0.1:18765/");
            _listener.Start();
            _thread = new Thread(ListenLoop) { IsBackground = true };
            _thread.Start();
        }

        public void Stop() { try { if (_listener != null) _listener.Stop(); } catch { } }

        private void ListenLoop()
        {
            while (_listener != null && _listener.IsListening)
            {
                try { ThreadPool.QueueUserWorkItem(Handle, _listener.GetContext()); }
                catch { if (_listener == null || !_listener.IsListening) return; }
            }
        }

        private void Handle(object state)
        {
            var context = (HttpListenerContext)state;
            try
            {
                string origin = context.Request.Headers["Origin"] ?? "";
                Uri allowed;
                if (!Uri.TryCreate(_config.ServerUrl, UriKind.Absolute, out allowed) ||
                    !origin.Equals(allowed.GetLeftPart(UriPartial.Authority), StringComparison.OrdinalIgnoreCase))
                    throw new Exception("Origin ไม่ได้รับอนุญาต");
                context.Response.Headers["Access-Control-Allow-Origin"] = origin;
                context.Response.Headers["Vary"] = "Origin";
                context.Response.Headers["Access-Control-Allow-Methods"] = "POST, OPTIONS";
                context.Response.Headers["Access-Control-Allow-Headers"] = "Content-Type";
                if (context.Request.Headers["Access-Control-Request-Private-Network"] == "true")
                    context.Response.Headers["Access-Control-Allow-Private-Network"] = "true";
                if (context.Request.HttpMethod == "OPTIONS") { context.Response.StatusCode = 204; return; }
                if (context.Request.HttpMethod != "POST") throw new Exception("Method not allowed");

                object result;
                if (context.Request.Url.AbsolutePath == "/test")
                {
                    var test = LocalJhcisBridge.Test(_config);
                    result = new { status = "success", db_version = test.Version, detected_pcucode = test.PcuCode,
                        person_count = test.PersonCount, screen_count = test.ScreenCount, source = "local_station" };
                }
                else if (context.Request.Url.AbsolutePath == "/sync")
                {
                    string body;
                    using (var reader = new StreamReader(context.Request.InputStream, Encoding.UTF8)) body = reader.ReadToEnd();
                    var payload = new JavaScriptSerializer().Deserialize<Dictionary<string, object>>(body);
                    string sql = payload != null && payload.ContainsKey("sql") ? Convert.ToString(payload["sql"]) : "";
                    string requestedHoscode = payload != null && payload.ContainsKey("hoscode") ? Convert.ToString(payload["hoscode"]) : "";
                    JhcisConnectionResult connected = LocalJhcisBridge.Test(_config);
                    if (string.IsNullOrWhiteSpace(requestedHoscode) || !requestedHoscode.Equals(connected.PcuCode, StringComparison.OrdinalIgnoreCase))
                        throw new Exception("รหัส รพ.สต. ใน JHCIS (" + connected.PcuCode + ") ไม่ตรงกับหน้าที่เลือก (" + requestedHoscode + ")");
                    int count = LocalJhcisBridge.ExecutePortalScript(_config, sql);
                    result = new { status = "success", processed = count, detected_pcucode = connected.PcuCode };
                }
                else throw new Exception("Endpoint not found");
                WriteJson(context, result, 200);
            }
            catch (Exception ex) { WriteJson(context, new { status = "error", message = ex.Message }, 400); }
            finally { try { context.Response.Close(); } catch { } }
        }

        private static void WriteJson(HttpListenerContext context, object data, int status)
        {
            byte[] bytes = Encoding.UTF8.GetBytes(new JavaScriptSerializer().Serialize(data));
            context.Response.StatusCode = status;
            context.Response.ContentType = "application/json; charset=utf-8";
            context.Response.ContentLength64 = bytes.Length;
            context.Response.OutputStream.Write(bytes, 0, bytes.Length);
        }
    }

    // ==========================================
    // SIREN SOUND PLAYER (Plays exactly 2 rounds non-blocking)
    // ==========================================
    public class SirenPlayer
    {
        private Thread _soundThread;
        private bool _isPlaying;

        public void PlayTwoRounds()
        {
            Stop();
            _isPlaying = true;
            _soundThread = new Thread(() =>
            {
                for (int round = 0; round < 2; round++)
                {
                    if (!_isPlaying) break;
                    try
                    {
                        Console.Beep(950, 280);
                        if (!_isPlaying) break;
                        Console.Beep(1350, 320);
                        if (!_isPlaying) break;
                        Console.Beep(950, 280);
                        if (!_isPlaying) break;
                        Console.Beep(1450, 380);
                    }
                    catch
                    {
                        System.Media.SystemSounds.Exclamation.Play();
                        Thread.Sleep(500);
                    }
                    Thread.Sleep(200);
                }
                _isPlaying = false;
            });
            _soundThread.IsBackground = true;
            _soundThread.Start();
        }

        public void Stop()
        {
            _isPlaying = false;
            if (_soundThread != null && _soundThread.IsAlive)
            {
                try { _soundThread.Abort(); } catch { }
            }
        }
    }

    // ==========================================
    // EMERGENCY POPUP WINDOW (Wide Layout + Matching Theme)
    // ==========================================
    public class AlertPopupForm : Form
    {
        private AppConfig _config;
        private SirenPlayer _siren;
        private Dictionary<string, object> _currentAlert;

        // UI Controls
        private Label lblHeaderTitle;
        private Label lblHeaderSubtitle;
        private Label lblPatientName;
        private Label lblCid;
        private Label lblAddress;
        private Label lblVhv;
        private Label lblContact;
        private Button btnCopyPhone;
        private Label lblCrisisBadge;
        private Label lblRedFlags;
        private Label lblBpSys;
        private Label lblDtx;
        private Label lblTime;
        private Button btnAck;
        private Button btnRefer;
        private Button btnMap;

        public AlertPopupForm(AppConfig config, SirenPlayer siren)
        {
            _config = config;
            _siren = siren;
            InitializeUI();
        }

        public void UpdateConfig(AppConfig config)
        {
            _config = config;
            if (btnRefer != null)
            {
                btnRefer.Text = string.Format("🏥 สั่งส่งต่อ {0} ({1})", _config.DestHospitalName, _config.DestHospitalCode);
            }
        }

        private void InitializeUI()
        {
            this.Text = "🚨 NCDs Red Alert Station - ศูนย์จัดการเหตุวิกฤตฉุกเฉิน";
            this.Size = new Size(820, 620);
            this.StartPosition = FormStartPosition.CenterScreen;
            this.FormBorderStyle = FormBorderStyle.FixedDialog;
            this.MaximizeBox = false;
            this.MinimizeBox = false;
            this.TopMost = true; // Always on top
            this.BackColor = Color.FromArgb(15, 23, 42); // Slate 900 (Main theme)
            this.ForeColor = Color.FromArgb(248, 250, 252);
            this.Font = ConfigManager.GetSystemFont(10);

            // 1. TOP HEADER BANNER (Vibrant Crimson Red Gradient)
            var pnlHeader = new Panel
            {
                Dock = DockStyle.Top,
                Height = 85,
                BackColor = Color.FromArgb(220, 38, 38), // Crimson 600
                Padding = new Padding(24, 12, 24, 12)
            };

            lblHeaderTitle = new Label
            {
                Text = "🚨 สัญญาณเตือนเหตุวิกฤตฉุกเฉิน (CRITICAL RED ALERT FAST-TRACK)",
                Font = ConfigManager.GetSystemFont(13, FontStyle.Bold),
                ForeColor = Color.White,
                Location = new Point(24, 14),
                AutoSize = true
            };

            lblHeaderSubtitle = new Label
            {
                Text = "ระบบตรวจพบสัญญาณชีพวิกฤตจาก อสม. หน้างาน • กรุณาประเมินและสั่งการส่งต่อทันที",
                Font = ConfigManager.GetSystemFont(10, FontStyle.Regular),
                ForeColor = Color.FromArgb(254, 226, 226),
                Location = new Point(24, 44),
                AutoSize = true
            };

            lblTime = new Label
            {
                Text = DateTime.Now.ToString("HH:mm:ss น."),
                Font = ConfigManager.GetSystemFont(11, FontStyle.Bold),
                ForeColor = Color.FromArgb(254, 240, 138),
                Location = new Point(660, 25),
                AutoSize = true
            };

            pnlHeader.Controls.Add(lblHeaderTitle);
            pnlHeader.Controls.Add(lblHeaderSubtitle);
            pnlHeader.Controls.Add(lblTime);
            this.Controls.Add(pnlHeader);

            // 2. MAIN 2-COLUMN CONTAINER
            var pnlMain = new Panel
            {
                Location = new Point(20, 105),
                Size = new Size(765, 400),
                BackColor = Color.Transparent
            };

            // --- LEFT COLUMN: PATIENT INFO CARD ---
            var cardLeft = new Panel
            {
                Location = new Point(0, 0),
                Size = new Size(410, 395),
                BackColor = Color.FromArgb(30, 41, 59), // Slate 800
                Padding = new Padding(18)
            };

            var lblPatientHeader = new Label
            {
                Text = "👤 ข้อมูลผู้ป่วย & พิกัดเกิดเหตุ",
                Font = ConfigManager.GetSystemFont(11.5f, FontStyle.Bold),
                ForeColor = Color.FromArgb(56, 189, 248), // Sky 400
                Location = new Point(16, 16),
                AutoSize = true
            };

            lblPatientName = new Label
            {
                Text = "นายสมคิด สุขเกษม",
                Font = ConfigManager.GetSystemFont(15, FontStyle.Bold),
                ForeColor = Color.White,
                Location = new Point(16, 48),
                AutoSize = true
            };

            lblCid = new Label
            {
                Text = "🆔 CID: 3-3405-XXXXX-12-3 (อายุ 68 ปี)",
                Font = ConfigManager.GetSystemFont(10, FontStyle.Regular),
                ForeColor = Color.FromArgb(203, 213, 225),
                Location = new Point(16, 85),
                AutoSize = true
            };

            lblAddress = new Label
            {
                Text = "📍 ที่อยู่: บ้านเลขที่ 12/1 ม.2 ต.ตาลสุม (รพ.สต. 07758)",
                Font = ConfigManager.GetSystemFont(10, FontStyle.Regular),
                ForeColor = Color.FromArgb(203, 213, 225),
                Location = new Point(16, 118),
                Size = new Size(375, 42)
            };

            lblCrisisBadge = new Label
            {
                Text = "⚠️ ภาวะวิกฤต: HT Crisis (ความดันโลหิตสูงวิกฤต)",
                Font = ConfigManager.GetSystemFont(10.5f, FontStyle.Bold),
                ForeColor = Color.FromArgb(248, 113, 113),
                Location = new Point(16, 170),
                AutoSize = true
            };

            lblRedFlags = new Label
            {
                Text = "🚨 อาการสำคัญ: ปวดศีรษะรุนแรง ตาพร่ามัว ปากเบี้ยว แขนขาอ่อนแรง",
                Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Regular),
                ForeColor = Color.FromArgb(254, 202, 202),
                Location = new Point(16, 205),
                Size = new Size(375, 55)
            };

            lblVhv = new Label
            {
                Text = "👩‍⚕️ อสม. ผู้แจ้ง: อสม. สมชาย มีสุข (โทร 081-999-8888)",
                Font = ConfigManager.GetSystemFont(10, FontStyle.Bold),
                ForeColor = Color.FromArgb(52, 211, 153), // Emerald 400
                Location = new Point(16, 270),
                AutoSize = true
            };

            lblContact = new Label
            {
                Text = "📱 ติดต่อกลับด่วน: 081-999-8888 (อสม.)",
                Font = ConfigManager.GetSystemFont(10.5f, FontStyle.Bold),
                ForeColor = Color.FromArgb(56, 189, 248), // Sky 400
                Location = new Point(16, 302),
                AutoSize = true
            };

            btnCopyPhone = new Button
            {
                Text = "📋 คัดลอกเบอร์โทรกลับ",
                Font = ConfigManager.GetSystemFont(9, FontStyle.Bold),
                BackColor = Color.FromArgb(16, 185, 129),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Location = new Point(16, 335),
                Size = new Size(180, 32),
                Cursor = Cursors.Hand
            };
            btnCopyPhone.FlatAppearance.BorderSize = 0;
            btnCopyPhone.Click += BtnCopyPhone_Click;

            cardLeft.Controls.Add(lblPatientHeader);
            cardLeft.Controls.Add(lblPatientName);
            cardLeft.Controls.Add(lblCid);
            cardLeft.Controls.Add(lblAddress);
            cardLeft.Controls.Add(lblCrisisBadge);
            cardLeft.Controls.Add(lblRedFlags);
            cardLeft.Controls.Add(lblVhv);
            cardLeft.Controls.Add(lblContact);
            cardLeft.Controls.Add(btnCopyPhone);

            // --- RIGHT COLUMN: VITALS MONITOR CARD ---
            var cardRight = new Panel
            {
                Location = new Point(425, 0),
                Size = new Size(340, 395),
                BackColor = Color.FromArgb(30, 41, 59), // Slate 800
                Padding = new Padding(18)
            };

            var lblVitalsHeader = new Label
            {
                Text = "🩺 ค่าสัญญาณชีพฉุกเฉิน (Vitals)",
                Font = ConfigManager.GetSystemFont(11.5f, FontStyle.Bold),
                ForeColor = Color.FromArgb(251, 191, 36), // Amber 400
                Location = new Point(16, 16),
                AutoSize = true
            };

            // BP Metric Box
            var pnlBp = new Panel
            {
                Location = new Point(16, 50),
                Size = new Size(305, 95),
                BackColor = Color.FromArgb(15, 23, 42),
                Padding = new Padding(12)
            };

            var lblBpLabel = new Label
            {
                Text = "ความดันโลหิต (BLOOD PRESSURE)",
                Font = ConfigManager.GetSystemFont(9, FontStyle.Bold),
                ForeColor = Color.FromArgb(148, 163, 184),
                Location = new Point(12, 10),
                AutoSize = true
            };

            lblBpSys = new Label
            {
                Text = "210 / 118",
                Font = ConfigManager.GetSystemFont(22, FontStyle.Bold),
                ForeColor = Color.FromArgb(239, 68, 68), // Bright Red
                Location = new Point(12, 32),
                AutoSize = true
            };

            var lblBpUnit = new Label
            {
                Text = "mmHg (วิกฤต)",
                Font = ConfigManager.GetSystemFont(10, FontStyle.Bold),
                ForeColor = Color.FromArgb(248, 113, 113),
                Location = new Point(200, 48),
                AutoSize = true
            };

            pnlBp.Controls.Add(lblBpLabel);
            pnlBp.Controls.Add(lblBpSys);
            pnlBp.Controls.Add(lblBpUnit);

            // DTX Metric Box
            var pnlDtx = new Panel
            {
                Location = new Point(16, 155),
                Size = new Size(305, 95),
                BackColor = Color.FromArgb(15, 23, 42),
                Padding = new Padding(12)
            };

            var lblDtxLabel = new Label
            {
                Text = "ระดับน้ำตาลปลายนิ้ว (DTX / BLOOD SUGAR)",
                Font = ConfigManager.GetSystemFont(9, FontStyle.Bold),
                ForeColor = Color.FromArgb(148, 163, 184),
                Location = new Point(12, 10),
                AutoSize = true
            };

            lblDtx = new Label
            {
                Text = "330",
                Font = ConfigManager.GetSystemFont(22, FontStyle.Bold),
                ForeColor = Color.FromArgb(245, 158, 11), // Orange Amber
                Location = new Point(12, 32),
                AutoSize = true
            };

            var lblDtxUnit = new Label
            {
                Text = "mg/dL (สูงวิกฤต)",
                Font = ConfigManager.GetSystemFont(10, FontStyle.Bold),
                ForeColor = Color.FromArgb(251, 191, 36),
                Location = new Point(160, 48),
                AutoSize = true
            };

            pnlDtx.Controls.Add(lblDtxLabel);
            pnlDtx.Controls.Add(lblDtx);
            pnlDtx.Controls.Add(lblDtxUnit);

            // Quick Map Button inside right card
            btnMap = new Button
            {
                Text = "🗺️ เปิดแผนที่นำทาง GPS จุดเกิดเหตุ",
                Font = ConfigManager.GetSystemFont(10, FontStyle.Bold),
                BackColor = Color.FromArgb(16, 185, 129),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Location = new Point(16, 270),
                Size = new Size(305, 44),
                Cursor = Cursors.Hand
            };
            btnMap.FlatAppearance.BorderSize = 0;
            btnMap.Click += BtnMap_Click;

            cardRight.Controls.Add(lblVitalsHeader);
            cardRight.Controls.Add(pnlBp);
            cardRight.Controls.Add(pnlDtx);
            cardRight.Controls.Add(btnMap);

            pnlMain.Controls.Add(cardLeft);
            pnlMain.Controls.Add(cardRight);
            this.Controls.Add(pnlMain);

            // 3. BOTTOM ACTION BAR
            var pnlBottom = new Panel
            {
                Dock = DockStyle.Bottom,
                Height = 85,
                BackColor = Color.FromArgb(15, 23, 42),
                Padding = new Padding(20, 14, 20, 14)
            };

            btnAck = new Button
            {
                Text = "🔕 รับทราบเคส (ปิดเสียงไซเรน)",
                Font = ConfigManager.GetSystemFont(11, FontStyle.Bold),
                BackColor = Color.FromArgb(51, 65, 85), // Slate 700
                ForeColor = Color.FromArgb(241, 245, 249),
                FlatStyle = FlatStyle.Flat,
                Location = new Point(20, 16),
                Size = new Size(340, 52),
                Cursor = Cursors.Hand
            };
            btnAck.FlatAppearance.BorderSize = 0;
            btnAck.Click += BtnAck_Click;

            btnRefer = new Button
            {
                Text = string.Format("🏥 สั่งส่งต่อ {0} ({1})", _config.DestHospitalName, _config.DestHospitalCode),
                Font = ConfigManager.GetSystemFont(11, FontStyle.Bold),
                BackColor = Color.FromArgb(37, 99, 235), // Royal Blue
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Location = new Point(375, 16),
                Size = new Size(410, 52),
                Cursor = Cursors.Hand
            };
            btnRefer.FlatAppearance.BorderSize = 0;
            btnRefer.Click += BtnRefer_Click;

            pnlBottom.Controls.Add(btnAck);
            pnlBottom.Controls.Add(btnRefer);
            this.Controls.Add(pnlBottom);

            this.FormClosing += (s, e) =>
            {
                e.Cancel = true;
                this.Hide();
                if (_siren != null) _siren.Stop();
            };
        }

        public void DisplayAlert(Dictionary<string, object> alert)
        {
            _currentAlert = alert;
            string name = alert.ContainsKey("patient_name") ? alert["patient_name"].ToString() : "ผู้ป่วย";
            string age = alert.ContainsKey("age") && alert["age"] != null ? alert["age"].ToString() : "";
            string cid = alert.ContainsKey("target_cid") ? alert["target_cid"].ToString() : "";
            string sbp = alert.ContainsKey("sbp") && alert["sbp"] != null ? alert["sbp"].ToString() : "-";
            string dbp = alert.ContainsKey("dbp") && alert["dbp"] != null ? alert["dbp"].ToString() : "-";
            string dtx = alert.ContainsKey("dtx") && alert["dtx"] != null ? alert["dtx"].ToString() : "-";
            string houseNo = alert.ContainsKey("house_no") ? alert["house_no"].ToString() : "";
            string moo = alert.ContainsKey("moo") ? alert["moo"].ToString() : "";
            string crisisType = alert.ContainsKey("crisis_type") ? alert["crisis_type"].ToString() : "";
            string redFlags = alert.ContainsKey("red_flags") && alert["red_flags"] != null ? alert["red_flags"].ToString() : "";
            string vhvName = alert.ContainsKey("vhv_name") ? alert["vhv_name"].ToString() : "-";
            string vhvPhone = alert.ContainsKey("vhv_phone") ? alert["vhv_phone"].ToString() : "";
            string contactPhone = alert.ContainsKey("contact_phone") && alert["contact_phone"] != null ? alert["contact_phone"].ToString() : vhvPhone;
            string contactType = alert.ContainsKey("contact_type") && alert["contact_type"] != null ? alert["contact_type"].ToString() : "vhv";
            string contactTypeStr = contactType == "relative" ? "ญาติ/ผู้ป่วย" : "อสม.";
            string hc = alert.ContainsKey("hoscode") ? alert["hoscode"].ToString() : "";

            lblPatientName.Text = name;
            lblCid.Text = string.Format("🆔 CID: {0} {1}", cid, !string.IsNullOrEmpty(age) ? "(อายุ " + age + " ปี)" : "");
            lblAddress.Text = string.Format("📍 ที่อยู่: บ้านเลขที่ {0} ม.{1} ต.ตาลสุม (รพ.สต. {2})", houseNo, moo, hc);
            lblCrisisBadge.Text = string.Format("⚠️ ภาวะวิกฤต: {0}", crisisType);
            lblRedFlags.Text = string.Format("🚨 อาการสำคัญ: {0}", !string.IsNullOrEmpty(redFlags) ? redFlags : "พบค่าสัญญาณชีพสูงเกินเกณฑ์วิกฤต Fast-Track");
            lblVhv.Text = string.Format("👩‍⚕️ อสม. ผู้แจ้ง: {0} {1}", vhvName, !string.IsNullOrEmpty(vhvPhone) ? "(โทร " + vhvPhone + ")" : "");
            lblContact.Text = string.Format("📱 โทรติดต่อกลับ: {0} ({1})", !string.IsNullOrEmpty(contactPhone) ? contactPhone : "ไม่มีเบอร์", contactTypeStr);
            
            btnCopyPhone.Tag = contactPhone;
            btnCopyPhone.Visible = !string.IsNullOrEmpty(contactPhone);
            btnCopyPhone.Text = "📋 คัดลอกเบอร์โทรกลับ";

            lblBpSys.Text = string.Format("{0} / {1}", sbp, dbp);
            lblDtx.Text = dtx;
            lblTime.Text = DateTime.Now.ToString("HH:mm:ss น.");

            btnRefer.Text = string.Format("🏥 สั่งส่งต่อ {0} ({1})", _config.DestHospitalName, _config.DestHospitalCode);

            if (_config.SoundEnabled)
            {
                _siren.PlayTwoRounds();
            }

            this.Show();
            this.WindowState = FormWindowState.Normal;
            this.TopMost = true;
            this.BringToFront();
            this.Activate();
            this.Focus();
        }

        private void BtnCopyPhone_Click(object sender, EventArgs e)
        {
            if (btnCopyPhone.Tag != null)
            {
                string phone = btnCopyPhone.Tag.ToString();
                if (!string.IsNullOrEmpty(phone))
                {
                    try
                    {
                        Clipboard.SetText(phone);
                        btnCopyPhone.Text = "✅ คัดลอกแล้ว!";
                        var t = new System.Windows.Forms.Timer { Interval = 2000 };
                        t.Tick += (s, ev) =>
                        {
                            btnCopyPhone.Text = "📋 คัดลอกเบอร์โทรกลับ";
                            t.Stop();
                            t.Dispose();
                        };
                        t.Start();
                    }
                    catch { }
                }
            }
        }

        private void BtnAck_Click(object sender, EventArgs e)
        {
            _siren.Stop();
            if (_currentAlert != null && _currentAlert.ContainsKey("alert_id"))
            {
                string alertId = _currentAlert["alert_id"].ToString();
                ThreadPool.QueueUserWorkItem(state =>
                {
                    try
                    {
                        using (var wb = new WebClient())
                        {
                            var reqData = new System.Collections.Specialized.NameValueCollection();
                            reqData.Add("action", "acknowledge_alert");
                            reqData.Add("alert_id", alertId);
                            reqData.Add("staff_name", "จนท. (" + _config.Hosname + ")");
                            wb.UploadValues(_config.ServerUrl.TrimEnd('/') + "/api/emergency_alert.php", "POST", reqData);
                        }
                    }
                    catch { }
                });
            }
            this.Hide();
        }

        private void BtnRefer_Click(object sender, EventArgs e)
        {
            _siren.Stop();
            btnRefer.Enabled = false;
            btnRefer.Text = "⏳ กำลังส่งข้อมูล...";

            if (_currentAlert != null && _currentAlert.ContainsKey("alert_id"))
            {
                string alertId = _currentAlert["alert_id"].ToString();
                string destHospital = string.Format("{0} ({1})", _config.DestHospitalName, _config.DestHospitalCode);
                string destCode = _config.DestHospitalCode;

                ThreadPool.QueueUserWorkItem(state =>
                {
                    try
                    {
                        using (var wb = new WebClient())
                        {
                            var reqData = new System.Collections.Specialized.NameValueCollection();
                            reqData.Add("action", "update_referral_status");
                            reqData.Add("alert_id", alertId);
                            reqData.Add("status", "referred_hospital");
                            reqData.Add("referral_destination", destHospital);
                            reqData.Add("referral_hospcode", destCode);
                            reqData.Add("sync_jhcis", (_config.AutoSyncJhcisReferral && _config.Hoscode != "ALL") ? "1" : "0");
                            reqData.Add("staff_name", "จนท. (" + _config.Hosname + ")");

                            byte[] response = wb.UploadValues(_config.ServerUrl.TrimEnd('/') + "/api/emergency_alert.php", "POST", reqData);
                            string resStr = Encoding.UTF8.GetString(response);

                            this.Invoke((MethodInvoker)delegate
                            {
                                btnRefer.Enabled = true;
                                btnRefer.Text = string.Format("🏥 สั่งส่งต่อ {0} ({1})", _config.DestHospitalName, _config.DestHospitalCode);
                                MessageBox.Show(string.Format("✅ สั่งส่งต่อไปยัง {0} (รหัส {1}) เรียบร้อยแล้ว\n{2}", _config.DestHospitalName, _config.DestHospitalCode, (_config.AutoSyncJhcisReferral && _config.Hoscode != "ALL") ? "(ส่งคำขอซิงค์ JHCIS แล้ว)" : ""), "สำเร็จ", MessageBoxButtons.OK, MessageBoxIcon.Information);
                                this.Hide();
                            });
                        }
                    }
                    catch (Exception ex)
                    {
                        this.Invoke((MethodInvoker)delegate
                        {
                            btnRefer.Enabled = true;
                            btnRefer.Text = string.Format("🏥 สั่งส่งต่อ {0} ({1})", _config.DestHospitalName, _config.DestHospitalCode);
                            MessageBox.Show("เกิดข้อผิดพลาด: " + ex.Message, "ข้อผิดพลาด", MessageBoxButtons.OK, MessageBoxIcon.Error);
                        });
                    }
                });
            }
            else
            {
                btnRefer.Enabled = true;
                btnRefer.Text = string.Format("🏥 สั่งส่งต่อ {0} ({1})", _config.DestHospitalName, _config.DestHospitalCode);
                this.Hide();
            }
        }

        private void BtnMap_Click(object sender, EventArgs e)
        {
            if (_currentAlert != null)
            {
                string lat = _currentAlert.ContainsKey("latitude") && _currentAlert["latitude"] != null ? _currentAlert["latitude"].ToString() : "";
                string lng = _currentAlert.ContainsKey("longitude") && _currentAlert["longitude"] != null ? _currentAlert["longitude"].ToString() : "";
                string url = (!string.IsNullOrEmpty(lat) && !string.IsNullOrEmpty(lng))
                    ? string.Format("https://www.google.com/maps?q={0},{1}", lat, lng)
                    : "https://www.google.com/maps/search/อำเภอตาลสุม";
                System.Diagnostics.Process.Start(url);
            }
        }
    }

    // ==========================================
    // SETTINGS CONFIGURATION WINDOW (Matching Main Portal Theme)
    // ==========================================
    public class SettingsForm : Form
    {
        private AppConfig _config;

        private TabControl tabControl;
        private TextBox txtServerUrl;
        private ComboBox cboHealthCenter;
        private TextBox txtHoscode;
        private Label lblUnitLoadStatus;
        private ComboBox cboDestHospital;
        private TextBox txtDestHospCode;
        private TextBox txtDestHospName;
        private CheckBox chkSound;
        private CheckBox chkAutoStart;
        private TextBox txtJhcisHost;
        private TextBox txtJhcisPort;
        private TextBox txtJhcisDb;
        private TextBox txtJhcisUser;
        private TextBox txtJhcisPass;
        private CheckBox chkJhcisAutoSync;
        private Button btnSave;
        private Button btnTestJhcis;
        private Button btnSimulateAlert;

        public SettingsForm(AppConfig config)
        {
            _config = config;
            InitializeUI();
            LoadConfigToUI();
            LoadHealthUnitsFromServerAsync();
        }

        private void InitializeUI()
        {
            this.Text = "NCDs Red Alert Station V3 • Settings";
            this.Size = new Size(720, 640);
            this.StartPosition = FormStartPosition.CenterScreen;
            this.FormBorderStyle = FormBorderStyle.FixedDialog;
            this.MaximizeBox = false;
            this.MinimizeBox = false;
            this.BackColor = Color.FromArgb(231, 238, 246);
            this.Font = ConfigManager.GetSystemFont(9.5f);

            tabControl = new TabControl
            {
                Dock = DockStyle.Top,
                Height = 510,
                Padding = new Point(14, 8)
            };

            // TAB 1: สถานบริการ & เซิร์ฟเวอร์
            var tab1 = new TabPage("🏥 รพ.สต. & เซิร์ฟเวอร์");
            tab1.BackColor = Color.White;
            tab1.Padding = new Padding(18);

            var lbl1 = new Label { Text = "URL เซิร์ฟเวอร์ NCDs Portal", Location = new Point(24, 24), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtServerUrl = new TextBox { Location = new Point(24, 52), Width = 630, Height = 30, AutoSize = false, Text = "https://ncd.ssotansum.com" };

            var lbl2 = new Label { Text = "สังกัดสถานี", Location = new Point(24, 104), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            cboHealthCenter = new ComboBox { Location = new Point(24, 132), Width = 630, DropDownStyle = ComboBoxStyle.DropDownList };
            cboHealthCenter.Items.Add(new KeyValuePair<string, string>("ALL", "ALL - ศูนย์กลาง (รับแจ้งเตือนทุกหน่วยบริการในอำเภอ)"));
            cboHealthCenter.DisplayMember = "Value";
            cboHealthCenter.ValueMember = "Key";
            cboHealthCenter.SelectedIndexChanged += (s, e) =>
            {
                if (cboHealthCenter.SelectedItem != null)
                {
                    var item = (KeyValuePair<string, string>)cboHealthCenter.SelectedItem;
                    txtHoscode.Text = item.Key;
                    if (chkJhcisAutoSync != null)
                    {
                        chkJhcisAutoSync.Enabled = item.Key != "ALL";
                        if (item.Key == "ALL") chkJhcisAutoSync.Checked = false;
                    }
                }
            };

            lblUnitLoadStatus = new Label
            {
                Text = "กำลังโหลดรายชื่อจาก Unit & House Manager...",
                Location = new Point(24, 168),
                Size = new Size(630, 22),
                ForeColor = Color.FromArgb(76, 104, 230)
            };

            var lbl3 = new Label { Text = "รหัสสถานบริการ (Hoscode)", Location = new Point(24, 204), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtHoscode = new TextBox { Location = new Point(24, 232), Width = 220, Height = 30, AutoSize = false, ReadOnly = true, TabStop = false };

            chkAutoStart = new CheckBox
            {
                Text = "⚡ เปิดโปรแกรมนี้อัตโนมัติเมื่อเปิดเครื่องคอมพิวเตอร์ (Auto-start with Windows)",
                Location = new Point(24, 284),
                AutoSize = true,
                Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold),
                Checked = true
            };

            btnSimulateAlert = new Button
            {
                Text = "⚡ ทดสอบสัญญาณภายในเครื่อง (ไม่ส่งข้อมูลขึ้นเว็บ)",
                Location = new Point(24, 336),
                Size = new Size(630, 46),
                BackColor = Color.FromArgb(220, 38, 38),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Font = ConfigManager.GetSystemFont(10, FontStyle.Bold),
                Cursor = Cursors.Hand
            };
            btnSimulateAlert.FlatAppearance.BorderSize = 0;
            btnSimulateAlert.Click += BtnSimulateAlert_Click;

            tab1.Controls.Add(lbl1);
            tab1.Controls.Add(txtServerUrl);
            tab1.Controls.Add(lbl2);
            tab1.Controls.Add(cboHealthCenter);
            tab1.Controls.Add(lblUnitLoadStatus);
            tab1.Controls.Add(lbl3);
            tab1.Controls.Add(txtHoscode);
            tab1.Controls.Add(chkAutoStart);
            tab1.Controls.Add(btnSimulateAlert);

            // TAB 2: โรงพยาบาลปลายทางส่งต่อ
            var tabDest = new TabPage("🏥 รพ. ปลายทางส่งต่อ");
            tabDest.BackColor = Color.White;
            tabDest.Padding = new Padding(18);

            var lblDestHosp = new Label { Text = "เลือกโรงพยาบาลแม่ข่ายปลายทางส่งต่อ:", Location = new Point(18, 16), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            cboDestHospital = new ComboBox { Location = new Point(18, 40), Width = 600, DropDownStyle = ComboBoxStyle.DropDownList };
            cboDestHospital.Items.Add(new KeyValuePair<string, string>("10957", "10957 - โรงพยาบาลตาลสุม (โรงพยาบาลชุมชนแม่ข่ายหลัก)"));
            cboDestHospital.Items.Add(new KeyValuePair<string, string>("10670", "10670 - โรงพยาบาลสรรพสิทธิประสงค์ (โรงพยาบาลศูนย์)"));
            cboDestHospital.Items.Add(new KeyValuePair<string, string>("10738", "10738 - โรงพยาบาลวารินชำราบ"));
            cboDestHospital.Items.Add(new KeyValuePair<string, string>("CUSTOM", "กำหนดรหัสและชื่อโรงพยาบาลเอง..."));
            cboDestHospital.DisplayMember = "Value";
            cboDestHospital.ValueMember = "Key";
            cboDestHospital.SelectedIndexChanged += (s, e) =>
            {
                if (cboDestHospital.SelectedItem != null)
                {
                    var item = (KeyValuePair<string, string>)cboDestHospital.SelectedItem;
                    if (item.Key == "10957")
                    {
                        txtDestHospCode.Text = "10957";
                        txtDestHospName.Text = "โรงพยาบาลตาลสุม";
                    }
                    else if (item.Key == "10670")
                    {
                        txtDestHospCode.Text = "10670";
                        txtDestHospName.Text = "โรงพยาบาลสรรพสิทธิประสงค์";
                    }
                    else if (item.Key == "10738")
                    {
                        txtDestHospCode.Text = "10738";
                        txtDestHospName.Text = "โรงพยาบาลวารินชำราบ";
                    }
                }
            };

            var lblDestCode = new Label { Text = "รหัสสถานบริการปลายทาง (5 หลัก):", Location = new Point(18, 85), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtDestHospCode = new TextBox { Location = new Point(18, 110), Width = 200, Text = "10957" };

            var lblDestName = new Label { Text = "ชื่อโรงพยาบาลปลายทาง:", Location = new Point(240, 85), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtDestHospName = new TextBox { Location = new Point(240, 110), Width = 378, Text = "โรงพยาบาลตาลสุม" };

            var lblDestNote = new Label
            {
                Text = "ℹ️ รหัสสถานบริการ 10957 และชื่อโรงพยาบาลตาลสุม จะถูกบันทึกลงในแฟ้ม visitrefer ของฐานข้อมูล JHCIS และใบส่งต่อ Fast-Track ทันทีเมื่อเจ้าหน้าที่กดสั่งส่งต่อ",
                Location = new Point(18, 160),
                Size = new Size(600, 60),
                ForeColor = Color.FromArgb(71, 85, 105)
            };

            tabDest.Controls.Add(lblDestHosp);
            tabDest.Controls.Add(cboDestHospital);
            tabDest.Controls.Add(lblDestCode);
            tabDest.Controls.Add(txtDestHospCode);
            tabDest.Controls.Add(lblDestName);
            tabDest.Controls.Add(txtDestHospName);
            tabDest.Controls.Add(lblDestNote);

            // TAB 3: การตั้งค่าเสียงเตือน
            var tab2 = new TabPage("🔊 การตั้งค่าเสียง");
            tab2.BackColor = Color.White;
            tab2.Padding = new Padding(18);

            chkSound = new CheckBox
            {
                Text = "🔔 เปิดใช้งานเสียงไซเรนเตือนภัยฉุกเฉินเมื่อมีเคสวิกฤต (ดัง 2 รอบอัตโนมัติ)",
                Location = new Point(18, 25),
                AutoSize = true,
                Font = ConfigManager.GetSystemFont(10, FontStyle.Bold),
                Checked = true
            };

            var lblSoundNote = new Label
            {
                Text = "ℹ️ เมื่อตรวจพบเคสวิกฤต เสียงไซเรนจะดัง 2 รอบแล้วหยุดอัตโนมัติ เพื่อไม่ให้รบกวนการทำงาน",
                Location = new Point(18, 65),
                Size = new Size(600, 40),
                ForeColor = Color.FromArgb(100, 116, 139)
            };

            var btnTestSound = new Button
            {
                Text = "🔊 กดทดสอบเสียงไซเรน (2 รอบ)",
                Location = new Point(18, 120),
                Size = new Size(260, 42),
                BackColor = Color.FromArgb(30, 41, 59),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold),
                Cursor = Cursors.Hand
            };
            btnTestSound.FlatAppearance.BorderSize = 0;
            btnTestSound.Click += (s, e) =>
            {
                var siren = new SirenPlayer();
                siren.PlayTwoRounds();
            };

            tab2.Controls.Add(chkSound);
            tab2.Controls.Add(lblSoundNote);
            tab2.Controls.Add(btnTestSound);

            // TAB 4: การเชื่อมต่อ JHCIS
            var tab3 = new TabPage("💾 ฐานข้อมูล JHCIS");
            tab3.BackColor = Color.White;
            tab3.Padding = new Padding(18);

            var lblJHost = new Label { Text = "Host JHCIS (เช่น localhost หรือ IP):", Location = new Point(18, 15), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtJhcisHost = new TextBox { Location = new Point(18, 40), Width = 380, Text = "localhost" };

            var lblJPort = new Label { Text = "พอร์ต MySQL:", Location = new Point(420, 15), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtJhcisPort = new TextBox { Location = new Point(420, 40), Width = 198, Text = "3333" };

            var lblJDb = new Label { Text = "ชื่อฐานข้อมูล (Database):", Location = new Point(18, 78), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtJhcisDb = new TextBox { Location = new Point(18, 102), Width = 600, Text = "jhcisdb" };

            var lblJUser = new Label { Text = "Username:", Location = new Point(18, 140), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtJhcisUser = new TextBox { Location = new Point(18, 164), Width = 285, Text = "root" };

            var lblJPass = new Label { Text = "Password:", Location = new Point(325, 140), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtJhcisPass = new TextBox { Location = new Point(325, 164), Width = 293, UseSystemPasswordChar = true };

            chkJhcisAutoSync = new CheckBox
            {
                Text = "⚡ ซิงค์สร้าง Record ส่งต่อไปยังตาราง visitrefer ใน JHCIS อัตโนมัติเมื่อกดสั่งส่งต่อ",
                Location = new Point(18, 215),
                AutoSize = true,
                Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold),
                Checked = true
            };

            btnTestJhcis = new Button
            {
                Text = "🔌 ทดสอบการเชื่อมต่อฐานข้อมูล JHCIS",
                Location = new Point(18, 260),
                Size = new Size(600, 40),
                BackColor = Color.FromArgb(16, 185, 129),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold),
                Cursor = Cursors.Hand
            };
            btnTestJhcis.FlatAppearance.BorderSize = 0;
            btnTestJhcis.Click += BtnTestJhcis_Click;

            tab3.Controls.Add(lblJHost);
            tab3.Controls.Add(txtJhcisHost);
            tab3.Controls.Add(lblJPort);
            tab3.Controls.Add(txtJhcisPort);
            tab3.Controls.Add(lblJDb);
            tab3.Controls.Add(txtJhcisDb);
            tab3.Controls.Add(lblJUser);
            tab3.Controls.Add(txtJhcisUser);
            tab3.Controls.Add(lblJPass);
            tab3.Controls.Add(txtJhcisPass);
            tab3.Controls.Add(chkJhcisAutoSync);
            tab3.Controls.Add(btnTestJhcis);

            tabControl.TabPages.Add(tab1);
            tabControl.TabPages.Add(tabDest);
            tabControl.TabPages.Add(tab2);
            tabControl.TabPages.Add(tab3);

            this.Controls.Add(tabControl);

            // Bottom Actions Panel
            var pnlBottom = new Panel
            {
                Dock = DockStyle.Bottom,
                Height = 65,
                BackColor = Color.FromArgb(241, 245, 249),
                Padding = new Padding(18, 12, 18, 12)
            };

            btnSave = new Button
            {
                Text = "💾 บันทึกการตั้งค่า",
                Size = new Size(180, 42),
                Location = new Point(455, 10),
                BackColor = Color.FromArgb(37, 99, 235),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Font = ConfigManager.GetSystemFont(10, FontStyle.Bold),
                Cursor = Cursors.Hand
            };
            btnSave.FlatAppearance.BorderSize = 0;
            btnSave.Click += BtnSave_Click;

            var btnCancel = new Button
            {
                Text = "ปิด",
                Size = new Size(110, 42),
                Location = new Point(335, 10),
                BackColor = Color.White,
                ForeColor = Color.FromArgb(15, 23, 42),
                FlatStyle = FlatStyle.Flat,
                Font = ConfigManager.GetSystemFont(10),
                Cursor = Cursors.Hand
            };
            btnCancel.Click += (s, e) => this.Close();

            pnlBottom.Controls.Add(btnSave);
            pnlBottom.Controls.Add(btnCancel);
            this.Controls.Add(pnlBottom);

            ApplyNeumorphicTheme(this);
        }

        private void LoadHealthUnitsFromServerAsync()
        {
            string serverUrl = (_config.ServerUrl ?? "https://ncd.ssotansum.com").TrimEnd('/');
            string selectedHoscode = _config.Hoscode ?? "ALL";
            ThreadPool.QueueUserWorkItem(state =>
            {
                try
                {
                    string json = DownloadUnitDirectory(serverUrl + "/api/health_units.php");

                    var serializer = new JavaScriptSerializer();
                    var payload = serializer.Deserialize<Dictionary<string, object>>(json);
                    if (payload == null || !payload.ContainsKey("status") || payload["status"].ToString() != "success")
                        throw new Exception("เซิร์ฟเวอร์ไม่ส่งรายชื่อหน่วยบริการ");
                    var units = payload.ContainsKey("units") ? payload["units"] as System.Collections.ArrayList : null;
                    if (units == null || units.Count == 0) throw new Exception("ไม่พบหน่วยบริการในระบบ");

                    this.BeginInvoke((MethodInvoker)delegate
                    {
                        cboHealthCenter.BeginUpdate();
                        try
                        {
                            cboHealthCenter.Items.Clear();
                            cboHealthCenter.Items.Add(new KeyValuePair<string, string>("ALL", "ALL - ศูนย์กลาง (รับแจ้งเตือนทุกหน่วยบริการในอำเภอ)"));
                            foreach (object itemObject in units)
                            {
                                var unit = itemObject as Dictionary<string, object>;
                                if (unit == null) continue;
                                string code = unit.ContainsKey("hoscode") && unit["hoscode"] != null ? unit["hoscode"].ToString().Trim() : "";
                                string name = unit.ContainsKey("hosname") && unit["hosname"] != null ? unit["hosname"].ToString().Trim() : "";
                                if (code == "" || name == "") continue;
                                cboHealthCenter.Items.Add(new KeyValuePair<string, string>(code, code + " - " + name));
                            }
                            SelectHealthUnit(selectedHoscode);
                            lblUnitLoadStatus.Text = "โหลดจาก Unit & House Manager แล้ว " + (cboHealthCenter.Items.Count - 1) + " หน่วยบริการ";
                            lblUnitLoadStatus.ForeColor = Color.FromArgb(22, 163, 74);
                        }
                        finally { cboHealthCenter.EndUpdate(); }
                    });
                }
                catch (Exception ex)
                {
                    try { File.AppendAllText("red_alert_v3_debug.log", DateTime.Now + " - Unit directory: " + ex.Message + "\r\n"); } catch { }
                    this.BeginInvoke((MethodInvoker)delegate
                    {
                        if (cboHealthCenter.Items.Count == 1 && selectedHoscode != "" && selectedHoscode != "ALL")
                            cboHealthCenter.Items.Add(new KeyValuePair<string, string>(selectedHoscode, selectedHoscode + " - " + (_config.Hosname ?? "หน่วยบริการที่บันทึกไว้")));
                        SelectHealthUnit(selectedHoscode);
                        lblUnitLoadStatus.Text = "โหลดรายชื่อไม่สำเร็จ: " + ex.Message;
                        lblUnitLoadStatus.ForeColor = Color.FromArgb(220, 38, 38);
                    });
                }
            });
        }

        private string DownloadUnitDirectory(string primaryUrl)
        {
            string fallbackUrl = primaryUrl.Replace("/api/health_units.php", "/api/emergency_alert.php?action=get_health_units");
            Exception lastError = null;
            foreach (string url in new[] { primaryUrl, fallbackUrl })
            {
                try
                {
                    var request = (HttpWebRequest)WebRequest.Create(url);
                    request.Method = "GET";
                    request.Timeout = 7000;
                    request.ReadWriteTimeout = 7000;
                    using (var response = (HttpWebResponse)request.GetResponse())
                    using (var reader = new StreamReader(response.GetResponseStream(), Encoding.UTF8))
                        return reader.ReadToEnd();
                }
                catch (Exception ex) { lastError = ex; }
            }
            throw new Exception(lastError != null ? lastError.Message : "ติดต่อ API ไม่สำเร็จ");
        }

        private void SelectHealthUnit(string hoscode)
        {
            for (int i = 0; i < cboHealthCenter.Items.Count; i++)
            {
                var item = (KeyValuePair<string, string>)cboHealthCenter.Items[i];
                if (item.Key == hoscode) { cboHealthCenter.SelectedIndex = i; return; }
            }
            cboHealthCenter.SelectedIndex = 0;
        }

        private void ApplyNeumorphicTheme(Control root)
        {
            Color surface = Color.FromArgb(232, 238, 244);
            Color raised = Color.FromArgb(238, 243, 248);
            Color text = Color.FromArgb(55, 62, 72);
            root.ForeColor = text;
            root.BackColor = surface;

            foreach (Control control in root.Controls)
            {
                if (control is TabPage || control is Panel)
                    control.BackColor = surface;
                else if (control is TextBox)
                {
                    ((TextBox)control).BorderStyle = BorderStyle.None;
                    control.BackColor = raised;
                    control.ForeColor = text;
                }
                else if (control is ComboBox)
                {
                    ((ComboBox)control).FlatStyle = FlatStyle.Flat;
                    control.BackColor = raised;
                    control.ForeColor = text;
                }
                else if (control is Button)
                {
                    var button = (Button)control;
                    button.FlatStyle = FlatStyle.Flat;
                    button.FlatAppearance.BorderSize = 0;
                    button.FlatAppearance.MouseOverBackColor = ControlPaint.Light(button.BackColor, 0.08f);
                    button.FlatAppearance.MouseDownBackColor = ControlPaint.Dark(button.BackColor, 0.05f);
                    SetRoundedRegion(button, 15);
                }
                else if (control is CheckBox || control is Label)
                    control.BackColor = Color.Transparent;

                if (control.HasChildren) ApplyNeumorphicTheme(control);
            }

            root.Paint += NeumorphicSurface_Paint;
            root.Resize += (s, e) => root.Invalidate();

            tabControl.Appearance = TabAppearance.Normal;
            tabControl.DrawMode = TabDrawMode.OwnerDrawFixed;
            tabControl.ItemSize = new Size(154, 42);
            tabControl.SizeMode = TabSizeMode.Fixed;
            tabControl.BackColor = surface;
            tabControl.DrawItem -= TabControl_DrawItem;
            tabControl.DrawItem += TabControl_DrawItem;
        }

        private void NeumorphicSurface_Paint(object sender, PaintEventArgs e)
        {
            var parent = sender as Control;
            if (parent == null) return;
            e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
            foreach (Control child in parent.Controls)
            {
                if (!(child is TextBox || child is ComboBox || child is Button)) continue;
                Rectangle box = child.Bounds;
                box.Inflate(5, 5);
                DrawSoftShadow(e.Graphics, box, 14, child is TextBox || child is ComboBox);
            }
        }

        private void DrawSoftShadow(Graphics g, Rectangle bounds, int radius, bool insetLook)
        {
            using (GraphicsPath darkPath = RoundedPath(new Rectangle(bounds.X + 4, bounds.Y + 5, bounds.Width, bounds.Height), radius))
            using (var dark = new SolidBrush(Color.FromArgb(insetLook ? 26 : 42, 139, 153, 168)))
                g.FillPath(dark, darkPath);
            using (GraphicsPath lightPath = RoundedPath(new Rectangle(bounds.X - 3, bounds.Y - 3, bounds.Width, bounds.Height), radius))
            using (var light = new SolidBrush(Color.FromArgb(insetLook ? 150 : 205, 255, 255, 255)))
                g.FillPath(light, lightPath);
            using (GraphicsPath facePath = RoundedPath(bounds, radius))
            using (var face = new SolidBrush(Color.FromArgb(238, 243, 248)))
                g.FillPath(face, facePath);
        }

        private void TabControl_DrawItem(object sender, DrawItemEventArgs e)
        {
            bool selected = (e.State & DrawItemState.Selected) == DrawItemState.Selected;
            Rectangle rect = e.Bounds;
            rect.Inflate(-5, -5);
            e.Graphics.SmoothingMode = SmoothingMode.AntiAlias;
            DrawSoftShadow(e.Graphics, rect, 14, false);
            using (GraphicsPath path = RoundedPath(rect, 14))
            using (var brush = new SolidBrush(selected ? Color.FromArgb(76, 104, 230) : Color.FromArgb(238, 243, 248)))
                e.Graphics.FillPath(brush, path);
            TextRenderer.DrawText(e.Graphics, tabControl.TabPages[e.Index].Text, ConfigManager.GetSystemFont(8.8f, FontStyle.Bold), rect,
                selected ? Color.White : Color.FromArgb(65, 73, 84), TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter | TextFormatFlags.EndEllipsis);
        }

        private static GraphicsPath RoundedPath(Rectangle rect, int radius)
        {
            int diameter = Math.Max(2, radius * 2);
            var path = new GraphicsPath();
            path.AddArc(rect.X, rect.Y, diameter, diameter, 180, 90);
            path.AddArc(rect.Right - diameter, rect.Y, diameter, diameter, 270, 90);
            path.AddArc(rect.Right - diameter, rect.Bottom - diameter, diameter, diameter, 0, 90);
            path.AddArc(rect.X, rect.Bottom - diameter, diameter, diameter, 90, 90);
            path.CloseFigure();
            return path;
        }

        private static void SetRoundedRegion(Control control, int radius)
        {
            control.Region = new Region(RoundedPath(new Rectangle(0, 0, control.Width, control.Height), radius));
            control.Resize += (s, e) =>
            {
                if (control.Region != null) control.Region.Dispose();
                control.Region = new Region(RoundedPath(new Rectangle(0, 0, control.Width, control.Height), radius));
            };
        }

        private void LoadConfigToUI()
        {
            txtServerUrl.Text = _config.ServerUrl;
            txtHoscode.Text = _config.Hoscode;
            txtDestHospCode.Text = !string.IsNullOrEmpty(_config.DestHospitalCode) ? _config.DestHospitalCode : "10957";
            txtDestHospName.Text = !string.IsNullOrEmpty(_config.DestHospitalName) ? _config.DestHospitalName : "โรงพยาบาลตาลสุม";
            chkSound.Checked = _config.SoundEnabled;
            chkAutoStart.Checked = true;
            txtJhcisHost.Text = _config.JhcisHost;
            txtJhcisPort.Text = _config.JhcisPort.ToString();
            txtJhcisDb.Text = _config.JhcisDbname;
            txtJhcisUser.Text = _config.JhcisUser;
            txtJhcisPass.Text = _config.JhcisPass;
            chkJhcisAutoSync.Checked = _config.AutoSyncJhcisReferral;

            for (int i = 0; i < cboHealthCenter.Items.Count; i++)
            {
                if (cboHealthCenter.Items[i] != null)
                {
                    var item = (KeyValuePair<string, string>)cboHealthCenter.Items[i];
                    if (item.Key == _config.Hoscode)
                    {
                        cboHealthCenter.SelectedIndex = i;
                        break;
                    }
                }
            }

            for (int i = 0; i < cboDestHospital.Items.Count; i++)
            {
                if (cboDestHospital.Items[i] != null)
                {
                    var item = (KeyValuePair<string, string>)cboDestHospital.Items[i];
                    if (item.Key == _config.DestHospitalCode)
                    {
                        cboDestHospital.SelectedIndex = i;
                        break;
                    }
                }
            }
            if (cboDestHospital.SelectedIndex < 0) cboDestHospital.SelectedIndex = 0;
        }

        private void BtnSave_Click(object sender, EventArgs e)
        {
            if (string.IsNullOrWhiteSpace(txtHoscode.Text))
            {
                MessageBox.Show(this, "กรุณาเลือก ALL หรือหน่วยบริการจากรายการก่อนบันทึก", "ต้องเลือกสังกัด", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                tabControl.SelectedIndex = 0;
                cboHealthCenter.Focus();
                return;
            }
            _config.ServerUrl = txtServerUrl.Text.Trim();
            _config.Hoscode = txtHoscode.Text.Trim();
            _config.Hosname = cboHealthCenter.Text;
            _config.DestHospitalCode = txtDestHospCode.Text.Trim();
            _config.DestHospitalName = txtDestHospName.Text.Trim();
            _config.SoundEnabled = chkSound.Checked;
            _config.AutoStartWithWindows = chkAutoStart.Checked;
            _config.JhcisHost = txtJhcisHost.Text.Trim();
            int p;
            _config.JhcisPort = int.TryParse(txtJhcisPort.Text.Trim(), out p) ? p : 3333;
            _config.JhcisDbname = txtJhcisDb.Text.Trim();
            _config.JhcisUser = txtJhcisUser.Text.Trim();
            _config.JhcisPass = txtJhcisPass.Text;
            _config.AutoSyncJhcisReferral = txtHoscode.Text.Trim() != "ALL" && chkJhcisAutoSync.Checked;
            _config.IsFirstRunSetupDone = true;

            ConfigManager.Save(_config);
            ConfigManager.CreateDesktopShortcut();

            MessageBox.Show(string.Format("✅ บันทึกการตั้งค่าเรียบร้อยแล้ว\n- โฮสต์เชื่อมต่อ: {0}\n- รพ. ปลายทางส่งต่อ: {1} ({2})\n- สร้าง Shortcut บนหน้าจอ Desktop ให้แล้ว", _config.ServerUrl, _config.DestHospitalName, _config.DestHospitalCode), "สำเร็จ", MessageBoxButtons.OK, MessageBoxIcon.Information);
            this.Close();
        }

        private void BtnTestJhcis_Click(object sender, EventArgs e)
        {
            btnTestJhcis.Enabled = false;
            btnTestJhcis.Text = "⏳ กำลังทดสอบ JHCIS จากเครื่องนี้...";

            int testPort;
            var testConfig = new AppConfig {
                JhcisHost = txtJhcisHost.Text.Trim(),
                JhcisPort = int.TryParse(txtJhcisPort.Text.Trim(), out testPort) ? testPort : 3333,
                JhcisDbname = txtJhcisDb.Text.Trim(),
                JhcisUser = txtJhcisUser.Text.Trim(),
                JhcisPass = txtJhcisPass.Text
            };

            ThreadPool.QueueUserWorkItem(state =>
            {
                try
                {
                    JhcisConnectionResult result = LocalJhcisBridge.Test(testConfig);
                    this.Invoke((MethodInvoker)delegate
                    {
                        btnTestJhcis.Enabled = true;
                        btnTestJhcis.Text = "🔌 ทดสอบการเชื่อมต่อฐานข้อมูล JHCIS";
                        string selectedHoscode = txtHoscode.Text.Trim();
                        string mismatch = selectedHoscode != "" && selectedHoscode != "ALL" && selectedHoscode != result.PcuCode
                            ? "\n\n⚠️ รหัสใน JHCIS ไม่ตรงกับสถานีที่เลือก (" + selectedHoscode + ")" : "";
                        MessageBox.Show("✅ เชื่อมต่อ JHCIS จากเครื่อง Local/IP สำเร็จ" +
                            "\n- MySQL: " + result.Version +
                            "\n- รหัสสถานบริการ: " + result.PcuCode +
                            "\n- บุคคล: " + result.PersonCount.ToString("N0") +
                            "\n- คัดกรอง NCD: " + result.ScreenCount.ToString("N0") + mismatch,
                            "Local JHCIS Bridge V3", MessageBoxButtons.OK,
                            mismatch == "" ? MessageBoxIcon.Information : MessageBoxIcon.Warning);
                    });
                }
                catch (Exception ex)
                {
                    this.Invoke((MethodInvoker)delegate
                    {
                        btnTestJhcis.Enabled = true;
                        btnTestJhcis.Text = "🔌 ทดสอบการเชื่อมต่อฐานข้อมูล JHCIS";
                        MessageBox.Show("❌ เกิดข้อผิดพลาดในการทดสอบ: " + ex.Message, "ข้อผิดพลาด", MessageBoxButtons.OK, MessageBoxIcon.Error);
                    });
                }
            });
        }

        private void BtnSimulateAlert_Click(object sender, EventArgs e)
        {
            btnSimulateAlert.Enabled = false;
            btnSimulateAlert.Text = "⏳ กำลังเปิดสัญญาณทดสอบ...";

            var mockAlert = new Dictionary<string, object>
            {
                { "alert_id", 9999 },
                { "hoscode", txtHoscode.Text.Trim() },
                { "target_cid", "3340500123456" },
                { "patient_name", "นายสมคิด สุขเกษม" },
                { "age", 68 },
                { "house_no", "12/1" },
                { "moo", "2" },
                { "sub_district_code", "341601" },
                { "crisis_type", "HT Crisis (ความดันโลหิตสูงวิกฤต)" },
                { "sbp", 210 },
                { "dbp", 118 },
                { "dtx", 330 },
                { "red_flags", "ปวดศีรษะรุนแรง ตาพร่ามัว ปากเบี้ยว แขนขาอ่อนแรง" },
                { "vhv_name", "อสม. สมชาย มีสุข" },
                { "vhv_phone", "081-999-8888" },
                { "latitude", 15.4350 },
                { "longitude", 104.9860 }
            };

            try
            {
                var testConfig = ConfigManager.Load();
                testConfig.SoundEnabled = chkSound.Checked;
                var siren = new SirenPlayer();
                var testPopup = new AlertPopupForm(testConfig, siren);
                testPopup.FormClosed += (s, args) => siren.Stop();
                testPopup.DisplayAlert(mockAlert);
                btnSimulateAlert.Text = "✅ เปิดหน้าต่างทดสอบแล้ว — ปิดได้จากหน้าต่างแจ้งเตือน";
                var resetTimer = new System.Windows.Forms.Timer { Interval = 2500 };
                resetTimer.Tick += (s, args) =>
                {
                    resetTimer.Stop();
                    resetTimer.Dispose();
                    btnSimulateAlert.Enabled = true;
                    btnSimulateAlert.Text = "⚡ ทดสอบสัญญาณภายในเครื่อง (ไม่ส่งข้อมูลขึ้นเว็บ)";
                };
                resetTimer.Start();
            }
            catch (Exception ex)
            {
                btnSimulateAlert.Enabled = true;
                btnSimulateAlert.Text = "⚡ ทดสอบสัญญาณภายในเครื่อง (ไม่ส่งข้อมูลขึ้นเว็บ)";
                MessageBox.Show(this, "เปิดการทดสอบไม่สำเร็จ: " + ex.Message, "V3", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }
    }

    // ==========================================
    // SYSTEM TRAY CONTEXT & BACKGROUND LISTENER
    // ==========================================
    public class RedAlertApplicationContext : ApplicationContext
    {
        private Form _hiddenAnchor;
        private NotifyIcon _trayIcon;
        private AppConfig _config;
        private SirenPlayer _siren;
        private AlertPopupForm _popupForm;
        private SettingsForm _settingsForm;
        private System.Windows.Forms.Timer _pollTimer;
        private int _lastAlertIdSeen = 0;
        private bool _isPolling = false;
        private SynchronizationContext _syncContext;
        private LocalBridgeServer _localBridge;

        public RedAlertApplicationContext()
        {
            _hiddenAnchor = new Form
            {
                ShowInTaskbar = false,
                WindowState = FormWindowState.Minimized,
                Size = new Size(0, 0),
                FormBorderStyle = FormBorderStyle.None,
                Opacity = 0
            };
            _hiddenAnchor.Show();
            _hiddenAnchor.Hide();
            this.MainForm = _hiddenAnchor;

            _syncContext = SynchronizationContext.Current ?? new WindowsFormsSynchronizationContext();
            bool isFirstRun = !ConfigManager.ConfigExists();
            _config = ConfigManager.Load();
            _localBridge = new LocalBridgeServer(_config);
            try { _localBridge.Start(); } catch (Exception ex) { try { File.AppendAllText("red_alert_v3_debug.log", DateTime.Now + " - Local bridge: " + ex.Message + "\r\n"); } catch { } }
            bool needsHealthUnit = string.IsNullOrWhiteSpace(_config.Hoscode);
            _siren = new SirenPlayer();
            _popupForm = new AlertPopupForm(_config, _siren);
            IntPtr dummyHandle = _popupForm.Handle; // Ensure window handle is created upfront

            if (isFirstRun || !_config.IsFirstRunSetupDone)
            {
                // Do not replace the legacy Auto Start until the user reviews and saves V3 settings.
            }

            _trayIcon = new NotifyIcon
            {
                Icon = SystemIcons.Shield,
                Text = "🚨 NCDs Red Alert V3 (" + (string.IsNullOrWhiteSpace(_config.Hoscode) ? "ยังไม่ตั้งค่า" : _config.Hoscode) + ")",
                Visible = true
            };

            var contextMenu = new ContextMenuStrip();
            contextMenu.Items.Add("⚙️ ตั้งค่า (Settings)", null, (s, e) => ShowSettings());
            contextMenu.Items.Add("🖥️ เปิดหน้าต่างรับสัญญาณ (Web)", null, (s, e) =>
            {
                System.Diagnostics.Process.Start(_config.ServerUrl.TrimEnd('/') + "/admin/emergency_receiver.php?hoscode=" + _config.Hoscode);
            });
            contextMenu.Items.Add("🏥 ศูนย์ส่งต่อผู้ป่วย (Referrals)", null, (s, e) =>
            {
                System.Diagnostics.Process.Start(_config.ServerUrl.TrimEnd('/') + "/admin/critical_referrals.php?hoscode=" + _config.Hoscode);
            });
            contextMenu.Items.Add(new ToolStripSeparator());
            contextMenu.Items.Add("🗑️ ถอนการติดตั้ง V3", null, (s, e) => { if (UninstallManager.ConfirmAndPrepare()) Exit(); });
            contextMenu.Items.Add("❌ ออกจากโปรแกรม", null, (s, e) => Exit());

            _trayIcon.ContextMenuStrip = contextMenu;
            _trayIcon.DoubleClick += (s, e) => ShowSettings();

            // Background Poll Timer (every 3 seconds)
            _pollTimer = new System.Windows.Forms.Timer { Interval = 3000 };
            _pollTimer.Tick += PollTimer_Tick;
            _pollTimer.Start();

            string statusMsg = needsHealthUnit
                ? "ยังไม่เริ่มรับแจ้งเตือน: กรุณาเลือกสังกัด Station"
                : (_config.Hoscode == "ALL"
                    ? "ศูนย์กลางกำลังรับแจ้งเตือนจากทุกหน่วยบริการในอำเภอ"
                    : "กำลังเฝ้าระวังเคสวิกฤตฉุกเฉินสำหรับหน่วยบริการ " + _config.Hoscode + " 24 ชม.");

            try
            {
                _trayIcon.ShowBalloonTip(3000, "NCDs Red Alert Station พร้อมทำงาน", statusMsg, ToolTipIcon.Info);
            }
            catch { }

            if (isFirstRun || !_config.IsFirstRunSetupDone || needsHealthUnit)
            {
                ShowSettings();
            }
        }

        private void ShowSettings()
        {
            if (_settingsForm != null && !_settingsForm.IsDisposed)
            {
                if (_settingsForm.WindowState == FormWindowState.Minimized)
                    _settingsForm.WindowState = FormWindowState.Normal;
                _settingsForm.Show();
                _settingsForm.BringToFront();
                _settingsForm.Activate();
                return;
            }

            _settingsForm = new SettingsForm(_config);
            _settingsForm.FormClosed += (s, e) =>
            {
                _settingsForm = null;
                _config = ConfigManager.Load();
                if (_popupForm != null && !_popupForm.IsDisposed)
                    _popupForm.UpdateConfig(_config);
                _trayIcon.Text = "🚨 NCDs Red Alert V3 (" + (string.IsNullOrWhiteSpace(_config.Hoscode) ? "ยังไม่ตั้งค่า" : _config.Hoscode) + ")";
            };
            _settingsForm.Show();
            _settingsForm.BringToFront();
            _settingsForm.Activate();
        }

        private void PollTimer_Tick(object sender, EventArgs e)
        {
            if (string.IsNullOrWhiteSpace(_config.Hoscode)) return;
            if (_isPolling) return;
            _isPolling = true;

            ThreadPool.QueueUserWorkItem(state =>
            {
                try
                {
                    string url = string.Format("{0}/api/emergency_alert.php?action=get_active_alerts&hoscode={1}", _config.ServerUrl.TrimEnd('/'), _config.Hoscode);
                    using (var wb = new WebClient())
                    {
                        wb.Encoding = Encoding.UTF8;
                        string json = wb.DownloadString(url);
                        var serializer = new JavaScriptSerializer();
                        var data = serializer.Deserialize<Dictionary<string, object>>(json);

                        if (data != null && data.ContainsKey("status") && data["status"].ToString() == "success")
                        {
                            var alerts = data["alerts"] as System.Collections.ArrayList;
                            if (alerts != null && alerts.Count > 0)
                            {
                                foreach (object item in alerts)
                                {
                                    var alert = item as Dictionary<string, object>;
                                    if (alert == null) continue;

                                    string status = alert.ContainsKey("alert_status") ? alert["alert_status"].ToString() : "";
                                    int alertId = alert.ContainsKey("alert_id") ? Convert.ToInt32(alert["alert_id"]) : 0;

                                    if (status == "pending" && alertId > _lastAlertIdSeen)
                                    {
                                        _lastAlertIdSeen = alertId;
                                        string patientName = alert.ContainsKey("patient_name") ? alert["patient_name"].ToString() : "ผู้ป่วย";
                                        
                                        _syncContext.Post(s =>
                                        {
                                            try
                                            {
                                                _trayIcon.ShowBalloonTip(5000, "🚨 แจ้งเตือนเหตุวิกฤตฉุกเฉิน!", "พบเคสวิกฤต: " + patientName, ToolTipIcon.Warning);

                                                if (_popupForm == null || _popupForm.IsDisposed)
                                                {
                                                    _popupForm = new AlertPopupForm(_config, _siren);
                                                }

                                                _popupForm.DisplayAlert(alert);
                                            }
                                            catch (Exception ex)
                                            {
                                                try { File.AppendAllText("red_alert_v3_debug.log", DateTime.Now + " - " + ex.ToString() + "\r\n"); } catch { }
                                            }
                                        }, null);

                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
                catch (Exception ex)
                {
                    try { File.AppendAllText("red_alert_v3_debug.log", DateTime.Now + " - Poll error: " + ex.Message + "\r\n"); } catch { }
                }
                finally
                {
                    _isPolling = false;
                }
            });
        }

        private void Exit()
        {
            if (_localBridge != null) _localBridge.Stop();
            _pollTimer.Stop();
            _siren.Stop();
            _trayIcon.Visible = false;
            Application.Exit();
        }
    }

    public static class Program
    {
        private static Mutex _singleInstance;

        [STAThread]
        public static void Main()
        {
            try
            {
                bool createdNew;
                _singleInstance = new Mutex(true, "NCDsRedAlertStationV3-2C82E2EB", out createdNew);
                if (!createdNew)
                {
                    MessageBox.Show("NCDs Red Alert Station V3 กำลังทำงานอยู่แล้ว", "V3", MessageBoxButtons.OK, MessageBoxIcon.Information);
                    return;
                }
                ServicePointManager.Expect100Continue = true;
                ServicePointManager.SecurityProtocol = (SecurityProtocolType)3072 | (SecurityProtocolType)768 | SecurityProtocolType.Tls;
                ServicePointManager.ServerCertificateValidationCallback = delegate { return true; };

                Application.EnableVisualStyles();
                Application.SetCompatibleTextRenderingDefault(false);
                Application.Run(new RedAlertApplicationContext());
            }
            catch (Exception ex)
            {
                try { File.WriteAllText("red_alert_v3_error.log", ex.ToString()); } catch { }
                MessageBox.Show("ข้อผิดพลาดในการเริ่มต้นโปรแกรม:\n" + ex.Message, "Red Alert V3 Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }
    }
}
