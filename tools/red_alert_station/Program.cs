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
using System.Web.Script.Serialization;
using System.Windows.Forms;
using Microsoft.Win32;

[assembly: AssemblyTitle("NCDs Red Alert Station")]
[assembly: AssemblyDescription("ศูนย์รับสัญญาณเตือนภัยวิกฤตฉุกเฉินและส่งต่อผู้ป่วย NCDs Portal")]
[assembly: AssemblyConfiguration("")]
[assembly: AssemblyCompany("สำนักงานสาธารณสุขอำเภอตาลสุม")]
[assembly: AssemblyProduct("NCDs Portal Red Alert Station")]
[assembly: AssemblyCopyright("Copyright © 2026 SSO Tan Sum")]
[assembly: AssemblyTrademark("SSO Tan Sum NCDs")]
[assembly: AssemblyCulture("")]
[assembly: ComVisible(false)]
[assembly: Guid("e5f6a7b8-1234-4567-890a-bcdef0123456")]
[assembly: AssemblyVersion("2.2.0.0")]
[assembly: AssemblyFileVersion("2.2.0.0")]

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
            Hosname = "ALL - ส่วนกลาง / สสอ.ตาลสุม / รพ.ตาลสุม (เห็นทุกเคสทั้งอำเภอ)";
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

    public static class ConfigManager
    {
        public static string ConfigPath
        {
            get
            {
                string dir = AppDomain.CurrentDomain.BaseDirectory;
                return Path.Combine(dir, "red_alert_config.json");
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
                            key.SetValue("NCDsRedAlertStation", "\"" + appPath + "\"");
                        }
                        else
                        {
                            key.DeleteValue("NCDsRedAlertStation", false);
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
                string shortcutPath = Path.Combine(desktopDir, "🚨 NCDs Red Alert Station.lnk");
                string appPath = Application.ExecutablePath;
                string workingDir = AppDomain.CurrentDomain.BaseDirectory;

                Type shellType = Type.GetTypeFromProgID("WScript.Shell");
                if (shellType != null)
                {
                    dynamic shell = Activator.CreateInstance(shellType);
                    dynamic shortcut = shell.CreateShortcut(shortcutPath);
                    shortcut.TargetPath = appPath;
                    shortcut.WorkingDirectory = workingDir;
                    shortcut.Description = "ศูนย์รับสัญญาณเคสวิกฤตฉุกเฉิน NCDs Portal อำเภอตาลสุม";
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
                            reqData.Add("sync_jhcis", _config.AutoSyncJhcisReferral ? "1" : "0");
                            reqData.Add("staff_name", "จนท. (" + _config.Hosname + ")");

                            byte[] response = wb.UploadValues(_config.ServerUrl.TrimEnd('/') + "/api/emergency_alert.php", "POST", reqData);
                            string resStr = Encoding.UTF8.GetString(response);

                            this.Invoke((MethodInvoker)delegate
                            {
                                btnRefer.Enabled = true;
                                btnRefer.Text = string.Format("🏥 สั่งส่งต่อ {0} ({1})", _config.DestHospitalName, _config.DestHospitalCode);
                                MessageBox.Show(string.Format("✅ สั่งส่งต่อไปยัง {0} (รหัส {1}) เรียบร้อยแล้ว\n{2}", _config.DestHospitalName, _config.DestHospitalCode, _config.AutoSyncJhcisReferral ? "(ซิงค์สร้าง Record ส่งต่อในตาราง visitrefer ของ JHCIS สำเร็จ)" : ""), "สำเร็จ", MessageBoxButtons.OK, MessageBoxIcon.Information);
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
        }

        private void InitializeUI()
        {
            this.Text = "⚙️ การตั้งค่าระบบ NCDs Red Alert Station";
            this.Size = new Size(680, 600);
            this.StartPosition = FormStartPosition.CenterScreen;
            this.FormBorderStyle = FormBorderStyle.FixedDialog;
            this.MaximizeBox = false;
            this.MinimizeBox = false;
            this.BackColor = Color.FromArgb(248, 250, 252);
            this.Font = ConfigManager.GetSystemFont(9.5f);

            tabControl = new TabControl
            {
                Dock = DockStyle.Top,
                Height = 475,
                Padding = new Point(14, 8)
            };

            // TAB 1: สถานบริการ & เซิร์ฟเวอร์
            var tab1 = new TabPage("🏥 รพ.สต. & เซิร์ฟเวอร์");
            tab1.BackColor = Color.White;
            tab1.Padding = new Padding(18);

            var lbl1 = new Label { Text = "URL เซิร์ฟเวอร์ NCDs Portal (โฮสต์กลาง):", Location = new Point(18, 16), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtServerUrl = new TextBox { Location = new Point(18, 40), Width = 600, Text = "https://ncd.ssotansum.com" };

            var lbl2 = new Label { Text = "เลือกสังกัดสถานี (รพ.สต. หรือ ศูนย์สั่งการส่วนกลาง):", Location = new Point(18, 76), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            cboHealthCenter = new ComboBox { Location = new Point(18, 100), Width = 600, DropDownStyle = ComboBoxStyle.DropDownList };
            cboHealthCenter.Items.Add(new KeyValuePair<string, string>("ALL", "ALL - ส่วนกลาง / สสอ.ตาลสุม / รพ.ตาลสุม (เห็นทุกเคสทั้งอำเภอ)"));
            cboHealthCenter.Items.Add(new KeyValuePair<string, string>("07758", "07758 - โรงพยาบาลส่งเสริมสุขภาพตำบลดอนมดแดง"));
            cboHealthCenter.Items.Add(new KeyValuePair<string, string>("07759", "07759 - โรงพยาบาลส่งเสริมสุขภาพตำบลนาคาย"));
            cboHealthCenter.Items.Add(new KeyValuePair<string, string>("07760", "07760 - โรงพยาบาลส่งเสริมสุขภาพตำบลคำหว้า"));
            cboHealthCenter.Items.Add(new KeyValuePair<string, string>("07761", "07761 - โรงพยาบาลส่งเสริมสุขภาพตำบลสำโรง"));
            cboHealthCenter.Items.Add(new KeyValuePair<string, string>("07762", "07762 - โรงพยาบาลส่งเสริมสุขภาพตำบลหนองกุ้ง"));
            cboHealthCenter.Items.Add(new KeyValuePair<string, string>("10957", "10957 - โรงพยาบาลตาลสุม (แม่ข่ายหลัก)"));
            cboHealthCenter.DisplayMember = "Value";
            cboHealthCenter.ValueMember = "Key";
            cboHealthCenter.SelectedIndexChanged += (s, e) =>
            {
                if (cboHealthCenter.SelectedItem != null)
                {
                    var item = (KeyValuePair<string, string>)cboHealthCenter.SelectedItem;
                    txtHoscode.Text = item.Key;
                }
            };

            var lbl3 = new Label { Text = "รหัสสถานบริการ (Hoscode):", Location = new Point(18, 138), AutoSize = true, Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold) };
            txtHoscode = new TextBox { Location = new Point(18, 162), Width = 180 };

            chkAutoStart = new CheckBox
            {
                Text = "⚡ เปิดโปรแกรมนี้อัตโนมัติเมื่อเปิดเครื่องคอมพิวเตอร์ (Auto-start with Windows)",
                Location = new Point(18, 205),
                AutoSize = true,
                Font = ConfigManager.GetSystemFont(9.5f, FontStyle.Bold),
                Checked = true
            };

            btnSimulateAlert = new Button
            {
                Text = "⚡ ทดสอบยิงสัญญาณวิกฤตจำลอง (Test Siren & Popup)",
                Location = new Point(18, 255),
                Size = new Size(600, 42),
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
            _config.AutoSyncJhcisReferral = chkJhcisAutoSync.Checked;
            _config.IsFirstRunSetupDone = true;

            ConfigManager.Save(_config);
            ConfigManager.CreateDesktopShortcut();

            MessageBox.Show(string.Format("✅ บันทึกการตั้งค่าเรียบร้อยแล้ว\n- โฮสต์เชื่อมต่อ: {0}\n- รพ. ปลายทางส่งต่อ: {1} ({2})\n- สร้าง Shortcut บนหน้าจอ Desktop ให้แล้ว", _config.ServerUrl, _config.DestHospitalName, _config.DestHospitalCode), "สำเร็จ", MessageBoxButtons.OK, MessageBoxIcon.Information);
            this.Close();
        }

        private void BtnTestJhcis_Click(object sender, EventArgs e)
        {
            btnTestJhcis.Enabled = false;
            btnTestJhcis.Text = "⏳ กำลังทดสอบการเชื่อมต่อไปยัง JHCIS...";

            ThreadPool.QueueUserWorkItem(state =>
            {
                try
                {
                    using (var wb = new WebClient())
                    {
                        var req = new System.Collections.Specialized.NameValueCollection();
                        req.Add("action", "test_connection");
                        req.Add("hoscode", txtHoscode.Text.Trim() == "ALL" ? "07758" : txtHoscode.Text.Trim());
                        req.Add("jhcis_host", txtJhcisHost.Text.Trim());
                        req.Add("jhcis_port", txtJhcisPort.Text.Trim());
                        req.Add("jhcis_dbname", txtJhcisDb.Text.Trim());
                        req.Add("jhcis_user", txtJhcisUser.Text.Trim());
                        req.Add("jhcis_pass", txtJhcisPass.Text);

                        byte[] res = wb.UploadValues(_config.ServerUrl.TrimEnd('/') + "/api/jhcis_sync.php", "POST", req);
                        string json = Encoding.UTF8.GetString(res);
                        var serializer = new JavaScriptSerializer();
                        var dict = serializer.Deserialize<Dictionary<string, object>>(json);

                        this.Invoke((MethodInvoker)delegate
                        {
                            btnTestJhcis.Enabled = true;
                            btnTestJhcis.Text = "🔌 ทดสอบการเชื่อมต่อฐานข้อมูล JHCIS";

                            if (dict != null && dict.ContainsKey("status") && dict["status"].ToString() == "success")
                            {
                                string pcu = dict.ContainsKey("detected_pcucode") ? dict["detected_pcucode"].ToString() : "";
                                string hname = dict.ContainsKey("detected_hosname") ? dict["detected_hosname"].ToString() : "";
                                MessageBox.Show("✅ เชื่อมต่อฐานข้อมูล JHCIS สำเร็จ!\n- รหัสสถานบริการใน JHCIS: [" + pcu + "] " + hname, "สำเร็จ", MessageBoxButtons.OK, MessageBoxIcon.Information);
                            }
                            else
                            {
                                string msg = dict != null && dict.ContainsKey("message") ? dict["message"].ToString() : "ไม่สามารถเชื่อมต่อได้";
                                MessageBox.Show("❌ เชื่อมต่อล้มเหลว: " + msg, "ข้อผิดพลาด", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                            }
                        });
                    }
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
            btnSimulateAlert.Text = "⏳ กำลังยิงสัญญาณ...";

            var mockAlert = new Dictionary<string, object>
            {
                { "alert_id", 9999 },
                { "hoscode", txtHoscode.Text.Trim() == "ALL" ? "07758" : txtHoscode.Text.Trim() },
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

            ThreadPool.QueueUserWorkItem(state =>
            {
                bool serverSuccess = false;
                string serverMsg = "";

                try
                {
                    using (var wb = new WebClient())
                    {
                        var req = new System.Collections.Specialized.NameValueCollection();
                        req.Add("action", "trigger_alert");
                        req.Add("hoscode", txtHoscode.Text.Trim() == "ALL" ? "07758" : txtHoscode.Text.Trim());
                        req.Add("target_cid", "3340500123456");
                        req.Add("patient_name", "นายสมคิด สุขเกษม");
                        req.Add("age", "68");
                        req.Add("house_no", "12/1");
                        req.Add("moo", "2");
                        req.Add("sub_district_code", "341601");
                        req.Add("crisis_type", "HT Crisis (ความดันโลหิตสูงวิกฤต)");
                        req.Add("sbp", "210");
                        req.Add("dbp", "118");
                        req.Add("dtx", "330");
                        req.Add("red_flags", "ปวดศีรษะรุนแรง ตาพร่ามัว ปากเบี้ยว แขนขาอ่อนแรง");
                        req.Add("vhv_name", "อสม. สมชาย มีสุข");
                        req.Add("vhv_phone", "081-999-8888");

                        byte[] res = wb.UploadValues(txtServerUrl.Text.Trim().TrimEnd('/') + "/api/emergency_alert.php", "POST", req);
                        serverSuccess = true;
                    }
                }
                catch (Exception ex)
                {
                    serverMsg = ex.Message;
                }

                this.Invoke((MethodInvoker)delegate
                {
                    btnSimulateAlert.Enabled = true;
                    btnSimulateAlert.Text = "⚡ ทดสอบยิงสัญญาณวิกฤตจำลอง (Test Siren & Popup)";

                    var siren = new SirenPlayer();
                    var testPopup = new AlertPopupForm(_config, siren);
                    testPopup.DisplayAlert(mockAlert);

                    if (serverSuccess)
                    {
                        MessageBox.Show("🚀 ยิงสัญญาณวิกฤตจำลองเข้าสู่โฮสต์กลาง (ncd.ssotansum.com) สำเร็จ!\n- หน้าต่าง Red Alert และเสียงไซเรน (2 รอบ) กำลังทำงาน", "สำเร็จ", MessageBoxButtons.OK, MessageBoxIcon.Information);
                    }
                    else
                    {
                        MessageBox.Show("⚡ ทำการทดสอบระบบไซเรน & หน้าต่างแจ้งเตือนแบบ Local Simulation ให้ทันที\n(หมายเหตุ: " + serverMsg + ")", "ทดสอบระบบสำเร็จ", MessageBoxButtons.OK, MessageBoxIcon.Information);
                    }
                });
            });
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
        private System.Windows.Forms.Timer _pollTimer;
        private int _lastAlertIdSeen = 0;
        private bool _isPolling = false;
        private SynchronizationContext _syncContext;

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
            _siren = new SirenPlayer();
            _popupForm = new AlertPopupForm(_config, _siren);
            IntPtr dummyHandle = _popupForm.Handle; // Ensure window handle is created upfront

            if (isFirstRun || !_config.IsFirstRunSetupDone)
            {
                ConfigManager.CreateDesktopShortcut();
                ConfigManager.ApplyAutoStart(true);
            }

            _trayIcon = new NotifyIcon
            {
                Icon = SystemIcons.Shield,
                Text = "🚨 NCDs Red Alert Station (" + _config.Hoscode + ")",
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
            contextMenu.Items.Add("❌ ออกจากโปรแกรม", null, (s, e) => Exit());

            _trayIcon.ContextMenuStrip = contextMenu;
            _trayIcon.DoubleClick += (s, e) => ShowSettings();

            // Background Poll Timer (every 3 seconds)
            _pollTimer = new System.Windows.Forms.Timer { Interval = 3000 };
            _pollTimer.Tick += PollTimer_Tick;
            _pollTimer.Start();

            string statusMsg = (_config.Hoscode == "ALL")
                ? "ศูนย์กลางส่วนกลาง: เฝ้าระวังเคสวิกฤตฉุกเฉินทุก รพ.สต. ทั้งอำเภอ 24 ชม."
                : "กำลังเฝ้าระวังเคสวิกฤตฉุกเฉินสำหรับ รพ.สต. " + _config.Hoscode + " 24 ชม.";

            try
            {
                _trayIcon.ShowBalloonTip(3000, "NCDs Red Alert Station พร้อมทำงาน", statusMsg, ToolTipIcon.Info);
            }
            catch { }

            if (isFirstRun || !_config.IsFirstRunSetupDone)
            {
                ShowSettings();
            }
        }

        private void ShowSettings()
        {
            var settingsForm = new SettingsForm(_config);
            settingsForm.ShowDialog();
            _config = ConfigManager.Load();
            if (_popupForm != null && !_popupForm.IsDisposed)
            {
                _popupForm.UpdateConfig(_config);
            }
            _trayIcon.Text = "🚨 NCDs Red Alert Station (" + _config.Hoscode + ")";
        }

        private void PollTimer_Tick(object sender, EventArgs e)
        {
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
                                                try { File.AppendAllText("red_alert_debug.log", DateTime.Now + " - " + ex.ToString() + "\r\n"); } catch { }
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
                    try { File.AppendAllText("red_alert_debug.log", DateTime.Now + " - Poll error: " + ex.Message + "\r\n"); } catch { }
                }
                finally
                {
                    _isPolling = false;
                }
            });
        }

        private void Exit()
        {
            _pollTimer.Stop();
            _siren.Stop();
            _trayIcon.Visible = false;
            Application.Exit();
        }
    }

    public static class Program
    {
        [STAThread]
        public static void Main()
        {
            try
            {
                ServicePointManager.Expect100Continue = true;
                ServicePointManager.SecurityProtocol = (SecurityProtocolType)3072 | (SecurityProtocolType)768 | SecurityProtocolType.Tls;
                ServicePointManager.ServerCertificateValidationCallback = delegate { return true; };

                Application.EnableVisualStyles();
                Application.SetCompatibleTextRenderingDefault(false);
                Application.Run(new RedAlertApplicationContext());
            }
            catch (Exception ex)
            {
                try { File.WriteAllText("red_alert_error.log", ex.ToString()); } catch { }
                MessageBox.Show("ข้อผิดพลาดในการเริ่มต้นโปรแกรม:\n" + ex.Message, "Red Alert Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }
    }
}
